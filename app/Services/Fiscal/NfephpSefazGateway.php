<?php

namespace App\Services\Fiscal;

use App\Models\Emitente;
use NFePHP\NFe\Common\Standardize;
use RuntimeException;

/**
 * Consulta real de status da SEFAZ, pela sped-nfe.
 *
 * Fica atrás da interface `SefazGateway` para que o monitor seja testável
 * sem certificado e sem rede.
 */
class NfephpSefazGateway implements SefazGateway
{
    public function __construct(
        private readonly NfephpToolsFactory $tools,
    ) {}

    public function statusServico(Emitente $emitente): RespostaSefaz
    {
        $std = $this->padronizar($this->tools->para($emitente)->sefazStatus());

        return new RespostaSefaz(
            cStat: (string) ($std->cStat ?? '999'),
            xMotivo: (string) ($std->xMotivo ?? ''),
        );
    }

    private function padronizar(string $retorno): object
    {
        if (trim($retorno) === '') {
            throw new RuntimeException('A SEFAZ devolveu resposta vazia.');
        }

        return (new Standardize)->toStd($retorno);
    }
}
