<?php

namespace App\Services\Transporte\Ciot;

use App\Models\Ciot;
use App\Models\Emitente;
use App\Models\Viagem;
use App\Services\Transporte\TransporteException;

/**
 * Uma empresa que gera CIOT (e-Frete, Strada, a própria ANTT...). O sistema
 * monta a operação uma vez só, em `OperacaoCiot`, com os campos do DCS da
 * ANTT; cada empresa só traduz para a API dela. Trocar de empresa é trocar
 * de adaptador, sem mexer na emissão.
 *
 * Erro definitivo (credencial recusada, dado que a empresa não aceita) sai
 * como `TransporteException`. Pedido que pode ter chegado sem resposta volta
 * como `processando`, para a próxima tentativa consultar antes de declarar
 * de novo e não gerar CIOT em dobro.
 */
interface GatewayCiot
{
    /**
     * @param  array<string, mixed>  $credenciais
     *
     * @throws TransporteException
     */
    public function declarar(Ciot $ciot, OperacaoCiot $operacao, array $credenciais): RespostaCiot;

    /** @param  array<string, mixed>  $credenciais */
    public function consultar(Ciot $ciot, OperacaoCiot $operacao, array $credenciais): RespostaCiot;

    /** @param  array<string, mixed>  $credenciais */
    public function cancelar(Ciot $ciot, string $motivo, array $credenciais): RespostaCiot;

    /** @param  array<string, mixed>  $credenciais */
    public function encerrar(Ciot $ciot, array $credenciais): RespostaCiot;

    /**
     * Entra na empresa com as credenciais, para a página dizer se estão certas.
     *
     * @param  array<string, mixed>  $credenciais
     */
    public function testarConexao(Emitente $emitente, array $credenciais): RespostaCiot;

    /**
     * Os campos que a página CIOT pede para esta empresa. `segredo` não volta
     * para a tela depois de salvo.
     *
     * @return array<string, array{rotulo: string, segredo?: bool, tipo?: string, opcoes?: array<string, string>, ajuda?: string}>
     */
    public function campos(): array;

    /**
     * O que impede esta empresa de gerar o CIOT desta viagem, em frases para
     * a lista de pendências.
     *
     * @param  array<string, mixed>  $credenciais
     * @return array<int, string>
     */
    public function pendencias(Viagem $viagem, array $credenciais): array;
}
