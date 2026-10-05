<?php

namespace App\Livewire\Financeiro;

use App\Models\ContaFinanceira;
use App\Models\Emitente;
use App\Models\FaturaParcela;
use App\Services\Financeiro\BaixaService;
use App\Support\EmitenteAtual;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use RuntimeException;

/**
 * A lista de títulos a receber. A fatura nasce em `/faturas`; aqui vive a
 * parcela, que é o que vence, atrasa e é recebida.
 */
#[Layout('components.layouts.fiscal')]
#[Title('Contas a receber')]
class ContasReceber extends Component
{
    public string $situacao = 'pendentes';

    public ?int $contaBaixaId = null;

    public function mount(): void
    {
        abort_unless($this->emitente !== null, 404, 'Nenhum emitente vinculado a este usuário.');
        $this->authorize('financeiro.ver');

        $this->contaBaixaId = $this->contas->first()?->getKey();
    }

    #[Computed]
    public function emitente(): ?Emitente
    {
        return app(EmitenteAtual::class)->resolver();
    }

    #[Computed]
    public function contas(): Collection
    {
        return ContaFinanceira::where('emitente_id', $this->emitente?->getKey())
            ->where('ativo', true)->orderBy('nome')->get();
    }

    /**
     * A lista é de parcelas, não de faturas: a parcela é o título, e é ela que
     * vence, atrasa e é recebida.
     */
    #[Computed]
    public function titulos(): Collection
    {
        return FaturaParcela::query()
            ->whereHas('fatura', fn ($q) => $q->where('status', 'ativa')
                ->where('emitente_id', $this->emitente?->getKey()))
            ->when($this->situacao === 'pendentes', fn ($q) => $q->where('status', 'pendente'))
            ->when($this->situacao === 'recebidos', fn ($q) => $q->where('status', 'pago'))
            ->with('fatura.destinatario')
            // Não é "por vencimento": são seis faixas, portadas da origem.
            ->emOrdemDeCobranca()
            ->limit(300)
            ->get();
    }

    #[Computed]
    public function totalPendenteCentavos(): int
    {
        return (int) FaturaParcela::whereHas('fatura', fn ($q) => $q->where('status', 'ativa')
            ->where('emitente_id', $this->emitente?->getKey()))
            ->where('status', 'pendente')->sum('valor_centavos');
    }

    #[Computed]
    public function totalVencidoCentavos(): int
    {
        return (int) FaturaParcela::whereHas('fatura', fn ($q) => $q->where('status', 'ativa')
            ->where('emitente_id', $this->emitente?->getKey()))
            ->where('status', 'pendente')->whereDate('vencimento', '<', today())->sum('valor_centavos');
    }

    public function baixar(int $id): void
    {
        $this->authorize('financeiro.gerenciar');

        $parcela = $this->parcelaDoEmitente($id);
        $conta = $this->contaBaixaId !== null ? ContaFinanceira::find($this->contaBaixaId) : null;

        try {
            app(BaixaService::class)->receber($parcela, $conta);
        } catch (RuntimeException $e) {
            $this->addError('baixa', $e->getMessage());

            return;
        }

        $this->esquecerTotais();

        session()->flash('sucesso', 'Recebimento registrado.');
    }

    /**
     * Parcela de fatura do emitente em foco. O escopo global já garante o
     * tenant, mas matriz e filial dividem o tenant: sem este filtro, uma
     * filial daria baixa em parcela da matriz.
     */
    private function parcelaDoEmitente(int $id): FaturaParcela
    {
        return FaturaParcela::query()
            ->whereHas('fatura', fn ($q) => $q->where('emitente_id', $this->emitente->getKey()))
            ->findOrFail($id);
    }

    public function render()
    {
        return view('livewire.financeiro.contas-receber');
    }

    /** @return array<int, int> */
    protected function idsDasParcelas(): array
    {
        return $this->titulos->pluck('id')->all();
    }

    private function esquecerTotais(): void
    {
        unset($this->titulos, $this->totalPendenteCentavos, $this->totalVencidoCentavos, $this->contas);
    }
}
