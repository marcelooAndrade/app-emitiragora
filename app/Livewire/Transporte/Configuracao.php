<?php

namespace App\Livewire\Transporte;

use App\Models\Emitente;
use App\Models\EmitenteTransporte;
use App\Models\RegraIcmsTransporte;
use App\Models\TransporteSerie;
use App\Services\Transporte\NumeracaoTransporte;
use App\Support\Documento;
use App\Support\EmitenteAtual;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * O que o emitente precisa a mais para CT-e e MDF-e, e as regras de ICMS do
 * frete. No Transm isso era a "configuração fiscal da empresa" inteira
 * (endereço, certificado, CNPJ); aqui isso já vive no Emitente, e esta tela
 * cuida só do que é de transporte.
 */
#[Layout('components.layouts.fiscal')]
#[Title('Configuração do transporte')]
class Configuracao extends Component
{
    public string $rntrc = '';

    public string $cteSerie = '1';

    public string $mdfeSerie = '1';

    public string $cfop = '5353';

    public string $naturezaOperacao = '';

    public string $tipoEmitenteMdfe = '1';

    public string $seguradoraNome = '';

    public string $seguradoraCnpj = '';

    public string $apolice = '';

    public string $responsavelSeguro = '1';

    public string $prazoFaturaDias = '30';

    public string $adiantamentoPercentual = '80';

    // AT&M: a senha nunca volta para a tela; em branco, fica a que está salva.
    public string $atmUsuario = '';

    public string $atmSenha = '';

    public string $atmCodigo = '';

    // e-Frete: senha e hash também nunca voltam para a tela.
    public string $efreteUsuario = '';

    public string $efreteSenha = '';

    public string $efreteIntegrador = '';

    public bool $efreteMassaAntt = false;

    // Regra de ICMS em edição.
    public ?int $regraId = null;

    public string $regraNome = '';

    public string $regraUfOrigem = '';

    public string $regraUfDestino = '';

    public string $regraCst = '00';

    public string $regraAliquota = '';

    public string $regraReducao = '';

    public string $regraPercurso = '';

    public function mount(): void
    {
        abort_unless($this->emitente !== null, 404, 'Nenhum emitente vinculado a este usuário.');
        $this->authorize('transporte.ver');
        $c = $this->config;
        $this->rntrc = (string) $c->rntrc;
        $this->cteSerie = (string) $c->cte_serie;
        $this->mdfeSerie = (string) $c->mdfe_serie;
        $this->cfop = (string) $c->cfop;
        $this->naturezaOperacao = (string) $c->natureza_operacao;
        $this->tipoEmitenteMdfe = (string) $c->tipo_emitente_mdfe;
        $this->seguradoraNome = (string) $c->seguradora_nome;
        $this->seguradoraCnpj = (string) $c->seguradora_cnpj;
        $this->apolice = (string) $c->apolice;
        $this->responsavelSeguro = (string) $c->responsavel_seguro;
        $this->prazoFaturaDias = (string) $c->prazo_fatura_dias;
        $this->adiantamentoPercentual = (string) $c->adiantamento_percentual;
        $this->atmUsuario = (string) $c->atm_usuario;
        $this->atmCodigo = (string) $c->atm_codigo;
        $this->efreteUsuario = (string) $c->efrete_usuario;
        $this->efreteMassaAntt = (bool) $c->efrete_massa_antt;
    }

    #[Computed]
    public function emitente(): ?Emitente
    {
        return app(EmitenteAtual::class)->resolver();
    }

    #[Computed]
    public function config(): EmitenteTransporte
    {
        return $this->emitente->configuracaoTransporte();
    }

    #[Computed]
    public function regras(): Collection
    {
        return RegraIcmsTransporte::where('emitente_id', $this->emitente->getKey())->orderBy('prioridade')->orderBy('uf_origem')->orderBy('uf_destino')->get();
    }

    /** @return array{cte: int, mdfe: int} */
    #[Computed]
    public function proximos(): array
    {
        $numeracao = app(NumeracaoTransporte::class);

        return [
            'cte' => $numeracao->previsto($this->emitente, TransporteSerie::CTE, (int) $this->config->cte_serie),
            'mdfe' => $numeracao->previsto($this->emitente, TransporteSerie::MDFE, (int) $this->config->mdfe_serie),
        ];
    }

