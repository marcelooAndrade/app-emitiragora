<?php

namespace App\Services\Transporte;

use App\Models\Emitente;
use App\Services\Fiscal\RespostaSefaz;

/**
 * Comunicação do CT-e com a SEFAZ, atrás de interface pelo mesmo motivo do
 * `SefazGateway` da NF-e: o transmissor testa sem certificado e sem rede.
 */
interface GatewayCte
{
    /** Assina e valida contra o XSD. Erro de leiaute vira TransporteException. */
    public function assinar(Emitente $emitente, string $xml): string;

    /** Envio síncrono do CT-e 4.00. Autorizado volta com o XML protocolado. */
    public function enviar(Emitente $emitente, string $xmlAssinado): RespostaSefaz;

    /** Situação pela chave. Com o XML assinado, devolve o protocolado se autorizado. */
    public function consultar(Emitente $emitente, string $chave, ?string $xmlAssinado = null): RespostaSefaz;

    public function cancelar(Emitente $emitente, string $chave, string $protocolo, string $justificativa): RespostaSefaz;

    /** @param  array<int, array{grupoAlterado: string, campoAlterado: string, valorAlterado: string}>  $correcoes */
    public function cartaCorrecao(Emitente $emitente, string $chave, array $correcoes, int $sequencia): RespostaSefaz;
}
