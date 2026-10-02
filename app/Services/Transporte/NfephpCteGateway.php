<?php

namespace App\Services\Transporte;

use App\Models\Emitente;
use App\Services\Fiscal\RespostaSefaz;
use Illuminate\Support\Str;
use NFePHP\Common\Exception\ValidatorException;
use NFePHP\CTe\Complements;
use Throwable;

class NfephpCteGateway implements GatewayCte
{
    public function __construct(
        private readonly TransporteToolsFactory $tools,
    ) {}

    public function assinar(Emitente $emitente, string $xml): string
    {
        try {
            return $this->tools->cte($emitente)->signCTe($xml);
        } catch (ValidatorException $e) {
            throw new TransporteException($this->mensagemDeLeiaute('CT-e', $e->getMessage()));
        }
    }

    public function enviar(Emitente $emitente, string $xmlAssinado): RespostaSefaz
    {
        $retorno = $this->tools->cte($emitente)->sefazEnviaCTe($xmlAssinado);
        $resposta = RetornoSefaz::protocolo($retorno, 'protCTe');

        return $this->comProtocolado($resposta, $xmlAssinado, $retorno);
    }

    public function consultar(Emitente $emitente, string $chave, ?string $xmlAssinado = null): RespostaSefaz
    {
        $retorno = $this->tools->cte($emitente)->sefazConsultaChave($chave);
        $resposta = RetornoSefaz::protocolo($retorno, 'protCTe');

        return $xmlAssinado ? $this->comProtocolado($resposta, $xmlAssinado, $retorno) : $resposta;
    }

    public function cancelar(Emitente $emitente, string $chave, string $protocolo, string $justificativa): RespostaSefaz
    {
        return RetornoSefaz::evento($this->tools->cte($emitente)->sefazCancela($chave, $justificativa, $protocolo));
    }

    public function cartaCorrecao(Emitente $emitente, string $chave, array $correcoes, int $sequencia): RespostaSefaz
    {
        return RetornoSefaz::evento($this->tools->cte($emitente)->sefazCCe($chave, $correcoes, $sequencia));
    }

    private function comProtocolado(RespostaSefaz $resposta, string $assinado, string $retorno): RespostaSefaz
    {
        if (! in_array($resposta->cStat, ['100', '150'], true)) {
            return $resposta;
        }
        try {
            $protocolado = Complements::toAuthorize($assinado, $retorno);
        } catch (Throwable) {
            $protocolado = null;
        }

        return new RespostaSefaz('100', $resposta->xMotivo, $resposta->protocolo, $resposta->recibo, $protocolado, $resposta->chave);
    }

    /** Tira os namespaces do erro do XSD e mostra só o campo que falhou. */
    private function mensagemDeLeiaute(string $documento, string $erro): string
    {
        $limpo = Str::squish((string) preg_replace('/\{https?:\/\/[^}]+\}/', '', $erro));
        if (Str::contains(Str::lower($limpo), 'fone')) {
            return "O telefone usado no {$documento} deve ter entre 7 e 12 dígitos, sem o 55 do país.";
        }

        return "O {$documento} não passou na validação do leiaute: ".Str::limit($limpo, 400);
    }
}