    public function salvar(): void
    {
        $this->authorize('transporte.configurar');
        $this->rntrc = preg_replace('/\D/', '', $this->rntrc);
        $this->seguradoraCnpj = preg_replace('/\D/', '', $this->seguradoraCnpj);
        $this->validate([
            'rntrc' => ['nullable', 'digits:8'],
            'cteSerie' => ['required', 'integer', 'min:0', 'max:999'],
            'mdfeSerie' => ['required', 'integer', 'min:0', 'max:999'],
            'cfop' => ['required', 'regex:/^[56]3(5[1-9]|60)$/'],
            'naturezaOperacao' => ['required', 'string', 'max:60'],
            'tipoEmitenteMdfe' => ['required', 'in:1,2'],
            'seguradoraNome' => ['nullable', 'string', 'max:30'],
            'seguradoraCnpj' => ['nullable', fn ($a, $v, $falhar) => $v === '' || Documento::cnpjValido((string) $v) ? null : $falhar('CNPJ da seguradora inválido.')],
            'apolice' => ['nullable', 'string', 'max:20'],
            'responsavelSeguro' => ['required', 'in:1,2'],
            'prazoFaturaDias' => ['required', 'integer', 'min:0', 'max:365'],
            'adiantamentoPercentual' => ['required', 'integer', 'min:0', 'max:100'],
            'atmUsuario' => ['nullable', 'string', 'max:100'],
            'atmSenha' => ['nullable', 'string', 'max:100'],
            'atmCodigo' => ['nullable', 'string', 'max:30', 'required_with:atmUsuario'],
            'efreteUsuario' => ['nullable', 'string', 'max:100'],
            'efreteSenha' => ['nullable', 'string', 'max:100'],
            'efreteIntegrador' => ['nullable', 'string', 'max:200'],
        ], ['cfop.regex' => 'Use um CFOP de prestação de serviço de transporte (5351 a 5360).'], [
            'rntrc' => 'RNTRC', 'cteSerie' => 'série do CT-e', 'mdfeSerie' => 'série do MDF-e', 'naturezaOperacao' => 'natureza da operação',
            'seguradoraNome' => 'seguradora', 'prazoFaturaDias' => 'prazo da fatura',
            'adiantamentoPercentual' => 'adiantamento padrão', 'atmUsuario' => 'usuário da AT&M', 'atmCodigo' => 'código da AT&M',
        ]);
        $usaAtm = trim($this->atmUsuario) !== '';
        if ($usaAtm && $this->atmSenha === '' && blank($this->config->atm_senha)) {
            $this->addError('atmSenha', 'Informe a senha da AT&M.');

            return;
        }
        $usaEfrete = trim($this->efreteUsuario) !== '';
        foreach (['efreteSenha' => ['efrete_senha', 'a senha do e-Frete'], 'efreteIntegrador' => ['efrete_integrador', 'o hash do integrador']] as $campo => [$coluna, $nome]) {
            if ($usaEfrete && $this->{$campo} === '' && blank($this->config->{$coluna})) {
                $this->addError($campo, "Informe {$nome}.");

                return;
            }
        }

        $this->config->update([
            'rntrc' => $this->rntrc ?: null,
            'cte_serie' => (int) $this->cteSerie,
            'mdfe_serie' => (int) $this->mdfeSerie,
            'cfop' => '5'.substr($this->cfop, 1),
            'natureza_operacao' => mb_strtoupper(trim($this->naturezaOperacao)),
            'tipo_emitente_mdfe' => $this->tipoEmitenteMdfe,
            'seguradora_nome' => $this->seguradoraNome !== '' ? mb_strtoupper(trim($this->seguradoraNome)) : null,
            'seguradora_cnpj' => $this->seguradoraCnpj ?: null,
            'apolice' => $this->apolice !== '' ? trim($this->apolice) : null,
            'responsavel_seguro' => $this->responsavelSeguro,
            'prazo_fatura_dias' => (int) $this->prazoFaturaDias,
            'adiantamento_percentual' => (int) $this->adiantamentoPercentual,
            // Usuário em branco desliga a integração e apaga as credenciais.
            'atm_usuario' => $usaAtm ? trim($this->atmUsuario) : null,
            'atm_senha' => $usaAtm ? ($this->atmSenha !== '' ? $this->atmSenha : $this->config->atm_senha) : null,
            'atm_codigo' => $usaAtm ? trim($this->atmCodigo) : null,
            'efrete_usuario' => $usaEfrete ? trim($this->efreteUsuario) : null,
            'efrete_senha' => $usaEfrete ? ($this->efreteSenha !== '' ? $this->efreteSenha : $this->config->efrete_senha) : null,
            'efrete_integrador' => $usaEfrete ? ($this->efreteIntegrador !== '' ? trim($this->efreteIntegrador) : $this->config->efrete_integrador) : null,
            'efrete_massa_antt' => $usaEfrete && $this->efreteMassaAntt,
        ]);
        $this->atmSenha = '';
        $this->efreteSenha = '';
        $this->efreteIntegrador = '';
        unset($this->config, $this->proximos);
        session()->flash('sucesso', 'Configuração salva.');
    }

