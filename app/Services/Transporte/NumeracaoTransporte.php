<?php

namespace App\Services\Transporte;

use App\Models\Emitente;
use App\Models\TransporteSerie;
use Illuminate\Support\Facades\DB;

/**
 * Número do CT-e, do MDF-e e da viagem. Mesma regra do NumeracaoService da
 * NF-e: o número só é consumido na transmissão, com lock pessimista, para
 * não ter buraco nem repetição.
 */
class NumeracaoTransporte
{
    public function proximo(Emitente $emitente, int $modelo, int $serie): int
    {
        return DB::transaction(function () use ($emitente, $modelo, $serie): int {
            $registro = TransporteSerie::query()->firstOrCreate(
                ['emitente_id' => $emitente->getKey(), 'modelo' => $modelo, 'serie' => $serie],
                ['proximo_numero' => 1],
            );
            $registro = TransporteSerie::query()->lockForUpdate()->find($registro->getKey());
            $numero = (int) $registro->proximo_numero;
            $registro->forceFill(['proximo_numero' => $numero + 1])->save();

            return $numero;
        });
    }

    public function previsto(Emitente $emitente, int $modelo, int $serie): int
    {
        return (int) (TransporteSerie::query()
            ->where('emitente_id', $emitente->getKey())
            ->where('modelo', $modelo)
            ->where('serie', $serie)
            ->value('proximo_numero') ?? 1);
    }
}
