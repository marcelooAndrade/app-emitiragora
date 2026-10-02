<?php

namespace App\Livewire\Transporte;

use App\Models\Emitente;
use App\Models\Motorista;
use App\Support\Documento;
use App\Support\EmitenteAtual;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.fiscal')]
#[Title('Motoristas')]
class Motoristas extends Component
{
    public ?int $editandoId = null;

    public string $nome = '';

    public string $cpf = '';

    public string $cnh = '';

    public string $telefone = '';

    public string $chavePix = '';

    public function mount(): void
    {
        abort_unless($this->emitente !== null, 404, 'Nenhum emitente vinculado a este usuário.');
        $this->authorize('transporte.ver');
    }

    #[Computed]
    public function emitente(): ?Emitente
    {
        return app(EmitenteAtual::class)->resolver();
    }

    #[Computed]
    public function motoristas(): Collection
    {
        return Motorista::where('emitente_id', $this->emitente->getKey())->orderByDesc('ativo')->orderBy('nome')->get();
    }

    public function editar(int $id): void
    {
        $this->authorize('transporte.operar');
        $m = $this->motoristas->firstWhere('id', $id);
        abort_if($m === null, 404);
        $this->resetErrorBag();
        $this->editandoId = $m->id;
        $this->nome = $m->nome;
        $this->cpf = $m->cpf;
        $this->cnh = (string) $m->cnh;
        $this->telefone = (string) $m->telefone;
        $this->chavePix = (string) $m->chave_pix;
    }

    public function novo(): void
    {
        $this->resetErrorBag();
        $this->reset();
    }

    public function salvar(): void
    {
        $this->authorize('transporte.operar');
        $this->cpf = preg_replace('/\D/', '', $this->cpf);
        $this->validate([
            'nome' => ['required', 'string', 'min:3', 'max:60'],
            'cpf' => ['required', 'digits:11', fn ($a, $v, $falhar) => Documento::cpfValido((string) $v) ? null : $falhar('CPF inválido.'),
                Rule::unique('motoristas', 'cpf')->where('emitente_id', $this->emitente->getKey())->ignore($this->editandoId)],
            'cnh' => ['nullable', 'string', 'max:20'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'chavePix' => ['nullable', 'string', 'max:77'],
        ], ['cpf.unique' => 'Já existe motorista com este CPF.'], ['chavePix' => 'chave Pix', 'cpf' => 'CPF']);

        $dados = [
            'nome' => mb_strtoupper(trim($this->nome)),
            'cpf' => $this->cpf,
            'cnh' => $this->cnh ?: null,
            'telefone' => $this->telefone ?: null,
            'chave_pix' => $this->chavePix ?: null,
        ];
        if ($this->editandoId) {
            $this->motoristas->firstWhere('id', $this->editandoId)?->update($dados);
        } else {
            (new Motorista($dados))->forceFill(['emitente_id' => $this->emitente->getKey()])->save();
        }
        session()->flash('sucesso', 'Motorista '.($this->editandoId ? 'atualizado' : 'cadastrado').'.');
        $this->novo();
        unset($this->motoristas);
    }

    public function alternarAtivo(int $id): void
    {
        $this->authorize('transporte.operar');
        $m = $this->motoristas->firstWhere('id', $id);
        abort_if($m === null, 404);
        $m->update(['ativo' => ! $m->ativo]);
        unset($this->motoristas);
    }

    public function render()
    {
        return view('livewire.transporte.motoristas');
    }
}
