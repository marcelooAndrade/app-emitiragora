<?php

namespace App\Livewire\Financeiro;

use App\Models\CentroCusto;
use App\Models\Emitente;
use App\Models\Fatura;
use App\Models\FaturaParcela;
use App\Models\Pessoa;
use App\Services\Assinatura\RenovacaoDeAssinatura;
use App\Support\Dinheiro;
use App\Support\EmitenteAtual;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * As faturas, uma por acordo com o cliente. É aqui que a fatura nasce, e
 * dela saem as parcelas que Contas a receber lista.
 */
#[Layout('components.layouts.fiscal')]
#[Title('Faturas')]
class Faturas extends Component
{
    public bool $formularioAberto = false;

    public string $titulo = '';

    public ?int $pessoaId = null;

    public ?int $centroCustoId = null;

    /** Fatura de mensalidade do EmitirAgora: só a empresa que cobra o EmitirAgora vê a opção. */
    public bool $mensalidade = false;

    public string $observacoes = '';

    public string $valor = '';

    public int $parcelas = 1;

    public string $primeiroVencimento = '';

    /**
     * As parcelas, uma por linha, com valor e vencimento próprios.
     *
     * É assim que a origem trabalha, e é assim que negociação funciona: entrada
     * de R$ 500 e o saldo em duas, com datas que não caem de mês em mês. A
     * divisão automática é atalho para o caso comum, não a única forma.
     *
     * @var array<int, array{descricao: string, valor: string, vencimento: string}>
     */
    public array $linhas = [];

    public function mount(): void
    {
        abort_unless($this->emitente !== null, 404, 'Nenhum emitente vinculado a este usuário.');
        $this->authorize('financeiro.ver');

        $this->primeiroVencimento = today()->toDateString();
    }

    #[Computed]
    public function emitente(): ?Emitente
    {
        return app(EmitenteAtual::class)->resolver();
    }

    #[Computed]
    public function cobraEmitirAgora(): bool
    {
        return RenovacaoDeAssinatura::emiteCobranca($this->emitente);
    }

    #[Computed]
    public function clientes(): Collection
    {
        return Pessoa::where('emitente_id', $this->emitente?->getKey())->where('e_cliente', true)->orderBy('razao_social')->limit(500)->get();
    }

    /** Só os centros de receita que recebem lançamento: folhas, ativas. */
    #[Computed]
    public function centrosReceita(): Collection
    {
        return CentroCusto::where('emitente_id', $this->emitente?->getKey())
            ->where('natureza', 'receita')->where('grupo', false)
            ->where('ativo', true)->orderBy('codigo')->get();
    }

