<?php

namespace App\Models;

use App\Enums\Fiscal\Ambiente;
use App\Enums\Transporte\MdfeStatus;
use App\Models\Concerns\Auditavel;
use App\Models\Concerns\DoTenantViaEmitente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Manifesto Eletrônico de Documentos Fiscais (modelo 58), um por viagem.
 */
class Mdfe extends Model
{
    use Auditavel, DoTenantViaEmitente;

    protected $table = 'mdfes';

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'rascunho'];

    protected function casts(): array
    {
        return [
            'status' => MdfeStatus::class,
            'ambiente' => Ambiente::class,
            'percurso_ufs' => 'array',
            'seguro' => 'array',
            'serie' => 'integer',
            'numero' => 'integer',
            'emitido_em' => 'datetime',
            'autorizado_em' => 'datetime',
            'encerrado_em' => 'datetime',
            'cancelado_em' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Mdfe $mdfe): void {
            $original = MdfeStatus::tryFrom((string) $mdfe->getRawOriginal('status'));
            if (! in_array($original, [MdfeStatus::Autorizado, MdfeStatus::Encerrado, MdfeStatus::Cancelado], true)) {
                return;
            }
            $mudou = array_intersect(array_keys($mdfe->getDirty()), ['serie', 'numero', 'chave', 'protocolo', 'uf_inicio', 'uf_fim', 'ambiente']);
            if ($mudou !== []) {
                throw new RuntimeException('MDF-e autorizado não pode ser alterado: '.implode(', ', $mudou).'.');
            }
        });
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

    public function numeroFormatado(): string
    {
        return $this->numero === null
            ? 'sem número'
            : str_pad((string) $this->serie, 3, '0', STR_PAD_LEFT).'-'.str_pad((string) $this->numero, 9, '0', STR_PAD_LEFT);
    }
}
