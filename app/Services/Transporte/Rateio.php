<?php

namespace App\Services\Transporte;

/**
 * Divide um valor em centavos entre partes, proporcionalmente aos pesos,
 * sem perder nem inventar centavo: o resto vai para as partes com a maior
 * fração descartada (método do maior resto).
 */
class Rateio
{
    /**
     * @param  array<int|string, float|int>  $pesos
     * @return array<int|string, int>
     */
    public static function dividir(int $totalCentavos, array $pesos): array
    {
        if ($pesos === []) {
            return [];
        }
        $soma = array_sum(array_map(fn ($p): float => max(0.0, (float) $p), $pesos));
        if ($soma <= 0) {
            // Sem critério (tudo zero): divide em partes iguais.
            $pesos = array_map(fn (): float => 1.0, $pesos);
            $soma = (float) count($pesos);
        }

        $partes = [];
        $restos = [];
        $distribuido = 0;
        foreach ($pesos as $chave => $peso) {
            $exato = $totalCentavos * max(0.0, (float) $peso) / $soma;
            $partes[$chave] = (int) floor($exato);
            $restos[$chave] = $exato - floor($exato);
            $distribuido += $partes[$chave];
        }

        arsort($restos);
        $falta = $totalCentavos - $distribuido;
        foreach (array_keys($restos) as $chave) {
            if ($falta <= 0) {
                break;
            }
            $partes[$chave]++;
            $falta--;
        }

        return $partes;
    }
}