    /** Mais recente primeiro. Com parcelas, para os totais na lista. */
    #[Computed]
    public function faturas(): Collection
    {
        return Fatura::query()
            ->where('emitente_id', $this->emitente?->getKey())
            ->with(['destinatario', 'centroCusto', 'parcelas'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get();
    }

    /** Preenche as linhas a partir do total, do número de parcelas e da data. */
    public function gerarLinhas(): void
    {
        $centavos = Dinheiro::emCentavos($this->valor);

        if ($centavos <= 0 || $this->parcelas < 1) {
            $this->addError('valor', 'Informe o total e o número de parcelas.');

            return;
        }

        $primeiro = Carbon::parse($this->primeiroVencimento ?: today()->toDateString());

        $this->linhas = [];

        foreach ($this->dividir($centavos, $this->parcelas) as $i => $valorParcela) {
            $this->linhas[] = [
                'descricao' => $this->parcelas > 1
                    ? sprintf('%s, parcela %d de %d', $this->titulo ?: 'Parcela', $i + 1, $this->parcelas)
                    : ($this->titulo ?: 'Parcela'),
                'valor' => Dinheiro::formatar($valorParcela),
                'vencimento' => $primeiro->copy()->addMonthsNoOverflow($i)->toDateString(),
            ];
        }
    }

    public function adicionarLinha(): void
    {
        $ultima = end($this->linhas) ?: null;

        $this->linhas[] = [
            'descricao' => $this->titulo ?: 'Parcela',
            'valor' => '',
            'vencimento' => $ultima
                ? Carbon::parse($ultima['vencimento'])->addMonthNoOverflow()->toDateString()
                : today()->toDateString(),
        ];
    }

    public function removerLinha(int $indice): void
    {
        unset($this->linhas[$indice]);
        $this->linhas = array_values($this->linhas);
    }

    public function lancar(): void
    {
        $this->authorize('financeiro.gerenciar');

        // Centro de receita é obrigatório para quem tem plano de contas. Quem
        // ainda não montou o plano não fica travado sem cobrar.
        $this->validate([
            'titulo' => ['required', 'string', 'max:160'],
            'pessoaId' => ['nullable', 'integer'],
            'centroCustoId' => [$this->centrosReceita->isEmpty() ? 'nullable' : 'required', 'integer', 'in:'.$this->centrosReceita->pluck('id')->implode(',')],
            'observacoes' => ['nullable', 'string', 'max:1000'],
        ], ['centroCustoId.required' => 'Escolha o centro de receita.', 'centroCustoId.in' => 'Escolha o centro de receita.'], ['titulo' => 'título']);

        // Caminho rápido: quem preencheu total e parcelas e mandou lançar não
        // precisa passar pelo botão de gerar.
        if ($this->linhas === []) {
            $this->gerarLinhas();
        }

        // Mensalidade sem conta do EmitirAgora com o CNPJ do cliente seria
        // cobrada e não liberaria nada: melhor avisar agora.
        if ($this->mensalidade && $this->cobraEmitirAgora) {
            $documento = $this->pessoaId === null ? null : $this->clientes->firstWhere('id', $this->pessoaId)?->documento;

            if ($documento === null || RenovacaoDeAssinatura::contaDoCnpj($documento, (int) $this->emitente->tenant_id) === null) {
                $this->addError('pessoaId', 'Nenhuma conta do EmitirAgora usa o CNPJ deste cliente. Escolha o cliente que tem a conta.');

                return;
            }
        }

        $parcelas = $this->parcelasValidadas();

        if ($parcelas === null) {
            return;
        }

        $fatura = DB::transaction(function () use ($parcelas): Fatura {
            $fatura = Fatura::create([
                'emitente_id' => $this->emitente->getKey(),
                'pessoa_id' => $this->pessoaId,
                'centro_custo_id' => $this->centroCustoId,
                'titulo' => $this->titulo,
                'mensalidade' => $this->mensalidade && $this->cobraEmitirAgora,
                'observacoes' => trim($this->observacoes) ?: null,
            ]);

            foreach ($parcelas as $i => $linha) {
                FaturaParcela::create([
                    'fatura_id' => $fatura->getKey(),
                    'numero' => $i + 1,
                    'descricao' => $linha['descricao'],
                    'valor_centavos' => $linha['centavos'],
                    'vencimento' => $linha['vencimento'],
                ])->setRelation('fatura', $fatura)->gerarCobrancaPix();
            }

            return $fatura;
        });

        session()->flash('sucesso', 'Fatura lançada.');

        $this->redirectRoute('faturas.detalhe', ['fatura' => $fatura->getKey()], navigate: true);
    }

    /**
     * Valida linha a linha antes de abrir transação.
     *
     * Uma linha ruim reprova a fatura inteira: meia fatura gravada é pior do
     * que nenhuma, porque o cliente recebe cobrança de parte do combinado.
     *
     * @return array<int, array{descricao: string, centavos: int, vencimento: string}>|null
     */
    private function parcelasValidadas(): ?array
    {
        if ($this->linhas === []) {
            $this->addError('linhas', 'Informe ao menos uma parcela.');

            return null;
        }

        $parcelas = [];

        foreach (array_values($this->linhas) as $i => $linha) {
            $centavos = Dinheiro::emCentavos((string) ($linha['valor'] ?? ''));
            $vencimento = trim((string) ($linha['vencimento'] ?? ''));

            if ($centavos <= 0) {
                $this->addError("linhas.{$i}.valor", 'Valor da parcela precisa ser maior que zero.');
            }

            if ($vencimento === '' || strtotime($vencimento) === false) {
                $this->addError("linhas.{$i}.vencimento", 'Informe o vencimento da parcela.');
            }

            $parcelas[] = [
                'descricao' => trim((string) ($linha['descricao'] ?? '')) ?: $this->titulo,
                'centavos' => $centavos,
                'vencimento' => $vencimento,
            ];
        }

        return $this->getErrorBag()->isEmpty() ? $parcelas : null;
    }

    public function render()
    {
        return view('livewire.financeiro.faturas');
    }

    /**
     * Divide em centavos inteiros e devolve a sobra nas primeiras parcelas.
     *
     * R$ 100 em três não são três de R$ 33,33: isso perde um centavo, e um
     * centavo perdido por venda vira diferença no fim do mês. A primeira
     * parcela leva a sobra, que é o que a praxe comercial faz.
     *
     * @return array<int, int>
     */
    private function dividir(int $centavos, int $partes): array
    {
        $base = intdiv($centavos, $partes);
        $sobra = $centavos % $partes;

        return array_map(
            fn (int $i): int => $base + ($i < $sobra ? 1 : 0),
            range(0, $partes - 1),
        );
    }
}
