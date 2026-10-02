<?php

namespace App\Services\Transporte\Efrete;

use App\Services\Transporte\TransporteException;
use SoapClient;
use SoapFault;
use Throwable;

/**
 * Canal SOAP do e-Frete, portado do `EfreteSoapGateway` do app-transm.
 */
class EfreteSoap implements GatewaySoapEfrete
{
    public function adicionarOperacao(array $payload, array $segredos): array
    {
        return $this->chamar('AdicionarOperacaoTransporte', 'AdicionarOperacaoTransporteRequest', $payload, $segredos, 'gerar o CIOT');
    }

    public function obterCodigoOperacao(array $payload, array $segredos): array
    {
        return $this->chamar(
            'ObterCodigoIdentificacaoOperacaoTransportePorIdOperacaoCliente',
            'ObterCodigoIdentificacaoOperacaoTransportePorIdOperacaoClienteRequest',
            $payload,
            $segredos,
            'consultar o CIOT',
        );
    }

    protected function cliente(): SoapClient
    {
        $wsdl = resource_path('fiscal/efrete/PefServiceV2.wsdl');
        if (! is_file($wsdl)) {
            throw new TransporteException('O contrato SOAP do e-Frete não está no sistema.');
        }

        return new HttpSoapClient(
            $wsdl,
            rtrim((string) config('fiscal.efrete.url'), '/').'/Services/PefServiceV2.asmx',
            (int) config('fiscal.efrete.connect_timeout', 10),
            (int) config('fiscal.efrete.timeout', 45),
        );
    }

    private function chamar(string $metodo, string $chave, array $payload, array $segredos, string $acao): array
    {
        if (! class_exists(SoapClient::class)) {
            throw new TransporteException("A extensão SOAP do PHP precisa estar ligada para {$acao} no e-Frete.");
        }

        try {
            // O cliente nasce aqui dentro: WSDL inválido ou fora do ar lança
            // no construtor e tem de virar mensagem, não erro 500. Objetos em
            // vez de arrays porque o encoder do PHP nem sempre lê chave de
            // array associativo como propriedade do tipo complexo.
            $resposta = $this->cliente()->__soapCall($metodo, [[$chave => $this->objeto($payload)]]);
        } catch (SoapFault $e) {
            report($e);

            throw new TransporteException($this->mensagemFalha($e, $segredos));
        } catch (TransporteException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            throw new TransporteException("Não foi possível {$acao} pelo SOAP do e-Frete: ".str_replace(array_filter($segredos), '[oculto]', $e->getMessage()));
        }

        $resultado = $this->arranjo($resposta);
        if (! is_array($resultado)) {
            throw new TransporteException("O e-Frete devolveu uma resposta SOAP que não deu para ler ao {$acao}.");
        }

        return $resultado;
    }

    private function objeto(mixed $valor): mixed
    {
        if (! is_array($valor)) {
            return $valor;
        }
        $convertido = array_map(fn (mixed $item): mixed => $this->objeto($item), $valor);

        return array_is_list($convertido) ? $convertido : (object) $convertido;
    }

    private function arranjo(mixed $valor): mixed
    {
        if (is_object($valor)) {
            $valor = get_object_vars($valor);
        }

        return is_array($valor) ? array_map(fn (mixed $item): mixed => $this->arranjo($item), $valor) : $valor;
    }

    /** A mensagem útil do .NET fica no fim de "---> ... ---> ...", sem a pilha. */
    private function mensagemFalha(SoapFault $e, array $segredos): string
    {
        $mensagem = str_replace(array_filter($segredos), '[oculto]', strip_tags($e->getMessage()));
        $mensagem = trim((string) preg_replace('/\s+/', ' ', $mensagem));
        if (str_contains($mensagem, '--->')) {
            $partes = preg_split('/\s*--->\s*/', $mensagem) ?: [];
            $mensagem = trim((string) end($partes));
        }
        $mensagem = preg_replace('/^(?:[A-Za-z0-9_.]+Exception:\s*)+/i', '', $mensagem) ?? $mensagem;
        $mensagem = preg_replace('/\s+(?:em|at)\s+(?:Microsoft|System)\..*$/iu', '', $mensagem) ?? $mensagem;

        return $mensagem === ''
            ? 'O e-Frete não gerou o CIOT pelo SOAP. Tente de novo; a operação tem identificador e não duplica.'
            : 'O e-Frete recusou: '.mb_substr($mensagem, 0, 500);
    }
}
