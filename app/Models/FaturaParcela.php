<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Support\Pix;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * O título de contas a receber.
 *
 * Não usa `DoTenantViaEmitente` porque não tem `emitente_id`: o escopo vem da
 * fatura, que já é escopada. Duplicar a coluna criaria duas verdades sobre de
 * quem é a parcela.
 */
class FaturaParcela extends Model
{
    use Auditavel;

    protected $table = 'fatura_parcelas';

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'pendente'];

    protected function casts(): array
    {
        return [
            'valor_centavos' => 'integer',
            'numero' => 'integer',
            'vencimento' => 'date',
            'pago_em' => 'datetime',
        ];
    }

    /**
     * Gera e grava o BR Code desta parcela, se o emitente tiver chave.
     *
     * Grava em vez de calcular na exibição porque o payload é o que o cliente
     * recebeu. Se a chave mudar amanhã, a cobrança já enviada continua valendo
     * o que valia quando foi enviada.
     *
     * Falha de cobrança não derruba o lançamento: o título é o que não pode
     * faltar, e a cobrança é conveniência em cima dele.
     */
    public function gerarCobrancaPix(): void
    {
        $emitente = $this->fatura?->emitente;

        if ($emitente === null || blank($emitente->chave_pix)) {
            return;
        }

        try {
            $this->forceFill(['pix_payload' => Pix::payload(
                chave: (string) $emitente->chave_pix,
                nomeRecebedor: (string) ($emitente->nome_fantasia ?: $emitente->razao_social),
                cidadeRecebedor: (string) $emitente->municipio,
                centavos: (int) $this->valor_centavos,
                identificador: Pix::identificador((string) $this->fatura_id, (int) $this->numero),
            )])->save();
        } catch (InvalidArgumentException) {
            // Chave malformada, cidade vazia: o título fica, a cobrança não.
        }
    }

    /**
     * Ordem de cobrança, portada de `sortReceivables` da origem.
     *
     * Seis faixas, e a direção inverte conforme a faixa:
     *
     * 0. venceu dentro do mês corrente, mais recente primeiro
     * 1. ainda vence neste mês, mais próximo primeiro
     * 2. vencido de meses anteriores, mais recente primeiro
     * 3. mês seguinte em diante, mais próximo primeiro
     * 4. pago
     * 5. cancelado
     *
     * A lógica: o que venceu neste mês é cobrança que ainda dá para salvar, e
     * vem na frente do que ainda vai vencer. O vencido antigo já virou
     * negociação, e por isso cai abaixo da operação do mês.
     *
     * Escrito em SQL, e não ordenado em PHP, porque a lista tem limite: ordenar
     * depois de cortar traria as linhas erradas.
     */
    public function scopeEmOrdemDeCobranca(Builder $query): Builder
    {
        $hoje = today()->toDateString();
        $inicioMes = today()->startOfMonth()->toDateString();
        $fimMes = today()->endOfMonth()->toDateString();

        // Comparação de data direta, sem função de mês: `strftime` é do SQLite
        // e `DATE_FORMAT` do MySQL, e as migrations aqui são portáveis.
        $faixa = "(case
            when status = 'pago' then 4
            when status <> 'pendente' then 5
            when vencimento >= ? and vencimento < ? then 0
            when vencimento >= ? and vencimento <= ? then 1
            when vencimento < ? then 2
            else 3
        end)";

        $ligacoes = [$inicioMes, $hoje, $hoje, $fimMes, $inicioMes];

        return $query
            ->orderByRaw("{$faixa} asc", $ligacoes)
            ->orderByRaw("(case when {$faixa} in (1, 3) then vencimento end) asc", $ligacoes)
            ->orderByRaw("(case when {$faixa} in (1, 3) then null else vencimento end) desc", $ligacoes);
    }

    public function estaPendente(): bool
    {
        return $this->status === 'pendente';
    }

    public function fatura(): BelongsTo
    {
        return $this->belongsTo(Fatura::class);
    }

    public function conta(): BelongsTo
    {
        return $this->belongsTo(ContaFinanceira::class, 'conta_financeira_id');
    }
}
