<?php

namespace App\Services\Transporte\Ciot;

use App\Models\Viagem;
use Carbon\CarbonInterface;

/**
 * A operação de transporte nos campos da declaração do DCS da ANTT
 * (serviço 03, DeclaracaoOperacaoTransporte). Toda empresa de CIOT acaba
 * entregando estes mesmos campos à ANTT, então nenhum adaptador precisa de
 * dado que o sistema não tenha. Montada por `MontadorOperacaoCiot`.
 *
 * Documentos só com dígitos; RNTRC com 9 dígitos (o DCS completa o de 8 com
 * zero à esquerda); valores em centavos.
 */
final class OperacaoCiot
{
    /**
     * @param  array<int, array{placa: string, rntrc: ?string, eixos: ?int, automotor: bool}>  $veiculos
     * @param  array<int, string>  $contratantesFracionada
     * @param  array{tipo: int, credor: string, chave_pix: ?string, banco: ?string, agencia: ?string, conta: ?string, a_prazo: bool, parcelas: array<int, array{numero: int, vencimento: string, valor_centavos: int}>}  $pagamento
     */
    public function __construct(
        public readonly Viagem $viagem,
        /** '1' lotação, '2' fracionada. */
        public readonly string $tipoOperacao,
        public readonly bool $frotaPropria,
        public readonly string $contratadoDocumento,
        public readonly ?string $contratadoRntrc,
        public readonly string $contratanteDocumento,
        public readonly ?string $destinatarioDocumento,
        public readonly int $valorFreteCentavos,
        public readonly CarbonInterface $inicio,
        public readonly CarbonInterface $fim,
        public readonly array $veiculos,
        public readonly ?string $municipioOrigem,
        public readonly ?string $municipioDestino,
        public readonly ?int $distanciaKm,
        /** Os 4 primeiros dígitos do NCM. */
        public readonly string $naturezaCarga,
        public readonly float $pesoKg,
        /** Tabela do DCS, de 1 (granel sólido) a 12 (granel pressurizada). */
        public readonly int $tipoCarga,
        public readonly array $contratantesFracionada,
        public readonly array $pagamento,
        public readonly bool $altoDesempenho,
        public readonly bool $retornoVazio,
        public readonly bool $composicaoVeicular,
    ) {}
}
