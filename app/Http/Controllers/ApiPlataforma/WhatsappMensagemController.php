<?php

namespace App\Http\Controllers\ApiPlataforma;

use App\Exceptions\UazapiIndisponivel;
use App\Http\Controllers\Controller;
use App\Models\ApiCliente;
use App\Services\Integrations\GatewayDeWhatsapp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsappMensagemController extends Controller
{
    public function __construct(private readonly GatewayDeWhatsapp $gateway) {}

    public function store(Request $request): JsonResponse
    {
        // `numero` é telefone ou id de grupo (120363...@g.us, 23 caracteres),
        // que a uazapi recebe no mesmo campo. O limite de 20 barrava grupo.
        $dados = $request->validate([
            'numero' => ['required', 'string', 'max:64'],
            'texto' => ['required', 'string', 'max:4096'],
        ]);

        /** @var ApiCliente $cliente */
        $cliente = $request->user();
        $instancia = $cliente->whatsappInstancia;

        abort_if($instancia === null, 404, 'Crie a instância primeiro, em POST /whatsapp/v1/instancia.');

        if ($instancia->status !== 'connected') {
            return response()->json([
                'erro' => 'Instância não conectada. Chame POST /whatsapp/v1/instancia/conectar e escaneie o QR code antes de mandar mensagem.',
            ], 422);
        }

        try {
            $enviado = $this->gateway->enviarTexto($instancia->uazapi_token, $dados['numero'], $dados['texto']);
        } catch (UazapiIndisponivel $e) {
            return response()->json(['erro' => $e->getMessage()], 503);
        }

        return response()->json([
            'enviado' => true,
            'id' => $enviado['messageid'] ?? $enviado['id'] ?? null,
        ], 201);
    }
}
