<?php

namespace App\Services\Transporte\Efrete;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use SoapClient;
use SoapFault;

/**
 * SoapClient que transporta pelo Http do Laravel, com os timeouts e sem
 * seguir redirecionamento. Portado do `EfreteHttpSoapClient` do app-transm.
 */
class HttpSoapClient extends SoapClient
{
    public function __construct(
        string $wsdl,
        private readonly string $endpoint,
        private readonly int $connectTimeout,
        private readonly int $timeout,
    ) {
        parent::__construct($wsdl, [
            'cache_wsdl' => WSDL_CACHE_BOTH,
            'exceptions' => true,
            'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
            'keep_alive' => true,
            'location' => $endpoint,
        ]);
    }

    public function __doRequest(
        string $request,
        string $location,
        string $action,
        int $version,
        bool $oneWay = false,
        ?string $uriParserClass = null,
    ): ?string {
        $contentType = $version === SOAP_1_2
            ? 'application/soap+xml; charset=utf-8; action="'.$action.'"'
            : 'text/xml; charset=utf-8';

        try {
            $resposta = Http::connectTimeout($this->connectTimeout)
                ->timeout($this->timeout)
                ->withOptions(['allow_redirects' => false, 'verify' => true])
                ->withHeaders(['SOAPAction' => '"'.$action.'"'])
                ->withBody($request, $contentType)
                ->post($this->endpoint);
        } catch (ConnectionException) {
            throw new SoapFault('HTTP', 'O e-Frete não respondeu.');
        }

        // Falha SOAP vem com HTTP 500 e um envelope com a mensagem: esse
        // corpo precisa chegar ao SoapClient para virar SoapFault legível.
        if (! $resposta->successful() && preg_match('/<(?:[A-Za-z0-9_-]+:)?Envelope\b[^>]*>/i', $resposta->body()) !== 1) {
            throw new SoapFault('HTTP', "O e-Frete respondeu HTTP {$resposta->status()}.");
        }

        return $oneWay ? null : $resposta->body();
    }
}
