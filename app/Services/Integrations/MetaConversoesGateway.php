<?php

namespace App\Services\Integrations;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MetaConversoesGateway implements GatewayDeConversoes
{
    private const VERSAO_API = 'v21.0';

    public function enviar(array $evento): void
    {
        $pixelId = (string) config('integracao.meta.pixel_id');
        $token = (string) config('integracao.meta.access_token');

        // Sem configuração o envio simplesmente não acontece, mesma regra do
        // AdminPessoalGateway: desenvolvimento e teste ficam sem chamada
        // externa, sem precisar de if espalhado em quem chama.
        if ($pixelId === '' || $token === '') {
            Log::info('Envio ao Meta desligado ou sem credencial.', ['evento' => $evento['event_name'] ?? null]);

            return;
        }

        $endpoint = 'https://graph.facebook.com/'.self::VERSAO_API."/{$pixelId}/events";

        $corpo = ['access_token' => $token, 'data' => [$evento]];

        if (filled($codigoDeTeste = config('integracao.meta.test_event_code'))) {
            $corpo['test_event_code'] = $codigoDeTeste;
        }

        $resposta = Http::timeout(15)
            ->retry(2, 1000, throw: false)
            ->post($endpoint, $corpo);

        if ($resposta->failed()) {
            // Lançar é de propósito: quem chama é um job com tentativas, e a
            // falha precisa chegar nele para a fila tentar de novo.
            throw new RuntimeException(
                'O Meta recusou o evento de conversão. Status '.$resposta->status()
            );
        }
    }
}
