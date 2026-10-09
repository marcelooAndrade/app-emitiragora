<?php

namespace App\Livewire\Transporte;

use App\Models\Emitente;
use App\Models\Veiculo;
use App\Models\Viagem;
use App\Services\Integrations\ViaCepService;
use App\Support\Documento;
use App\Support\EmitenteAtual;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use RuntimeException;

/**
 * Cavalos e carretas. No Transm eram três cadastros (cavalos, carretas e
 * proprietários); aqui é um só, e o proprietário só aparece quando o
 * veículo é de terceiro, que é o único caso em que o MDF-e pede.
 */
#[Layout('components.layouts.fiscal')]
#[Title('Veículos')]
class Veiculos extends Component
{
    // Padrão de listagem de 09/10/2026: lista na largura toda, formulário numa janela.
    public bool $formularioAberto = false;

    public string $busca = '';

    public ?int $editandoId = null;

    public string $tipo = 'tracao';

    public string $placa = '';

    public string $renavam = '';

    public string $uf = '';

    public string $tara = '';

    public string $capacidade = '';

    public string $rodado = '03';

    public string $carroceria = '02';

    public string $proprietarioTipo = 'proprio';

    public string $proprietarioDocumento = '';

    public string $proprietarioNome = '';

    public string $proprietarioRntrc = '';

    public string $proprietarioIe = '';

    public string $proprietarioUf = '';

    public string $proprietarioTp = '1';

    // e-Frete: chassi e eixos do veículo, endereço do proprietário.
    public string $chassi = '';

    public string $eixos = '';

    public string $proprietarioCep = '';

    public string $proprietarioMunicipioCodigo = '';

    public string $proprietarioMunicipio = '';

    public string $proprietarioLogradouro = '';

    public string $proprietarioNumero = '';

    public string $proprietarioBairro = '';

    public function mount(): void
    {
        abort_unless($this->emitente !== null, 404, 'Nenhum emitente vinculado a este usuário.');
        $this->authorize('transporte.ver');
        $this->uf = (string) $this->emitente->uf;
    }

    #[Computed]
    public function emitente(): ?Emitente
    {
        return app(EmitenteAtual::class)->resolver();
    }

    #[Computed]
    public function usaEfrete(): bool
    {
        return $this->emitente->configuracaoCiot()->provedor === 'efrete';
    }

    public function updatedProprietarioCep(ViaCepService $viaCep): void
    {
        $this->proprietarioCep = preg_replace('/\D/', '', $this->proprietarioCep);
        if (strlen($this->proprietarioCep) !== 8) {
            return;
        }
        try {
            $r = $viaCep->consultar($this->proprietarioCep);
            $this->proprietarioLogradouro = $this->proprietarioLogradouro ?: (string) $r->logradouro;
            $this->proprietarioBairro = $this->proprietarioBairro ?: (string) $r->bairro;
            $this->proprietarioMunicipioCodigo = (string) $r->codigoIbge;
            $this->proprietarioMunicipio = trim($r->municipio.'/'.$r->uf, '/');
            $this->resetErrorBag('proprietarioCep');
        } catch (RuntimeException $e) {
            $this->addError('proprietarioCep', $e->getMessage());
        }
    }

    #[Computed]
    public function veiculos(): Collection
    {
        return Veiculo::where('emitente_id', $this->emitente->getKey())->orderByDesc('ativo')->orderBy('tipo')->orderBy('placa')->get();
    }

    /** O que a lista mostra: filtrado pela busca por placa ou proprietário. */
    #[Computed]
    public function listados(): Collection
    {
        $termo = mb_strtoupper(trim($this->busca));
        if ($termo === '') {
            return $this->veiculos;
        }
        $placa = preg_replace('/[^A-Z0-9]/', '', $termo);

        return $this->veiculos->filter(fn (Veiculo $v): bool => ($placa !== '' && str_contains((string) $v->placa, $placa))
            || str_contains(mb_strtoupper((string) $v->proprietario_nome), $termo))->values();
    }

