<?php

namespace App\Services\Transporte;

use App\Services\Fiscal\RespostaSefaz;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Lê o retorno da SEFAZ do CT-e e do MDF-e para o mesmo `RespostaSefaz` da
 * NF-e. Por DOM e `local-name()`, como o `FiscalXmlResponseReader` do Transm:
 * o retorno muda de envelope conforme o serviço, mas o protocolo (infProt) e
 * o evento (infEvento) têm sempre os mesmos campos.
 */
class RetornoSefaz
{
    public static function protocolo(string $xml, string $tagProtocolo, ?string $xmlProtocolado = null): RespostaSefaz
    {
        [$xpath, $raiz] = self::carregar($xml);
        $prot = $xpath->query('//*[local-name()="'.$tagProtocolo.'"]/*[local-name()="infProt"]')->item(0);
        $no = $prot instanceof DOMElement ? $prot : $raiz;

        return new RespostaSefaz(
            cStat: self::valor($xpath, $no, 'cStat') ?? '999',
            xMotivo: self::valor($xpath, $no, 'xMotivo') ?? 'Retorno não reconhecido',
            protocolo: self::valor($xpath, $no, 'nProt'),
            recibo: self::valor($xpath, $raiz, 'nRec'),
            xmlProtocolado: $xmlProtocolado,
            chave: self::valor($xpath, $no, 'chCTe') ?? self::valor($xpath, $no, 'chMDFe'),
        );
    }

    public static function evento(string $xml): RespostaSefaz
    {
        [$xpath, $raiz] = self::carregar($xml);
        $evento = $xpath->query('//*[local-name()="infEvento"]')->item(0);
        $no = $evento instanceof DOMElement ? $evento : $raiz;
        $motivo = self::valor($xpath, $no, 'xMotivo') ?? '';
        $protocolo = self::valor($xpath, $no, 'nProt');

        // Evento repetido volta como rejeição, mas com o protocolo do evento
        // original no texto. Para quem pediu o cancelamento de novo depois de
        // um timeout, isso é sucesso, não erro.
        if ($protocolo === null && str_contains(mb_strtolower($motivo), 'duplicidade de evento')
            && preg_match('/nProt\s*:?\s*(\d{15})/i', $motivo, $m) === 1) {
            return new RespostaSefaz('135', $motivo, $m[1]);
        }

        return new RespostaSefaz(
            cStat: self::valor($xpath, $no, 'cStat') ?? '999',
            xMotivo: $motivo,
            protocolo: $protocolo,
        );
    }

    /** cStat de evento registrado (cancelamento, CC-e, encerramento). */
    public static function eventoAceito(RespostaSefaz $resposta): bool
    {
        return in_array($resposta->cStat, ['135', '136', '155'], true);
    }

    /** @return array{0: DOMXPath, 1: DOMElement} */
    private static function carregar(string $xml): array
    {
        $documento = new DOMDocument;
        $anterior = libxml_use_internal_errors(true);
        $carregou = trim($xml) !== '' && $documento->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);
        if (! $carregou || ! $documento->documentElement) {
            throw new TransporteException('A SEFAZ devolveu uma resposta vazia ou inválida. Consulte o documento antes de reenviar.');
        }

        return [new DOMXPath($documento), $documento->documentElement];
    }

    private static function valor(DOMXPath $xpath, DOMElement $contexto, string $tag): ?string
    {
        $valor = trim((string) $xpath->query('.//*[local-name()="'.$tag.'"]', $contexto)->item(0)?->nodeValue);

        return $valor !== '' ? $valor : null;
    }
}
