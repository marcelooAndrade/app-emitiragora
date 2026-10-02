<?php

namespace App\Services\Transporte\Efrete;

/**
 * As duas chamadas que o e-Frete só aceita por SOAP (contrato PefServiceV2).
 * Interface para os testes trocarem o SOAP de verdade por um roteiro.
 */
interface GatewaySoapEfrete
{
    /**
     * @param  array<int, string>  $segredos  usuário, senha e integrador, para nunca aparecerem em mensagem
     * @return array<string, mixed>
     */
    public function adicionarOperacao(array $payload, array $segredos): array;

    /**
     * @param  array<int, string>  $segredos
     * @return array<string, mixed>
     */
    public function obterCodigoOperacao(array $payload, array $segredos): array;
}
