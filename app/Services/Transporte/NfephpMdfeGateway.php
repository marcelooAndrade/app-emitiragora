<?php

namespace App\Services\Transporte;

use App\Models\Emitente;
use App\Services\Fiscal\RespostaSefaz;
use Illuminate\Support\Str;
use NFePHP\Common\Exception\ValidatorException;
use NFePHP\Common\UFList;
use NFePHP\MDFe\Complements;
use Throwable;

class NfephpMdfeGateway implements GatewayMdfe
{
    public function __construct(
        private readonly TransporteToolsFactory $tools,
    ) {}

    public function assinar(Emitente $emitente, string $xml): string
    {
        try {
            return $this->tools->mdfe($emitente)->signMDFe($xml);
        } catch (ValidatorException $e) {
            $limpo = Str::squish((string) preg_replace('/\{https?:\/\/[^}]+\}/', '', $e->getMessage()));

            throw new TransporteException('O MDF-e não passou na validação do leiaute: '.Str::limit($limpo, 400));
        }
    }

    public function enviar(Emitente $emitente, string $xmlAssinado): RespostaSefaz
    {
        $retorno = $this->tools->mdfe($emitente)->sefazEnviaLote([$xmlAssinado], (string) now()->format('ymdHis'), 1);

        return $this->comProtocolado(RetornoSefaz::protocolo($retorno, 'protMDFe'), $xmlAssinado, $retorno);
    }

    public function consultar(Emitente $emitente, string $chave, ?string $xmlAssinado = null): RespostaSefaz
    {
        $retorno = $this->tools->mdfe($emitente)->sefazConsultaChave($chave);
        $resposta = RetornoSefaz::protocolo($retorno, 'protMDFe');

        return $xmlAssinado ? $this->comProtocolado($resposta, $xmlAssinado, $retorno) : $resposta;
    }

    public function encerrar(Emitente $emitente, string $chave, string $protocolo, string $uf, string $municipioCodigo, string $data): RespostaSefaz
    {
        return RetornoSefaz::evento($this->tools->mdfe($emitente)->sefazEncerra(
            $chave,
            $protocolo,
            (string) UFList::getCodeByUF($uf),
            $municipioCodigo,
            $data,
        ));
    }

    public function cancelar(Emitente $emitente, string $chave, string $protocolo, string $justificativa): RespostaSefaz
    {
        return RetornoSefaz::evento($this->tools->mdfe($emitente)->sefazCancela($chave, $justificativa, $protocolo));
    }

    private function comProtocolado(RespostaSefaz $resposta, string $assinado, string $retorno): RespostaSefaz
    {
        if ($resposta->cStat !== '100') {
            return $resposta;
        }
        try {
            $protocolado = Complements::toAuthorize($assinado, $retorno);
        } catch (Throwable) {
            $protocolado = null;
        }

        return new RespostaSefaz('100', $resposta->xMotivo, $resposta->protocolo, $resposta->recibo, $protocolado, $resposta->chave);
    }
}
