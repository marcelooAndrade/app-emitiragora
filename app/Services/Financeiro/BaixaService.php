<?php

namespace App\Services\Financeiro;

use App\Models\ContaFinanceira;
use App\Models\ContaPagar;
use App\Models\FaturaParcela;
use App\Models\MovimentoCaixa;
use App\Services\Assinatura\RenovacaoDeAssinatura;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Baixa de título: marca o título e lança no razão de caixa, numa transação só.
 *
 * As duas coisas precisam acontecer juntas. Título pago sem lançamento some do
 * caixa; lançamento sem título aparece no extrato sem explicação. É o mesmo
 * cuidado que a baixa de estoque tem na emissão.
 *
 * Baixa sem conta bancária é permitida e não mexe em saldo nenhum, seguindo a
 * regra da origem: o título fecha, e o painel conta quantos ficaram assim, que
 * é o número que denuncia conciliação pendente.
 *
 * Reabrir é estorno, nunca apagamento: o projeto Marcelo Andrade apaga o
 * lançamento do caixa ao reabrir, e aqui o razão é imutável. Entra um
 * lançamento contrário apontando para o original, e o título volta a pendente.
 */
class BaixaService
{
    public function __construct(private readonly RenovacaoDeAssinatura $assinatura) {}

    public function pagar(ContaPagar $titulo, ?ContaFinanceira $conta, ?DateTimeInterface $quando = null): ContaPagar
    {
        return $this->baixar(
            titulo: $titulo,
            conta: $conta,
            quando: $quando,
            emitenteId: (int) $titulo->emitente_id,
            sentido: 'debito',
            origemTipo: 'conta_pagar',
            descricao: $titulo->descricao,
        );
    }

    public function receber(FaturaParcela $parcela, ?ContaFinanceira $conta, ?DateTimeInterface $quando = null): FaturaParcela
    {
        // Parcela de mensalidade paga libera mais um mês para o cliente, na
        // mesma transação: baixa sem renovação, ou o contrário, não existe.
        return DB::transaction(function () use ($parcela, $conta, $quando): FaturaParcela {
            $paga = $this->baixar(
                titulo: $parcela,
                conta: $conta,
                quando: $quando,
                emitenteId: (int) $parcela->fatura->emitente_id,
                sentido: 'credito',
                origemTipo: 'fatura_parcela',
                descricao: $parcela->descricao,
            );

            $this->assinatura->aoReceber($paga);

            return $paga;
        });
    }

    /**
     * Reabre um título pago. Se a baixa lançou no caixa, entra o lançamento
     * contrário, com `origem_tipo` `estorno` e `origem_id` do movimento
     * original: o extrato mostra os dois, e a DRE desconta um do outro.
     */
    public function estornar(ContaPagar|FaturaParcela $titulo, ?DateTimeInterface $quando = null): ContaPagar|FaturaParcela
    {
        if ($titulo->status !== 'pago') {
            throw new RuntimeException('Só um título pago pode ser reaberto.');
        }

        $origemTipo = $titulo instanceof ContaPagar ? 'conta_pagar' : 'fatura_parcela';
        $quando ??= today();

        return DB::transaction(function () use ($titulo, $origemTipo, $quando) {
            $original = MovimentoCaixa::query()
                ->where('origem_tipo', $origemTipo)
                ->where('origem_id', $titulo->getKey())
                ->orderByDesc('id')
                ->first();

            $jaEstornado = $original !== null && MovimentoCaixa::query()
                ->where('origem_tipo', 'estorno')
                ->where('origem_id', $original->getKey())
                ->exists();

            if ($original !== null && ! $jaEstornado) {
                MovimentoCaixa::create([
                    'emitente_id' => $original->emitente_id,
                    'conta_financeira_id' => $original->conta_financeira_id,
                    'user_id' => auth()->id(),
                    'sentido' => $original->sentido === 'credito' ? 'debito' : 'credito',
                    'valor_centavos' => (int) $original->valor_centavos,
                    'descricao' => mb_substr('Estorno: '.$original->descricao, 0, 200),
                    'ocorrido_em' => $quando,
                    'origem_tipo' => 'estorno',
                    'origem_id' => $original->getKey(),
                    'created_at' => now(),
                ]);
            }

            $titulo->forceFill(['status' => 'pendente', 'pago_em' => null, 'conta_financeira_id' => null])->save();

            if ($titulo instanceof FaturaParcela) {
                $this->assinatura->aoEstornar($titulo);
            }

            return $titulo;
        });
    }

    private function baixar(
        ContaPagar|FaturaParcela $titulo,
        ?ContaFinanceira $conta,
        ?DateTimeInterface $quando,
        int $emitenteId,
        string $sentido,
        string $origemTipo,
        string $descricao,
    ): ContaPagar|FaturaParcela {
        if (! $titulo->estaPendente()) {
            throw new RuntimeException(
                "Este título já está {$titulo->status} e não pode ser baixado de novo."
            );
        }

        // O projeto usa datas imutáveis, então o tipo aceito é a interface e
        // não uma implementação específica.
        $quando ??= today();

        return DB::transaction(function () use ($titulo, $conta, $quando, $emitenteId, $sentido, $origemTipo, $descricao) {
            $titulo->forceFill(['status' => 'pago', 'pago_em' => $quando])->save();

            if ($conta !== null) {
                // Conferido depois de marcar, de propósito: a transação desfaz
                // tudo, e o teste prova que o título não fica pago.
                if ((int) $conta->emitente_id !== $emitenteId) {
                    throw new RuntimeException('A conta informada é de outro emitente.');
                }

                MovimentoCaixa::create([
                    'emitente_id' => $emitenteId,
                    'conta_financeira_id' => $conta->getKey(),
                    'user_id' => auth()->id(),
                    'sentido' => $sentido,
                    'valor_centavos' => (int) $titulo->valor_centavos,
                    'descricao' => $descricao,
                    'ocorrido_em' => $quando,
                    'origem_tipo' => $origemTipo,
                    'origem_id' => $titulo->getKey(),
                    'created_at' => now(),
                ]);

                $titulo->forceFill(['conta_financeira_id' => $conta->getKey()])->save();
            }

            return $titulo;
        });
    }
}
