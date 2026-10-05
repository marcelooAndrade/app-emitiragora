<?php

namespace App\Livewire\Financeiro;

use App\Models\CentroCusto;
use App\Models\ContaFinanceira;
use App\Models\Emitente;
use App\Models\Fatura;
use App\Models\FaturaParcela;
use App\Models\Pessoa;
use App\Services\Financeiro\BaixaService;
use App\Support\Dinheiro;
use App\Support\EmitenteAtual;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use RuntimeException;

/**
 * Uma fatura por inteiro: o link que vai para o cliente, os totais e cada
 * parcela com o que se faz com ela (receber, reabrir, mudar o vencimento,
 * emitir a NFS-e). Portado de `InvoiceDetails.tsx` do projeto Marcelo Andrade.
 */
#[Layout('components.layouts.fiscal')]
#[Title('Fatura')]
class FaturaDetalhe extends Component
{
    public int $faturaId;

    public ?int $contaBaixaId = null;

    /** Parcela com o vencimento em edição, uma por vez. */
    public ?int $vencimentoEmEdicaoDe = null;

    public string $novoVencimento = '';

    public bool $editando = false;

    public string $edTitulo = '';

    public ?int $edPessoaId = null;

    public ?int $edCentroCustoId = null;

    public string $edObservacoes = '';

    /** @var array<int, array{id: int, numero: int, descricao: string, valor: string, vencimento: string, status: string}> */
    public array $edLinhas = [];

    public function mount(int $fatura): void
    {
        abort_unless($this->emitente !== null, 404, 'Nenhum emitente vinculado a este usuário.');
        $this->authorize('financeiro.ver');

        // Por emitente, não só por tenant: matriz não abre fatura da filial.
        $this->faturaId = Fatura::query()
            ->where('emitente_id', $this->emitente->getKey())
            ->findOrFail($fatura)
            ->getKey();

        $this->contaBaixaId = $this->contas->first()?->getKey();
    }

    #[Computed]
    public function emitente(): ?Emitente
    {
        return app(EmitenteAtual::class)->resolver();
    }

    #[Computed]
    public function fatura(): Fatura
    {
        return Fatura::query()
            ->where('emitente_id', $this->emitente?->getKey())
            ->with(['destinatario', 'centroCusto', 'parcelas'])
            ->findOrFail($this->faturaId);
    }

    #[Computed]
    public function linkPublico(): string
    {
        return route('fatura.publica', ['token' => $this->fatura->public_token]);
    }

    /** @return array{total: int, pago: int, emAberto: int} */
    #[Computed]
    public function totais(): array
    {
        $parcelas = $this->fatura->parcelas;

        return [
            'total' => (int) $parcelas->where('status', '!=', 'cancelado')->sum('valor_centavos'),
            'pago' => (int) $parcelas->where('status', 'pago')->sum('valor_centavos'),
            'emAberto' => (int) $parcelas->where('status', 'pendente')->sum('valor_centavos'),
        ];
    }

    #[Computed]
    public function contas(): Collection
    {
        return ContaFinanceira::where('emitente_id', $this->emitente?->getKey())
            ->where('ativo', true)->orderBy('nome')->get();
    }

    #[Computed]
    public function clientes(): Collection
    {
        return Pessoa::where('emitente_id', $this->emitente?->getKey())->where('e_cliente', true)->orderBy('razao_social')->limit(500)->get();
    }

    #[Computed]
    public function centrosReceita(): Collection
    {
        return CentroCusto::where('emitente_id', $this->emitente?->getKey())
            ->where('natureza', 'receita')->where('grupo', false)
            ->where('ativo', true)->orderBy('codigo')->get();
    }

    public function baixar(int $parcelaId): void
    {
        $this->authorize('financeiro.gerenciar');

        $parcela = $this->parcelaDestaFatura($parcelaId);
        $conta = $this->contaBaixaId !== null ? ContaFinanceira::find($this->contaBaixaId) : null;

        try {
            app(BaixaService::class)->receber($parcela, $conta);
        } catch (RuntimeException $e) {
            $this->addError('baixa', $e->getMessage());

            return;
        }

        $this->esquecer();
        session()->flash('sucesso', 'Recebimento registrado.');
    }

    /** Reabrir é estorno no caixa, nunca apagamento. Ver BaixaService. */
    public function reabrir(int $parcelaId): void
    {
        $this->authorize('financeiro.gerenciar');

        try {
            app(BaixaService::class)->estornar($this->parcelaDestaFatura($parcelaId));
        } catch (RuntimeException $e) {
            $this->addError('baixa', $e->getMessage());

            return;
        }

        $this->esquecer();
        session()->flash('sucesso', 'Parcela reaberta. Se havia lançamento no caixa, entrou o estorno.');
    }

    public function iniciarVencimento(int $parcelaId): void
    {
        $this->authorize('financeiro.gerenciar');

        $parcela = $this->parcelaDestaFatura($parcelaId);

        $this->vencimentoEmEdicaoDe = $parcela->getKey();
        $this->novoVencimento = $parcela->vencimento->toDateString();
        $this->resetErrorBag('novoVencimento');
    }

    public function cancelarVencimento(): void
    {
        $this->reset('vencimentoEmEdicaoDe', 'novoVencimento');
    }

