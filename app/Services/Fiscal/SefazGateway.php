<?php

namespace App\Services\Fiscal;

use App\Models\Emitente;

/**
 * Consulta de status do serviço da SEFAZ, atrás de interface.
 *
 * Era a comunicação inteira da NF-e (enviar, consultar, cancelar, carta de
 * correção, inutilizar). Com a NF-e fora do produto em 05/10/2026, sobrou o
 * que o indicador "SEFAZ em operação" usa (ver MonitorSefaz). CT-e e MDF-e
 * têm os gateways deles em Services\Transporte.
 */
interface SefazGateway
{
    public function statusServico(Emitente $emitente): RespostaSefaz;
}