    public function editar(int $id): void
    {
        $this->authorize('transporte.operar');
        $v = $this->veiculos->firstWhere('id', $id);
        abort_if($v === null, 404);
        $this->resetErrorBag();
        $this->editandoId = $v->id;
        $this->tipo = $v->tipo;
        $this->placa = $v->placa;
        $this->renavam = (string) $v->renavam;
        $this->uf = $v->uf;
        $this->tara = (string) $v->tara_kg;
        $this->capacidade = (string) $v->capacidade_kg;
        $this->rodado = (string) ($v->tipo_rodado ?? '03');
        $this->carroceria = $v->tipo_carroceria;
        $this->proprietarioTipo = $v->proprietario_tipo;
        $this->proprietarioDocumento = (string) $v->proprietario_documento;
        $this->proprietarioNome = (string) $v->proprietario_nome;
        $this->proprietarioRntrc = (string) $v->proprietario_rntrc;
        $this->proprietarioIe = (string) $v->proprietario_ie;
        $this->proprietarioUf = (string) $v->proprietario_uf;
        $this->proprietarioTp = (string) ($v->proprietario_tp ?? '1');
        $this->chassi = (string) $v->chassi;
        $this->eixos = (string) $v->eixos;
        $this->proprietarioCep = (string) $v->proprietario_cep;
        $this->proprietarioMunicipioCodigo = (string) $v->proprietario_municipio_codigo;
        $this->proprietarioMunicipio = $v->proprietario_municipio_codigo ? 'IBGE '.$v->proprietario_municipio_codigo : '';
        $this->proprietarioLogradouro = (string) $v->proprietario_logradouro;
        $this->proprietarioNumero = (string) $v->proprietario_numero;
        $this->proprietarioBairro = (string) $v->proprietario_bairro;
        $this->formularioAberto = true;
    }

    public function novo(): void
    {
        $this->authorize('transporte.operar');
        $this->limparFormulario();
        $this->formularioAberto = true;
    }

    public function fecharFormulario(): void
    {
        $this->limparFormulario();
        $this->formularioAberto = false;
    }

    /**
     * Veículo que nunca rodou some de vez. O que já está em alguma viagem
     * (cavalo ou carreta) só é inativado, para não sumir do histórico.
     */
    public function excluir(int $id): void
    {
        $this->authorize('transporte.operar');
        $v = $this->veiculos->firstWhere('id', $id);
        abort_if($v === null, 404);
        $usado = Viagem::where(fn ($q) => $q->where('veiculo_id', $v->id)->orWhere('reboque_id', $v->id)->orWhere('reboque2_id', $v->id))->exists();
        if ($usado) {
            $v->update(['ativo' => false]);
            session()->flash('sucesso', "O veículo {$v->placaFormatada()} já está em viagens: foi inativado e não aparece mais para escolher.");
        } else {
            $v->delete();
            session()->flash('sucesso', "Veículo {$v->placaFormatada()} excluído.");
        }
        unset($this->veiculos, $this->listados);
    }