    public function salvarVencimento(): void
    {
        $this->authorize('financeiro.gerenciar');

        $this->validate(['novoVencimento' => ['required', 'date']], [], ['novoVencimento' => 'vencimento']);

        $this->parcelaDestaFatura((int) $this->vencimentoEmEdicaoDe)->update(['vencimento' => $this->novoVencimento]);

        $this->cancelarVencimento();
        $this->esquecer();
        session()->flash('sucesso', 'Vencimento alterado.');
    }

    public function abrirEdicao(): void
    {
        $this->authorize('financeiro.gerenciar');

        $fatura = $this->fatura;

        $this->edTitulo = $fatura->titulo;
        $this->edPessoaId = $fatura->pessoa_id;
        $this->edCentroCustoId = $fatura->centro_custo_id;
        $this->edObservacoes = (string) $fatura->observacoes;
        $this->edLinhas = $fatura->parcelas->map(fn (FaturaParcela $p): array => [
            'id' => $p->id,
            'numero' => $p->numero,
            'descricao' => $p->descricao,
            'valor' => Dinheiro::formatar((int) $p->valor_centavos),
            'vencimento' => $p->vencimento->toDateString(),
            'status' => $p->status,
        ])->values()->all();

        $this->editando = true;
        $this->resetErrorBag();
    }

    public function fecharEdicao(): void
    {
        $this->editando = false;
        $this->resetErrorBag();
    }

    /**
     * Quem edita mexe no acordo, não no caixa: o valor de uma parcela já paga
     * não muda por aqui. Reabre-se a parcela, e aí o valor pode mudar.
     */
    public function salvarEdicao(): void
    {
        $this->authorize('financeiro.gerenciar');

        $this->validate([
            'edTitulo' => ['required', 'string', 'max:160'],
            'edPessoaId' => ['nullable', 'integer', 'in:'.$this->clientes->pluck('id')->implode(',')],
            'edCentroCustoId' => [$this->centrosReceita->isEmpty() ? 'nullable' : 'required', 'integer', 'in:'.$this->centrosReceita->pluck('id')->implode(',')],
            'edObservacoes' => ['nullable', 'string', 'max:1000'],
        ], ['edCentroCustoId.required' => 'Escolha o centro de receita.', 'edCentroCustoId.in' => 'Escolha o centro de receita.'], ['edTitulo' => 'título']);

        $fatura = $this->fatura;
        $parcelas = $fatura->parcelas->keyBy('id');
        $novas = [];

        foreach (array_values($this->edLinhas) as $i => $linha) {
            $parcela = $parcelas->get((int) ($linha['id'] ?? 0));

            if ($parcela === null) {
                $this->addError("edLinhas.{$i}.descricao", 'Parcela não pertence a esta fatura.');

                continue;
            }

            $centavos = Dinheiro::emCentavos((string) ($linha['valor'] ?? ''));
            $vencimento = trim((string) ($linha['vencimento'] ?? ''));

            if ($centavos <= 0) {
                $this->addError("edLinhas.{$i}.valor", 'Valor da parcela precisa ser maior que zero.');
            }

            if ($parcela->status === 'pago' && $centavos !== (int) $parcela->valor_centavos) {
                $this->addError("edLinhas.{$i}.valor", 'Parcela paga não muda de valor. Reabra a parcela antes.');
            }

            if ($vencimento === '' || strtotime($vencimento) === false) {
                $this->addError("edLinhas.{$i}.vencimento", 'Informe o vencimento da parcela.');
            }

            $novas[] = ['parcela' => $parcela, 'descricao' => trim((string) ($linha['descricao'] ?? '')) ?: $this->edTitulo, 'centavos' => $centavos, 'vencimento' => $vencimento];
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        DB::transaction(function () use ($fatura, $novas): void {
            $fatura->update([
                'titulo' => $this->edTitulo,
                'pessoa_id' => $this->edPessoaId,
                'centro_custo_id' => $this->edCentroCustoId,
                'observacoes' => trim($this->edObservacoes) ?: null,
            ]);

            foreach ($novas as $nova) {
                /** @var FaturaParcela $parcela */
                $parcela = $nova['parcela'];
                $valorMudou = $nova['centavos'] !== (int) $parcela->valor_centavos;

                $parcela->update([
                    'descricao' => $nova['descricao'],
                    'valor_centavos' => $nova['centavos'],
                    'vencimento' => $nova['vencimento'],
                ]);

                // O código Pix carrega o valor: valor novo, código novo.
                if ($valorMudou && $parcela->status === 'pendente') {
                    $parcela->setRelation('fatura', $fatura)->gerarCobrancaPix();
                }
            }
        });

        $this->fecharEdicao();
        $this->esquecer();
        session()->flash('sucesso', 'Fatura atualizada.');
    }

    public function render()
    {
        return view('livewire.financeiro.fatura-detalhe');
    }

    /** @return array<int, int> */
    protected function idsDasParcelas(): array
    {
        return $this->fatura->parcelas->pluck('id')->all();
    }

    private function parcelaDestaFatura(int $id): FaturaParcela
    {
        return FaturaParcela::query()->where('fatura_id', $this->faturaId)->findOrFail($id);
    }

    private function esquecer(): void
    {
        unset($this->fatura, $this->totais, $this->contas, $this->notasServico);
    }
}
