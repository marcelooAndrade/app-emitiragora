<?php

namespace App\Services\Transporte;

use App\Models\Emitente;
use App\Services\Fiscal\RespostaSefaz;

interface GatewayMdfe
{
    public function assinar(Emitente $emitente, string $xml): string;

    /** Envio síncrono de um MDF-e. Autorizado volta com o XML protocolado. */
    public function enviar(Emitente $emitente, string $xmlAssinado): RespostaSefaz;

    public function consultar(Emitente $emitente, string $chave, ?string $xmlAssinado = null): RespostaSefaz;

    public function encerrar(Emitente $emitente, string $chave, string $protocolo, string $uf, string $municipioCodigo, string $data): RespostaSefaz;

    public function cancelar(Emitente $emitente, string $chave, string $protocolo, string $justificativa): RespostaSefaz;
}