    public function salvar(): void
    {
        $this->authorize('transporte.operar');
        $this->placa = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $this->placa));
        $this->proprietarioDocumento = preg_replace('/\D/', '', $this->proprietarioDocumento);
        $this->proprietarioRntrc = preg_replace('/\D/', '', $this->proprietarioRntrc);
        $this->chassi = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $this->chassi));
        $terceiro = $this->proprietarioTipo === 'terceiro';

        $this->validate([
            'tipo' => ['required', 'in:tracao,reboque'],
            'placa' => ['required', 'regex:/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/', Rule::unique('veiculos', 'placa')->where('emitente_id', $this->emitente->getKey())->ignore($this->editandoId)],
            'renavam' => ['nullable', 'digits_between:9,11'],
            'uf' => ['required', 'size:2'],
            'tara' => ['required', 'integer', 'min:1', 'max:99999'],
            'capacidade' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'rodado' => [$this->tipo === 'tracao' ? 'required' : 'nullable', 'in:'.implode(',', array_keys(Veiculo::TIPOS_RODADO))],
            'carroceria' => ['required', 'in:'.implode(',', array_keys(Veiculo::TIPOS_CARROCERIA))],
            'proprietarioTipo' => ['required', 'in:proprio,terceiro'],
            'proprietarioDocumento' => [$terceiro ? 'required' : 'nullable', function ($a, $v, $falhar) use ($terceiro): void {
                if ($terceiro && ! (Documento::cpfValido((string) $v) || Documento::cnpjValido((string) $v))) {
                    $falhar('CPF ou CNPJ inválido.');
                }
            }],
            'proprietarioNome' => [$terceiro ? 'required' : 'nullable', 'string', 'max:60'],
            'proprietarioRntrc' => [$terceiro ? 'required' : 'nullable', 'digits:8'],
            'proprietarioIe' => ['nullable', 'string', 'max:20'],
            'proprietarioUf' => ['nullable', 'size:2'],
            'proprietarioTp' => [$terceiro ? 'required' : 'nullable', 'in:0,1,2'],
            'chassi' => ['nullable', 'size:17'],
            'eixos' => ['nullable', 'integer', 'min:1', 'max:4'],
            'proprietarioCep' => ['nullable', 'digits:8'],
            'proprietarioMunicipioCodigo' => ['nullable', 'digits:7'],
            'proprietarioLogradouro' => ['nullable', 'string', 'max:60'],
            'proprietarioNumero' => ['nullable', 'string', 'max:10'],
            'proprietarioBairro' => ['nullable', 'string', 'max:60'],
        ], ['placa.regex' => 'Placa no formato ABC1D23 ou ABC1234.', 'placa.unique' => 'Já existe veículo com esta placa.'], [
            'tara' => 'tara', 'rodado' => 'tipo de rodado', 'carroceria' => 'carroceria',
            'proprietarioDocumento' => 'CPF/CNPJ do proprietário', 'proprietarioNome' => 'nome do proprietário',
            'proprietarioRntrc' => 'RNTRC do proprietário', 'proprietarioTp' => 'tipo do proprietário',
        ]);

        $dados = [
            'tipo' => $this->tipo,
            'placa' => $this->placa,
            'renavam' => $this->renavam ?: null,
            'uf' => strtoupper($this->uf),
            'tara_kg' => (int) $this->tara,
            'capacidade_kg' => $this->capacidade !== '' ? (int) $this->capacidade : null,
            'tipo_rodado' => $this->tipo === 'tracao' ? $this->rodado : null,
            'tipo_carroceria' => $this->carroceria,
            'proprietario_tipo' => $this->proprietarioTipo,
            'proprietario_documento' => $terceiro ? $this->proprietarioDocumento : null,
            'proprietario_nome' => $terceiro ? mb_strtoupper(trim($this->proprietarioNome)) : null,
            'proprietario_rntrc' => $terceiro ? $this->proprietarioRntrc : null,
            'proprietario_ie' => $terceiro && $this->proprietarioIe !== '' ? $this->proprietarioIe : null,
            'proprietario_uf' => $terceiro && $this->proprietarioUf !== '' ? strtoupper($this->proprietarioUf) : null,
            'proprietario_tp' => $terceiro ? $this->proprietarioTp : null,
            'chassi' => $this->chassi ?: null,
            'eixos' => $this->eixos !== '' ? (int) $this->eixos : null,
            'proprietario_cep' => $terceiro && $this->proprietarioCep !== '' ? $this->proprietarioCep : null,
            'proprietario_municipio_codigo' => $terceiro && $this->proprietarioMunicipioCodigo !== '' ? $this->proprietarioMunicipioCodigo : null,
            'proprietario_logradouro' => $terceiro && $this->proprietarioLogradouro !== '' ? mb_strtoupper(trim($this->proprietarioLogradouro)) : null,
            'proprietario_numero' => $terceiro && $this->proprietarioNumero !== '' ? trim($this->proprietarioNumero) : null,
            'proprietario_bairro' => $terceiro && $this->proprietarioBairro !== '' ? mb_strtoupper(trim($this->proprietarioBairro)) : null,
        ];

        if ($this->editandoId) {
            $this->veiculos->firstWhere('id', $this->editandoId)?->update($dados);
        } else {
            (new Veiculo($dados))->forceFill(['emitente_id' => $this->emitente->getKey()])->save();
        }
        session()->flash('sucesso', 'Veículo '.($this->editandoId ? 'atualizado' : 'cadastrado').'.');
        $this->fecharFormulario();
        unset($this->veiculos, $this->listados);
    }

    public function alternarAtivo(int $id): void
    {
        $this->authorize('transporte.operar');
        $v = $this->veiculos->firstWhere('id', $id);
        abort_if($v === null, 404);
        $v->update(['ativo' => ! $v->ativo]);
        unset($this->veiculos, $this->listados);
    }

    /** Volta tudo ao padrão, menos a busca, que é da lista e não do formulário. */
    private function limparFormulario(): void
    {
        $busca = $this->busca;
        $this->resetErrorBag();
        $this->reset();
        $this->busca = $busca;
        $this->uf = (string) $this->emitente->uf;
    }

    public function render()
    {
        return view('livewire.transporte.veiculos');
    }
}
