<?php

namespace App\Livewire\Financeiro;

use App\Models\ContaFinanceira;
use App\Models\ContaPagar;
use App\Models\Emitente;
use App\Models\FaturaParcela;
use App\Models\MovimentoCaixa;
use App\Support\Dinheiro;
use App\Support\EmitenteAtual;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Contas bancárias e tesouraria: as contas onde o dinheiro está, o saldo de
 * cada uma (saldo inicial mais razão, nunca gravado), e o extrato de quem
 * quiser abrir uma conta específica.
 *
 * O ajuste manual existe para o lançamento que não nasce de título nenhum:
 * um saque, uma taxa de banco, uma transferência entre contas. Vai para o
 * razão com `origem_tipo` `ajuste`, sem `origem_id`, exatamente como as
 * baixas de título vão com `conta_pagar`/`fatura_parcela`.
 */
#[Layout('components.layouts.fiscal')]
#[Title('Contas bancárias')]
class ContasBancarias extends Component
{
    // Padrão de listagem de 09/10/2026: contas numa tabela, formulários em janelas.
    public bool $formularioAberto = false;

    public ?int $editandoId = null;

    public bool $ajusteAberto = false;

    public string $nome = '';

    public string $banco = '';

    public string $tipo = 'corrente';

    public string $saldoInicial = '';

    public bool $padrao = false;

    public ?int $contaSelecionadaId = null;

    public string $ajusteSentido = 'debito';

    public string $ajusteValor = '';

    public string $ajusteDescricao = '';

    public string $ajusteOcorridoEm = '';

    public function mount(): void
    {
        abort_unless($this->emitente !== null, 404, 'Nenhum emitente vinculado a este usuário.');
        $this->authorize('financeiro.ver');

        $this->ajusteOcorridoEm = today()->toDateString();
        $this->contaSelecionadaId = $this->contas->first()?->getKey();
    }

    #[Computed]
    public function emitente(): ?Emitente
    {
        return app(EmitenteAtual::class)->resolver();
    }

    /** @return Collection<int, ContaFinanceira> */
    #[Computed]
    public function contas(): Collection
    {
        return ContaFinanceira::where('emitente_id', $this->emitente?->getKey())
            ->orderByDesc('ativo')->orderBy('nome')->get();
    }

    #[Computed]
    public function saldoTotalCentavos(): int
    {
        return (int) $this->contas->sum(fn (ContaFinanceira $c): int => $c->saldoCentavos());
    }

    #[Computed]
    public function contaSelecionada(): ?ContaFinanceira
    {
        return $this->contas->firstWhere('id', $this->contaSelecionadaId);
    }

    /** @return Collection<int, MovimentoCaixa> */
    #[Computed]
    public function movimentos(): Collection
    {
        if ($this->contaSelecionadaId === null) {
            return new Collection;
        }

        return MovimentoCaixa::where('conta_financeira_id', $this->contaSelecionadaId)
            ->orderByDesc('ocorrido_em')
            ->orderByDesc('id')
            ->limit(50)
            ->get();
    }

    public function selecionar(int $contaId): void
    {
        $this->contaSelecionadaId = $contaId;
        unset($this->movimentos);
    }

    public function novaConta(): void
    {
        $this->authorize('financeiro.gerenciar');
        $this->limparConta();
        $this->formularioAberto = true;
    }

    public function editarConta(int $contaId): void
    {
        $this->authorize('financeiro.gerenciar');
        $conta = ContaFinanceira::where('emitente_id', $this->emitente?->getKey())->findOrFail($contaId);
        $this->limparConta();
        $this->editandoId = $conta->id;
        $this->nome = $conta->nome;
        $this->banco = (string) $conta->banco;
        $this->tipo = $conta->tipo;
        $this->saldoInicial = Dinheiro::formatar((int) $conta->saldo_inicial_centavos);
        $this->padrao = (bool) $conta->padrao;
        $this->formularioAberto = true;
    }

    public function fecharFormulario(): void
    {
        $this->limparConta();
        $this->formularioAberto = false;
    }

    /** Conta com lançamento não muda de saldo inicial: mudaria todo o extrato dela. */
    #[Computed]
    public function editandoTemMovimento(): bool
    {
        return $this->editandoId !== null && MovimentoCaixa::where('conta_financeira_id', $this->editandoId)->exists();
    }

    public function salvarConta(): void
    {
        if ($this->editandoId === null) {
            $this->criarConta();

            return;
        }
        $this->authorize('financeiro.gerenciar');
        $dados = $this->validate([
            'nome' => ['required', 'string', 'max:100'],
            'banco' => ['nullable', 'string', 'max:100'],
            'tipo' => ['required', 'in:corrente,poupanca,caixa'],
        ], attributes: ['nome' => 'nome', 'banco' => 'banco', 'tipo' => 'tipo']);
        $conta = ContaFinanceira::where('emitente_id', $this->emitente->getKey())->findOrFail($this->editandoId);
        $conta->update([
            'nome' => $dados['nome'],
            'banco' => $dados['banco'] ?: null,
            'tipo' => $dados['tipo'],
            'padrao' => $this->padrao,
            ...($this->editandoTemMovimento ? [] : ['saldo_inicial_centavos' => Dinheiro::emCentavos($this->saldoInicial)]),
        ]);
        session()->flash('sucesso', "Conta {$conta->nome} atualizada.");
        $this->fecharFormulario();
        unset($this->contas, $this->movimentos, $this->saldoTotalCentavos);
    }