    public function editarRegra(int $id): void
    {
        $this->authorize('transporte.configurar');
        $r = $this->regras->firstWhere('id', $id);
        abort_if($r === null, 404);
        $this->resetErrorBag();
        $this->regraId = $r->id;
        $this->regraNome = $r->nome;
        $this->regraUfOrigem = (string) $r->uf_origem;
        $this->regraUfDestino = (string) $r->uf_destino;
        $this->regraCst = $r->cst;
        $this->regraAliquota = number_format((float) $r->aliquota, 2, ',', '');
        $this->regraReducao = (float) $r->reducao_base > 0 ? number_format((float) $r->reducao_base, 2, ',', '') : '';
        $this->regraPercurso = implode(', ', (array) $r->percurso_ufs);
    }

    public function novaRegra(): void
    {
        $this->resetErrorBag();
        $this->reset('regraId', 'regraNome', 'regraUfOrigem', 'regraUfDestino', 'regraCst', 'regraAliquota', 'regraReducao', 'regraPercurso');
    }

    public function salvarRegra(): void
    {
        $this->authorize('transporte.configurar');
        $this->regraUfOrigem = strtoupper(trim($this->regraUfOrigem));
        $this->regraUfDestino = strtoupper(trim($this->regraUfDestino));
        $this->validate([
            'regraNome' => ['required', 'string', 'max:80'],
            'regraUfOrigem' => ['nullable', 'alpha', 'size:2'],
            'regraUfDestino' => ['nullable', 'alpha', 'size:2'],
            'regraCst' => ['required', 'in:'.implode(',', array_keys(RegraIcmsTransporte::CSTS))],
            'regraAliquota' => [in_array($this->regraCst, ['00', '20', '90'], true) ? 'required' : 'nullable'],
        ], attributes: ['regraNome' => 'nome', 'regraUfOrigem' => 'UF de origem', 'regraUfDestino' => 'UF de destino', 'regraCst' => 'CST', 'regraAliquota' => 'alíquota']);

        $percentual = fn (string $v): float => (float) str_replace(',', '.', $v);
        $dados = [
            'nome' => trim($this->regraNome),
            'uf_origem' => $this->regraUfOrigem ?: null,
            'uf_destino' => $this->regraUfDestino ?: null,
            'cst' => $this->regraCst,
            'aliquota' => $percentual($this->regraAliquota),
            'reducao_base' => $percentual($this->regraReducao),
            'percurso_ufs' => collect(preg_split('/[\s,;]+/', mb_strtoupper($this->regraPercurso)))->filter(fn ($uf) => preg_match('/^[A-Z]{2}$/', (string) $uf) === 1)->values()->all() ?: null,
        ];
        if ($this->regraId) {
            $this->regras->firstWhere('id', $this->regraId)?->update($dados);
        } else {
            (new RegraIcmsTransporte($dados))->forceFill(['emitente_id' => $this->emitente->getKey()])->save();
        }
        $this->novaRegra();
        unset($this->regras);
        session()->flash('sucesso', 'Regra de ICMS salva.');
    }

    public function excluirRegra(int $id): void
    {
        $this->authorize('transporte.configurar');
        $this->regras->firstWhere('id', $id)?->delete();
        unset($this->regras);
    }

    public function render()
    {
        return view('livewire.transporte.configuracao');
    }
}
