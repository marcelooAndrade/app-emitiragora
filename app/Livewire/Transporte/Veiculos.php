<?php

namespace App\Livewire\Transporte;

use App\Models\Emitente;
use App\Models\Veiculo;
use App\Support\Documento;
use App\Support\EmitenteAtual;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Cavalos e carretas. No Transm eram três cadastros (cavalos, carretas e
 * proprietários); aqui é um só, e o proprietário só aparece quando o
 * veículo é de terceiro, que é o único caso em que o MDF-e pede.
 */
#[Layout('components.layouts.fiscal')]
#[Title('Veículos')]
class Veiculos extends Component
{
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
    public function veiculos(): Collection
    {
        return Veiculo::where('emitente_id', $this->emitente->getKey())->orderByDesc('ativo')->orderBy('tipo')->orderBy('placa')->get();
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
    }

    public function novo(): void
    {
        $this->resetErrorBag();
        $this->reset();
        $this->uf = (string) $this->emitente->uf;
    }

    public function salvar(): void
    {
        $this->authorize('transporte.operar');
        $this->placa = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $this->placa));
        $this->proprietarioDocumento = preg_replace('/\D/', '', $this->proprietarioDocumento);
        $this->proprietarioRntrc = preg_replace('/\D/', '', $this->proprietarioRntrc);
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
        ];

        if ($this->editandoId) {
            $this->veiculos->firstWhere('id', $this->editandoId)?->update($dados);
        } else {
            (new Veiculo($dados))->forceFill(['emitente_id' => $this->emitente->getKey()])->save();
        }
        session()->flash('sucesso', 'Veículo '.($this->editandoId ? 'atualizado' : 'cadastrado').'.');
        $this->novo();
        unset($this->veiculos);
    }

    public function alternarAtivo(int $id): void
    {
        $this->authorize('transporte.operar');
        $v = $this->veiculos->firstWhere('id', $id);
        abort_if($v === null, 404);
        $v->update(['ativo' => ! $v->ativo]);
        unset($this->veiculos);
    }

    public function render()
    {
        return view('livewire.transporte.veiculos');
    }
}
