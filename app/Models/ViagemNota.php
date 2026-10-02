<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Uma NF-e que vai no caminhão. Só os campos que a tela e o agrupamento em
 * CT-e precisam ficam em coluna; o XML inteiro fica no disco `fiscal`, de
 * onde o CT-e e o MDF-e leem o resto.
 *
 * Não tem escopo de tenant próprio: é sempre lida pela viagem, que tem.
 */
class ViagemNota extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'remetente' => 'array',
            'destinatario' => 'array',
            'emitida_em' => 'datetime',
            'valor_centavos' => 'integer',
            'peso_kg' => 'decimal:3',
        ];
    }

    /** @return BelongsTo<Viagem, $this> */
    public function viagem(): BelongsTo
    {
        return $this->belongsTo(Viagem::class);
    }

    /** @return BelongsTo<Cte, $this> */
    public function cte(): BelongsTo
    {
        return $this->belongsTo(Cte::class);
    }

    public function xml(): string
    {
        return Storage::disk('fiscal')->get($this->xml_path);
    }

    /**
     * Chave do agrupamento em CT-e. A SEFAZ aceita várias NF-e num CT-e só
     * quando remetente, destinatário, origem e destino coincidem (regra que o
     * Transm já cobrava em validateCompatibleDocuments).
     */
    public function grupoCte(): string
    {
        return implode('|', [
            $this->remetente['documento'] ?? '',
            $this->destinatario['documento'] ?? '',
            $this->municipio_origem_codigo,
            $this->municipio_destino_codigo,
        ]);
    }
}
