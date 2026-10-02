<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\DoTenantViaEmitente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tributação do ICMS na prestação de transporte, por par de UFs. Portada da
 * `fiscal_regras_imposto` do Transm, sem os grupos de IBS/CBS (o leiaute do
 * CT-e ainda não os exige) e sem o vínculo por tomador, que lá nunca foi usado.
 */
class RegraIcmsTransporte extends Model
{
    use Auditavel, DoTenantViaEmitente;

    public const CSTS = [
        '00' => '00 · Tributação normal',
        '20' => '20 · Com redução de base',
        '40' => '40 · Isenta',
        '41' => '41 · Não tributada',
        '51' => '51 · Diferida',
        '90' => '90 · Outros (ICMS devido à UF de origem)',
        'SN' => 'Simples Nacional',
    ];

    protected $table = 'regras_icms_transporte';

    protected $guarded = ['id', 'emitente_id'];

    protected $attributes = ['prioridade' => 100, 'ativo' => true];

    protected function casts(): array
    {
        return [
            'aliquota' => 'decimal:4',
            'reducao_base' => 'decimal:4',
            'percentual_credito' => 'decimal:4',
            'percurso_ufs' => 'array',
            'prioridade' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    /** @return BelongsTo<Emitente, $this> */
    public function emitente(): BelongsTo
    {
        return $this->belongsTo(Emitente::class);
    }

    public function rotuloRota(): string
    {
        return ($this->uf_origem ?: 'Qualquer UF').' → '.($this->uf_destino ?: 'Qualquer UF');
    }
}
