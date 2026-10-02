<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\DoTenantViaEmitente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Contrato do frete com o motorista terceiro (o "contrato do motorista" do
 * Transm): quanto ele recebe, quanto vai adiantado, o que é descontado e o
 * saldo na entrega. Dele saem as duas contas a pagar e o grupo de
 * pagamento do MDF-e.
 */
class ContratoFrete extends Model
{
    use Auditavel, DoTenantViaEmitente;

    protected $table = 'contratos_frete';

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'ativo', 'forma_pagamento' => 'pix'];

    protected function casts(): array
    {
        return [
            'frete_centavos' => 'integer',
            'adiantamento_centavos' => 'integer',
            'imposto_renda_centavos' => 'integer',
            'falta_mercadoria_centavos' => 'integer',
            'seguro_motorista_centavos' => 'integer',
            'seguro_carga_centavos' => 'integer',
            'saldo_centavos' => 'integer',
            'vencimento_saldo' => 'date',
            'emitido_em' => 'datetime',
            'ciot_distancia_km' => 'integer',
            'ciot_tipo_carga' => 'integer',
            'ciot_fim_previsto' => 'date',
            'ciot_resposta' => 'array',
            'ciot_emitido_em' => 'datetime',
            'ciot_encerrado_em' => 'datetime',
        ];
    }

    /** @return BelongsTo<Viagem, $this> */
    public function viagem(): BelongsTo
    {
        return $this->belongsTo(Viagem::class);
    }

    /** @return BelongsTo<Emitente, $this> */
    public function emitente(): BelongsTo
    {
        return $this->belongsTo(Emitente::class);
    }

    /** @return BelongsTo<ContaPagar, $this> */
    public function contaAdiantamento(): BelongsTo
    {
        return $this->belongsTo(ContaPagar::class, 'conta_pagar_adiantamento_id');
    }

    /** @return BelongsTo<ContaPagar, $this> */
    public function contaSaldo(): BelongsTo
    {
        return $this->belongsTo(ContaPagar::class, 'conta_pagar_saldo_id');
    }

    public function descontosCentavos(): int
    {
        return (int) $this->imposto_renda_centavos + (int) $this->falta_mercadoria_centavos
            + (int) $this->seguro_motorista_centavos + (int) $this->seguro_carga_centavos;
    }

    /** CIOT gerado pelo e-Frete (e não digitado): não se edita mais à mão. */
    public function ciotPeloEfrete(): bool
    {
        return in_array($this->ciot_status, ['registrado', 'encerrado'], true);
    }

    /** TAC (autônomo, agregado ou independente): o CIOT é obrigatório. */
    public function exigeCiot(): bool
    {
        return in_array($this->contratado_tp, ['0', '1'], true);
    }
}
