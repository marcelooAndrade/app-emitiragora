<?php

namespace App\Services\Integrations;

/**
 * Gateway do módulo WhatsApp atrás de interface, mesmo motivo de
 * GatewayDeLeads: testável sem rede.
 *
 * Diferente do GatewayDeLeads, que é disparo em
 * segundo plano (fila, ninguém espera resposta), aqui quem chama é uma
 * requisição HTTP de um cliente da API esperando o retorno na hora — por
 * isso os métodos devolvem dado, e erro vira exceção em vez de log
 * silencioso. Ver UazapiIndisponivel.
 */
interface GatewayDeWhatsapp
{
    /** @return array{id: string, token: string} */
    public function criarInstancia(string $nome): array;

    /** @return array{qrcode: ?string, status: string} */
    public function conectar(string $instanceToken): array;

    /** @return array{status: string, conectado: bool} */
    public function status(string $instanceToken): array;

    /** @return array<string, mixed> */
    public function enviarTexto(string $instanceToken, string $numero, string $texto): array;

    /**
     * Aponta o webhook da instância para a URL de recebimento do Nodo. Só
     * o evento "messages": é só mensagem que o Nodo repassa ao cliente.
     */
    public function configurarWebhook(string $instanceToken, string $urlDoNodo): void;

    /** @return array<int, array<string, mixed>> */
    public function listarGrupos(string $instanceToken): array;
}