    /**
     * Conta sem lançamento some de vez. A que já tem movimento no caixa, ou
     * que está numa conta a pagar ou a receber, só é inativada: o razão é
     * imutável e não pode ficar apontando para conta apagada.
     */
    public function excluirConta(int $contaId): void
    {
        $this->authorize('financeiro.gerenciar');
        $conta = ContaFinanceira::where('emitente_id', $this->emitente?->getKey())->findOrFail($contaId);
        $usada = MovimentoCaixa::where('conta_financeira_id', $conta->id)->exists()
            || ContaPagar::where('conta_financeira_id', $conta->id)->exists()
            || FaturaParcela::where('conta_financeira_id', $conta->id)->exists();
        if ($usada) {
            $conta->update(['ativo' => false]);
            session()->flash('sucesso', "{$conta->nome} já tem lançamentos: foi inativada.");
        } else {
            $conta->delete();
            session()->flash('sucesso', "{$conta->nome} excluída.");
            if ($this->contaSelecionadaId === $conta->id) {
                $this->contaSelecionadaId = null;
            }
        }
        unset($this->contas, $this->movimentos, $this->saldoTotalCentavos, $this->contaSelecionada);
    }

    public function abrirAjuste(?int $contaId = null): void
    {
        $this->authorize('financeiro.gerenciar');
        if ($contaId !== null) {
            $this->selecionar($contaId);
        }
        $this->resetErrorBag();
        $this->reset(['ajusteValor', 'ajusteDescricao', 'ajusteSentido']);
        $this->ajusteOcorridoEm = today()->toDateString();
        $this->ajusteAberto = true;
    }

    public function fecharAjuste(): void
    {
        $this->resetErrorBag();
        $this->ajusteAberto = false;
    }

    public function criarConta(): void
    {
        $this->authorize('financeiro.gerenciar');

        $dados = $this->validate([
            'nome' => ['required', 'string', 'max:100'],
            'banco' => ['nullable', 'string', 'max:100'],
            'tipo' => ['required', 'in:corrente,poupanca,caixa'],
        ], attributes: ['nome' => 'nome', 'banco' => 'banco', 'tipo' => 'tipo']);

        $conta = ContaFinanceira::create([
            'emitente_id' => $this->emitente->getKey(),
            'nome' => $dados['nome'],
            'banco' => $dados['banco'] ?: null,
            'tipo' => $dados['tipo'],
            'saldo_inicial_centavos' => Dinheiro::emCentavos($this->saldoInicial),
            'padrao' => $this->padrao,
        ]);

        $this->fecharFormulario();
        $this->contaSelecionadaId = $conta->getKey();
        session()->flash('sucesso', "Conta {$conta->nome} criada.");

        unset($this->contas, $this->movimentos, $this->saldoTotalCentavos, $this->contaSelecionada);
    }

    public function alternarAtiva(int $contaId): void
    {
        $this->authorize('financeiro.gerenciar');

        $conta = ContaFinanceira::where('emitente_id', $this->emitente?->getKey())->findOrFail($contaId);
        $conta->update(['ativo' => ! $conta->ativo]);

        unset($this->contas);
    }

    public function lancarAjuste(): void
    {
        $this->authorize('financeiro.gerenciar');

        $conta = $this->contaSelecionada;

        if ($conta === null) {
            $this->addError('ajusteConta', 'Selecione uma conta para lançar o ajuste.');

            return;
        }

        $this->validate([
            'ajusteSentido' => ['required', 'in:credito,debito'],
            'ajusteDescricao' => ['required', 'string', 'max:200'],
            'ajusteOcorridoEm' => ['required', 'date'],
        ], attributes: ['ajusteDescricao' => 'descrição', 'ajusteOcorridoEm' => 'data']);

        $centavos = Dinheiro::emCentavos($this->ajusteValor);

        if ($centavos <= 0) {
            $this->addError('ajusteValor', 'Informe um valor maior que zero.');

            return;
        }

        MovimentoCaixa::create([
            'emitente_id' => $this->emitente->getKey(),
            'conta_financeira_id' => $conta->getKey(),
            'user_id' => auth()->id(),
            'sentido' => $this->ajusteSentido,
            'valor_centavos' => $centavos,
            'descricao' => $this->ajusteDescricao,
            'ocorrido_em' => $this->ajusteOcorridoEm,
            'origem_tipo' => 'ajuste',
            'origem_id' => null,
            'created_at' => now(),
        ]);

        $this->reset(['ajusteValor', 'ajusteDescricao']);
        $this->ajusteOcorridoEm = today()->toDateString();
        $this->ajusteAberto = false;

        unset($this->contas, $this->movimentos, $this->saldoTotalCentavos);

        session()->flash('sucesso', 'Ajuste lançado.');
    }

    private function limparConta(): void
    {
        $this->resetErrorBag();
        $this->reset(['editandoId', 'nome', 'banco', 'tipo', 'saldoInicial', 'padrao']);
        unset($this->editandoTemMovimento);
    }

    public function render()
    {
        return view('livewire.financeiro.contas-bancarias');
    }
}
