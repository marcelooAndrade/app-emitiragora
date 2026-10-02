<?php

namespace App\Models;

use App\Enums\Fiscal\Ambiente;
use App\Enums\Transporte\CteStatus;
use App\Models\Concerns\Auditavel;
use App\Models\Concerns\DoTenantViaEmitente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

/**
 * Conhecimento de Transporte Eletrônico (modelo 57).
 */
class Cte extends Model
{
    use Auditavel, DoTenantViaEmitente;

    public const TOMADORES = [
        '0' => 'Remetente',
        '3' => 'Destinatário',
    ];

    protected $table = 'ctes';

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'rascunho', 'tomador_tipo' => '0'];

    protected function casts(): array
    {
        return [
            'status' => CteStatus::class,
            'ambiente' => Ambiente::class,
            'remetente' => 'array',
            'destinatario' => 'array',
            'serie' => 'integer',
            'numero' => 'integer',
            'peso_kg' => 'decimal:3',
            'valor_carga_centavos' => 'integer',
            'valor_frete_centavos' => 'integer',
            'valor_pedagio_centavos' => 'integer',
            'valor_total_centavos' => 'integer',
            'icms_base_centavos' => 'integer',
            'icms_aliquota' => 'decimal:4',
            'icms_valor_centavos' => 'integer',
            'icms_credito_centavos' => 'integer',
            'emitido_em' => 'datetime',
            'autorizado_em' => 'datetime',
            'cancelado_em' => 'datetime',
        ];
    }

    /**
     * CT-e autorizado é documento fiscal: o que foi para a SEFAZ não muda
     * mais por aqui. Mudança depois disso é carta de correção ou cancelamento.
     */
    protected static function booted(): void
    {
        static::updating(function (Cte $cte): void {
            $original = CteStatus::tryFrom((string) $cte->getRawOriginal('status'));
            if (! in_array($original, [CteStatus::Autorizado, CteStatus::Cancelado, CteStatus::Denegado], true)) {
                return;
            }
            $mudou = array_intersect(array_keys($cte->getDirty()), $cte->camposImutaveis());
            if ($mudou !== []) {
                throw new RuntimeException('CT-e autorizado não pode ser alterado: '.implode(', ', $mudou).'.');
            }
        });
    }

    /** @return array<int, string> */
    public function camposImutaveis(): array
    {
        return [
            'serie', 'numero', 'chave', 'protocolo', 'cfop', 'tomador_tipo', 'remetente', 'destinatario',
            'municipio_inicio_codigo', 'municipio_fim_codigo', 'peso_kg', 'valor_carga_centavos',
            'valor_frete_centavos', 'valor_pedagio_centavos', 'valor_total_centavos',
            'icms_cst', 'icms_base_centavos', 'icms_aliquota', 'icms_valor_centavos', 'ambiente',
        ];
    }

    /** @return BelongsTo<Emitente, $this> */
    public function emitente(): BelongsTo
    {
        return $this->belongsTo(Emitente::class);
    }

    /** @return BelongsTo<Viagem, $this> */
    public function viagem(): BelongsTo
    {
        return $this->belongsTo(Viagem::class);
    }

    /** @return HasMany<ViagemNota, $this> */
    public function notas(): HasMany
    {
        return $this->hasMany(ViagemNota::class)->orderBy('id');
    }

    /** @return HasMany<CteEvento, $this> */
    public function eventos(): HasMany
    {
        return $this->hasMany(CteEvento::class)->orderBy('id');
    }

    /** @return BelongsTo<Fatura, $this> */
    public function fatura(): BelongsTo
    {
        return $this->belongsTo(Fatura::class);
    }

    /** @return BelongsTo<RegraIcmsTransporte, $this> */
    public function regraIcms(): BelongsTo
    {
        return $this->belongsTo(RegraIcmsTransporte::class);
    }

    /** @return BelongsTo<Pessoa, $this> */
    public function tomadorPessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'tomador_pessoa_id');
    }

    /** O participante que paga o frete, no formato de `remetente`/`destinatario`. */
    public function tomador(): array
    {
        return $this->tomador_tipo === '3' ? (array) $this->destinatario : (array) $this->remetente;
    }

    public function numeroFormatado(): string
    {
        return $this->numero === null
            ? 'sem número'
            : str_pad((string) $this->serie, 3, '0', STR_PAD_LEFT).'-'.str_pad((string) $this->numero, 9, '0', STR_PAD_LEFT);
    }
}
