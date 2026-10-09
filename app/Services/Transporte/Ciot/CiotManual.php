<?php

namespace App\Services\Transporte\Ciot;

use App\Models\Ciot;
use App\Models\Emitente;
use App\Models\Viagem;
use App\Services\Transporte\TransporteException;

/**
 * Sem integração: a pessoa gera o CIOT fora (no programa gratuito da ANTT ou
 * no portal da empresa de pagamento de frete) e digita na viagem. Sempre
 * disponível, inclusive em produção, e é o plano B de qualquer outra empresa.
 */
class CiotManual implements GatewayCiot
{
    public const PEDIDO = 'Informe o CIOT da viagem em "Informar CIOT gerado fora". Ele é gerado de graça no programa da ANTT ou no portal da sua empresa de pagamento de frete.';

    public function declarar(Ciot $ciot, OperacaoCiot $operacao, array $credenciais): RespostaCiot
    {
        throw new TransporteException(self::PEDIDO);
    }

    public function consultar(Ciot $ciot, OperacaoCiot $operacao, array $credenciais): RespostaCiot
    {
        throw new TransporteException(self::PEDIDO);
    }

    public function cancelar(Ciot $ciot, string $motivo, array $credenciais): RespostaCiot
    {
        return new RespostaCiot('cancelado', mensagem: 'Marcado como cancelado aqui. Cancele também onde o CIOT foi gerado.');
    }

    public function encerrar(Ciot $ciot, array $credenciais): RespostaCiot
    {
        return new RespostaCiot('encerrado', mensagem: 'Marcado como encerrado aqui. Encerre também onde o CIOT foi gerado.');
    }

    public function testarConexao(Emitente $emitente, array $credenciais): RespostaCiot
    {
        return new RespostaCiot('ok', mensagem: 'Não há conexão a testar: o CIOT é digitado na viagem.');
    }

    public function campos(): array
    {
        return [];
    }

    public function pendencias(Viagem $viagem, array $credenciais): array
    {
        return [];
    }
}
