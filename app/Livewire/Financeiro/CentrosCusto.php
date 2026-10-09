<?php

namespace App\Livewire\Financeiro;

use App\Models\CentroCusto as Centro;
use App\Models\ContaPagar;
use App\Models\Emitente;
use App\Models\Fatura;
use App\Support\EmitenteAtual;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Plano de contas hierárquico, código de até três níveis. Ver `CentroCusto`
 * para o formato do código e a herança de "essencial".
 *
 * Padrão de listagem de 09/10/2026: a lista na largura toda e o formulário
 * numa janela. Na edição só mudam nome, "essencial" e "é grupo": código,
 * natureza e pai definem a posição na árvore e o lado da DRE, e trocá-los
 * depois reclassificaria em silêncio tudo o que já foi lançado.
 */
#[Layout('components.layouts.fiscal')]
#[Title('Centros de custo')]
class CentrosCusto extends Component
{
    public bool $formularioAberto = false;

    public ?int $editandoId = null;

    public string $busca = '';

    public string $codigo = '';

    public string $nome = '';

    public string $natureza = 'despesa';

    public ?int $paiId = null;

    public bool $grupo = false;

    /** '', '1' ou '0': vazio é "herda do pai". */
    public string $essencial = '';

    public function mount(): void
    {
        abort_unless($this->emitente !== null, 404, 'Nenhum emitente vinculado a este usuário.');
        $this->authorize('financeiro.ver');
    }

    #[Computed]
    public function emitente(): ?Emitente
    {
        return app(EmitenteAtual::class)->resolver();
    }

    /** Do mais raso ao mais fundo, por código, que já ordena a hierarquia. */
    #[Computed]
    public function centros(): Collection
    {
        return Centro::where('emitente_id', $this->emitente?->getKey())
            ->with('pai')
            ->orderBy('codigo')
            ->get();
    }

    /** O que a lista mostra: filtrado pela busca por código ou nome. */
    #[Computed]
    public function listados(): Collection
    {
        $termo = mb_strtolower(trim($this->busca));
        if ($termo === '') {
            return $this->centros;
        }

        return $this->centros->filter(fn (Centro $c): bool => str_contains(mb_strtolower($c->nome), $termo)
            || str_contains($c->codigo, $termo))->values();
    }

    public function novo(): void
    {
        $this->authorize('financeiro.gerenciar');
        $this->limparFormulario();
        $this->formularioAberto = true;
    }

    public function editar(int $id): void
    {
        $this->authorize('financeiro.gerenciar');
        $centro = Centro::where('emitente_id', $this->emitente?->getKey())->findOrFail($id);
        $this->limparFormulario();
        $this->editandoId = $centro->id;
        $this->codigo = $centro->codigo;
        $this->nome = $centro->nome;
        $this->natureza = $centro->natureza;
        $this->paiId = $centro->pai_id;
        $this->grupo = (bool) $centro->grupo;
        $this->essencial = $centro->essencial === null ? '' : ($centro->essencial ? '1' : '0');
        $this->formularioAberto = true;
    }

    public function fecharFormulario(): void
    {
        $this->limparFormulario();
        $this->formularioAberto = false;
    }

    public function salvar(): void
    {
        if ($this->editandoId === null) {
            $this->criar();

            return;
        }
        $this->authorize('financeiro.gerenciar');
        $this->validate(['nome' => ['required', 'string', 'max:120']], attributes: ['nome' => 'nome']);
        $centro = Centro::where('emitente_id', $this->emitente->getKey())->findOrFail($this->editandoId);
        if (! $this->grupo && Centro::where('pai_id', $centro->id)->exists()) {
            $this->addError('grupo', 'Este centro tem filhos: continua sendo grupo.');

            return;
        }
        $centro->update([
            'nome' => trim($this->nome),
            'grupo' => $this->grupo,
            'essencial' => $this->essencial === '' ? null : (bool) (int) $this->essencial,
        ]);
        session()->flash('sucesso', "Centro {$centro->codigo} atualizado.");
        $this->fecharFormulario();
        unset($this->centros, $this->listados);
    }

    /**
     * Centro que nunca classificou nada some de vez. O que já está em conta a
     * pagar, fatura, ou que tem filhos, só é inativado: apagar tiraria a
     * classificação do que já foi lançado e da DRE.
     */
    public function excluir(int $id): void
    {
        $this->authorize('financeiro.gerenciar');
        $centro = Centro::where('emitente_id', $this->emitente?->getKey())->findOrFail($id);
        $usado = Centro::where('pai_id', $centro->id)->exists()
            || ContaPagar::where('centro_custo_id', $centro->id)->exists()
            || Fatura::where('centro_custo_id', $centro->id)->exists();
        if ($usado) {
            $centro->update(['ativo' => false]);
            session()->flash('sucesso', "{$centro->codigo} {$centro->nome} já classifica lançamentos ou tem filhos: foi inativado.");
        } else {
            $centro->delete();
            session()->flash('sucesso', "{$centro->codigo} {$centro->nome} excluído.");
        }
        unset($this->centros, $this->listados);
    }

    /** Só os que podem ser pai: grupos, porque um centro folha não recebe filho. */
    #[Computed]
    public function possiveisPais(): Collection
    {
        return $this->centros->where('grupo', true)->where('natureza', $this->natureza);
    }

    public function criar(): void
    {
        $this->authorize('financeiro.gerenciar');

        $codigoFormatado = Centro::formatarCodigo($this->codigo);

        $dados = $this->validate([
            'nome' => ['required', 'string', 'max:120'],
            'natureza' => ['required', 'in:receita,despesa'],
            'paiId' => ['nullable', 'integer'],
        ], attributes: ['nome' => 'nome', 'natureza' => 'natureza']);

        if (! Centro::codigoValido($codigoFormatado)) {
            $this->addError('codigo', 'Código inválido. Use até três grupos de três dígitos, como 001 ou 001.002.');

            return;
        }

        $existe = Centro::where('emitente_id', $this->emitente->getKey())
            ->where('codigo', $codigoFormatado)->exists();

        if ($existe) {
            $this->addError('codigo', 'Já existe um centro de custo com este código.');

            return;
        }

        Centro::create([
            'emitente_id' => $this->emitente->getKey(),
            'pai_id' => $this->paiId,
            'codigo' => $codigoFormatado,
            'nome' => $dados['nome'],
            'natureza' => $dados['natureza'],
            'grupo' => $this->grupo,
            'essencial' => $this->essencial === '' ? null : (bool) (int) $this->essencial,
        ]);

        session()->flash('sucesso', "Centro {$codigoFormatado} criado.");
        $this->fecharFormulario();
        unset($this->centros, $this->listados);
    }

    public function alternarAtivo(int $centroId): void
    {
        $this->authorize('financeiro.gerenciar');

        $centro = Centro::where('emitente_id', $this->emitente?->getKey())->findOrFail($centroId);
        $centro->update(['ativo' => ! $centro->ativo]);

        unset($this->centros, $this->listados);
    }

    private function limparFormulario(): void
    {
        $this->resetErrorBag();
        $this->reset(['editandoId', 'codigo', 'nome', 'natureza', 'paiId', 'grupo', 'essencial']);
    }

    public function render()
    {
        return view('livewire.financeiro.centros-custo');
    }
}
