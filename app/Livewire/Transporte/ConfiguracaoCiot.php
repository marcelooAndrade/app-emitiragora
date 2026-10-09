<?php

namespace App\Livewire\Transporte;

use App\Enums\Fiscal\Ambiente;
use App\Models\Emitente;
use App\Models\EmitenteCiot;
use App\Services\Transporte\Ciot\ProvedoresCiot;
use App\Services\Transporte\Ciot\ServicoCiot;
use App\Services\Transporte\TransporteException;
use App\Support\EmitenteAtual;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Qual empresa gera o CIOT deste emitente, com que credenciais, e como os
 * clientes pagam o frete (a ANTT pede também na frota própria).
 *
 * Os campos de credencial são os que o adaptador da empresa declara em
 * `GatewayCiot::campos()`: empresa nova não precisa de tela nova. Campo
 * marcado como segredo nunca volta para a tela; em branco, continua o que
 * estava salvo.
 */
#[Layout('components.layouts.fiscal')]
#[Title('CIOT')]
class ConfiguracaoCiot extends Component
{
    public string $provedor = 'manual';

    /** @var array<string, mixed> */
    public array $homologacao = [];

    /** @var array<string, mixed> */
    public array $producao = [];

    public string $recebimentoTipo = 'pix';

    public string $recebimentoBanco = '';

    public string $recebimentoAgencia = '';

    public string $recebimentoConta = '';

    public function mount(): void
    {
        abort_unless($this->emitente !== null, 404, 'Nenhum emitente vinculado a este usuário.');
        $this->authorize('transporte.ver');
        $c = $this->config;
        $this->provedor = $c->provedor;
        $this->recebimentoTipo = $c->recebimento_tipo;
        $this->recebimentoBanco = (string) $c->recebimento_banco;
        $this->recebimentoAgencia = (string) $c->recebimento_agencia;
        $this->recebimentoConta = (string) $c->recebimento_conta;
        $this->carregarCredenciais();
    }

    #[Computed]
    public function emitente(): ?Emitente
    {
        return app(EmitenteAtual::class)->resolver();
    }

    #[Computed]
    public function config(): EmitenteCiot
    {
        return $this->emitente->configuracaoCiot();
    }

    /** @return array<string, array{classe: string, nome: string, descricao: string, producao: bool}> */
    #[Computed]
    public function provedores(): array
    {
        return app(ProvedoresCiot::class)->todos();
    }

    /** @return array<string, array{rotulo: string, segredo?: bool, tipo?: string, opcoes?: array<string, string>, ajuda?: string}> */
    #[Computed]
    public function campos(): array
    {
        $provedores = app(ProvedoresCiot::class);

        return $provedores->existe($this->provedor) ? $provedores->gateway($this->provedor)->campos() : [];
    }

    /** Segredos já salvos por ambiente, para a tela dizer "salvo" sem mostrar. */
    #[Computed]
    public function salvos(): array
    {
        $salvos = [];
        foreach (['homologacao' => Ambiente::Homologacao, 'producao' => Ambiente::Producao] as $chave => $ambiente) {
            $credenciais = $this->config->credenciais($this->provedor, $ambiente);
            foreach ($this->campos as $campo => $definicao) {
                $salvos[$chave][$campo] = filled($credenciais[$campo] ?? null);
            }
        }

        return $salvos;
    }

    public function updatedProvedor(): void
    {
        unset($this->campos, $this->salvos);
        $this->carregarCredenciais();
    }

    public function salvar(): void
    {
        $this->authorize('transporte.configurar');
        $this->validate([
            'provedor' => ['required', 'in:'.implode(',', array_keys($this->provedores))],
            'recebimentoTipo' => ['required', 'in:pix,transferencia,boleto'],
            'recebimentoBanco' => ['nullable', 'required_if:recebimentoTipo,transferencia', 'digits:3'],
            'recebimentoAgencia' => ['nullable', 'required_if:recebimentoTipo,transferencia', 'string', 'max:10'],
            'recebimentoConta' => ['nullable', 'required_if:recebimentoTipo,transferencia', 'string', 'max:20'],
            'homologacao.*' => ['nullable', 'max:200'],
            'producao.*' => ['nullable', 'max:200'],
        ], [
            'recebimentoBanco.required_if' => 'Informe o código do banco (3 dígitos).',
            'recebimentoAgencia.required_if' => 'Informe a agência.',
            'recebimentoConta.required_if' => 'Informe a conta.',
        ], ['recebimentoBanco' => 'banco', 'recebimentoAgencia' => 'agência', 'recebimentoConta' => 'conta']);

        $config = $this->config;
        foreach (['homologacao' => Ambiente::Homologacao, 'producao' => Ambiente::Producao] as $chave => $ambiente) {
            if ($this->campos === []) {
                continue;
            }
            $atuais = $config->credenciais($this->provedor, $ambiente);
            $novas = [];
            foreach ($this->campos as $campo => $definicao) {
                $valor = $this->{$chave}[$campo] ?? null;
                if (($definicao['tipo'] ?? null) === 'checkbox') {
                    $novas[$campo] = (bool) $valor;
                } elseif (($definicao['segredo'] ?? false) && blank($valor)) {
                    $novas[$campo] = $atuais[$campo] ?? null;
                } else {
                    $novas[$campo] = is_string($valor) ? trim($valor) : $valor;
                }
            }
            $config->guardarCredenciais($this->provedor, $ambiente, $novas);
        }

        $transferencia = $this->recebimentoTipo === 'transferencia';
        $config->forceFill([
            'provedor' => $this->provedor,
            'recebimento_tipo' => $this->recebimentoTipo,
            'recebimento_banco' => $transferencia ? $this->recebimentoBanco : null,
            'recebimento_agencia' => $transferencia ? trim($this->recebimentoAgencia) : null,
            'recebimento_conta' => $transferencia ? trim($this->recebimentoConta) : null,
        ])->save();

        unset($this->config, $this->salvos);
        $this->carregarCredenciais();
        session()->flash('sucesso', 'Configuração do CIOT salva.');
    }

    public function testarConexao(ServicoCiot $ciot): void
    {
        $this->authorize('transporte.configurar');
        $this->salvar();
        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }
        try {
            session()->flash('sucesso', $ciot->testarConexao($this->emitente->fresh()));
        } catch (TransporteException $e) {
            $this->addError('teste', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.transporte.configuracao-ciot');
    }

    /** Preenche o que não é segredo; segredo fica em branco na tela. */
    private function carregarCredenciais(): void
    {
        foreach (['homologacao' => Ambiente::Homologacao, 'producao' => Ambiente::Producao] as $chave => $ambiente) {
            $credenciais = $this->config->credenciais($this->provedor, $ambiente);
            $valores = [];
            foreach ($this->campos as $campo => $definicao) {
                $valores[$campo] = match (true) {
                    $definicao['segredo'] ?? false => '',
                    ($definicao['tipo'] ?? null) === 'checkbox' => (bool) ($credenciais[$campo] ?? false),
                    ($definicao['tipo'] ?? null) === 'select' => (string) ($credenciais[$campo] ?? array_key_first($definicao['opcoes'] ?? [])),
                    default => (string) ($credenciais[$campo] ?? ''),
                };
            }
            $this->{$chave} = $valores;
        }
    }
}
