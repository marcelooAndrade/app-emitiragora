<?php

namespace App\Services\Transporte;

use App\Models\Emitente;
use App\Models\RegraIcmsTransporte;

/**
 * Acha a regra de ICMS da prestação e calcula o imposto.
 *
 * A escolha e a conta vieram do `FiscalTaxRuleResolver` do app-transm: a
 * regra mais específica para o par de UFs vence, e a conta é feita em
 * inteiros escalados para não perder centavo em arredondamento de float.
 *
 * Melhoria em relação ao Transm: emitente do Simples Nacional sem regra
 * cadastrada não trava, sai com ICMSSN, que é o que ele usaria de qualquer
 * jeito.
 */
class RegraIcms
{
    public function resolver(Emitente $emitente, string $ufOrigem, string $ufDestino): RegraIcmsTransporte
    {
        $regra = RegraIcmsTransporte::query()
            ->where('emitente_id', $emitente->getKey())
            ->where('ativo', true)
            ->where(fn ($q) => $q->whereNull('uf_origem')->orWhere('uf_origem', $ufOrigem))
            ->where(fn ($q) => $q->whereNull('uf_destino')->orWhere('uf_destino', $ufDestino))
            ->get()
            ->sortBy(fn (RegraIcmsTransporte $r): array => [
                $r->prioridade,
                -collect([$r->uf_origem, $r->uf_destino])->filter()->count(),
                $r->id,
            ])
            ->first();

        if ($regra !== null) {
            return $regra;
        }

        if ($this->simplesNacional($emitente)) {
            return new RegraIcmsTransporte(['nome' => 'Simples Nacional', 'cst' => 'SN']);
        }

        throw new TransporteException(
            "Falta a regra de ICMS para frete de {$ufOrigem} para {$ufDestino}. Cadastre em Transporte, Configuração."
        );
    }

    /**
     * @return array{icms_cst: string, icms_base_centavos: int, icms_aliquota: string, icms_valor_centavos: int, icms_credito_centavos: int}
     */
    public function calcular(RegraIcmsTransporte $regra, int $totalCentavos): array
    {
        if (in_array($regra->cst, ['40', '41', '51', 'SN'], true)) {
            return [
                'icms_cst' => $regra->cst,
                'icms_base_centavos' => 0,
                'icms_aliquota' => '0.0000',
                'icms_valor_centavos' => 0,
                'icms_credito_centavos' => 0,
            ];
        }

        $reducao = $this->escalar((string) $regra->reducao_base, 4);
        $aliquota = $this->escalar((string) $regra->aliquota, 4);
        $base = $this->dividirArredondando($totalCentavos * (1_000_000 - $reducao), 1_000_000);
        $imposto = $this->dividirArredondando($base * $aliquota, 1_000_000);
        $credito = $this->dividirArredondando($totalCentavos * $this->escalar((string) $regra->percentual_credito, 4), 1_000_000);

        return [
            'icms_cst' => $regra->cst,
            'icms_base_centavos' => $base,
            'icms_aliquota' => number_format((float) $regra->aliquota, 4, '.', ''),
            'icms_valor_centavos' => $imposto,
            'icms_credito_centavos' => $credito,
        ];
    }

    private function simplesNacional(Emitente $emitente): bool
    {
        return in_array((string) $emitente->crt, ['1', '2', '4'], true);
    }

    /** "12.5000" com escala 4 vira 125000 (percentual × 10⁴). */
    private function escalar(string $valor, int $escala): int
    {
        $normalizado = str_replace(',', '.', trim($valor));
        [$inteiro, $decimal] = array_pad(explode('.', $normalizado, 2), 2, '');
        $decimal = substr(str_pad($decimal, $escala + 1, '0'), 0, $escala + 1);
        $escalado = ((int) ($inteiro === '' ? '0' : $inteiro) * (10 ** $escala)) + (int) substr($decimal, 0, $escala);
        if ((int) substr($decimal, $escala, 1) >= 5) {
            $escalado++;
        }

        return $escalado;
    }

    private function dividirArredondando(int $valor, int $divisor): int
    {
        return intdiv($valor + intdiv($divisor, 2), $divisor);
    }
}
