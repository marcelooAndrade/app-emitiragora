<?php

namespace App\Services\Transporte;

use App\Models\Emitente;
use App\Services\Fiscal\CertificateService;
use NFePHP\CTe\Tools as CteTools;
use NFePHP\MDFe\Tools as MdfeTools;

/**
 * Monta o `Tools` da sped-cte e da sped-mdfe para um emitente, com o mesmo
 * certificado que a NF-e já usa (CertificateService). Veio do
 * `NfephpToolsFactory` do app-transm.
 */
class TransporteToolsFactory
{
    public function __construct(
        private readonly CertificateService $certificados,
    ) {}

    public function cte(Emitente $emitente): CteTools
    {
        $tools = new CteTools(
            json_encode($this->config($emitente, '4.00'), JSON_THROW_ON_ERROR),
            $this->certificados->certificado($emitente),
        );
        $tools->model(57);

        return $tools;
    }

    public function mdfe(Emitente $emitente): MdfeTools
    {
        return new MdfeTools(
            json_encode($this->config($emitente, '3.00'), JSON_THROW_ON_ERROR),
            $this->certificados->certificado($emitente),
        );
    }

    /** @return array<string, mixed> */
    private function config(Emitente $emitente, string $versao): array
    {
        return [
            'atualizacao' => now()->format('Y-m-d H:i:s'),
            'tpAmb' => $emitente->ambiente->tpAmb(),
            'razaosocial' => $emitente->razao_social,
            'cnpj' => $emitente->cnpj,
            'siglaUF' => $emitente->uf,
            'schemes' => '',
            'versao' => $versao,
            'proxyConf' => ['proxyIp' => '', 'proxyPort' => '', 'proxyUser' => '', 'proxyPass' => ''],
        ];
    }
}
