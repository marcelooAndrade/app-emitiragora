<?php

namespace App\Services\Transporte\Efrete;

use App\Enums\Fiscal\Ambiente;
use App\Models\EmitenteTransporte;
use App\Services\Transporte\TransporteException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * O e-Frete inteiro visto daqui: REST para login, cadastros, consulta do
 * transportador, PDF e encerramento; SOAP para abrir e achar a operação.
 *
 * Portado do `EfreteClient` do app-transm, com a mesma trava: só o
 * endereço oficial de homologação e só para emitente em homologação.
 */
class EfreteCliente
{
    public function __construct(private readonly GatewaySoapEfrete $soap) {}

    public function login(EmitenteTransporte $config): string
    {
        try {
            $resposta = $this->http($config)->send('GET', '/services/Logon/Login', ['json' => [
                'Senha' => $config->efrete_senha,
                'Usuario' => $config->efrete_usuario,
                'Integrador' => $config->efrete_integrador,
                'Versao' => 1,
            ]]);
        } catch (ConnectionException) {
            throw new TransporteException('O e-Frete não respondeu ao login. Tente de novo em instantes.');
        }
        $resultado = $this->resultado($resposta, 'entrar no e-Frete');
        $token = trim((string) ($resultado['Token'] ?? $resultado['token'] ?? ''));
        if ($token === '') {
            throw new TransporteException('O e-Frete não devolveu token. Confira usuário, senha e hash do integrador.');
        }

        return $token;
    }

    public function gravarMotorista(EmitenteTransporte $config, array $payload): array
    {
        return $this->post($config, '/services/motoristas/gravar', $payload, 'cadastrar o motorista');
    }

    public function gravarProprietario(EmitenteTransporte $config, array $payload): array
    {
        return $this->post($config, '/services/proprietarios/gravarV2', $payload, 'cadastrar o proprietário');
    }

    public function gravarVeiculo(EmitenteTransporte $config, array $payload): array
    {
        return $this->post($config, '/services/veiculos/gravar', $payload, 'cadastrar o veículo');
    }

    public function consultarTransportador(EmitenteTransporte $config, array $payload): array
    {
        return $this->post($config, '/services/Pef/ConsultaSituacaoTransportador', $payload, 'consultar a situação do transportador');
    }

    public function abrirOperacao(EmitenteTransporte $config, array $payload): array
    {
        $this->travaHomologacao($config);

        return $this->conferir($this->soap->adicionarOperacao($this->notasComoLista($payload), $this->segredos($config)), 'gerar o CIOT');
    }

    /** Acha a operação pelo nosso identificador: SOAP primeiro, REST de reserva. */
    public function acharOperacao(EmitenteTransporte $config, array $payload): array
    {
        $this->travaHomologacao($config);
        $soap = null;
        try {
            $soap = $this->conferir($this->soap->obterCodigoOperacao($payload, $this->segredos($config)), 'consultar o CIOT');
            if (Ciot::numeroEm($soap) !== null) {
                return $soap;
            }
        } catch (TransporteException) {
            // Tenta pelo REST.
        }

        $rest = $this->post($config, '/Services/Pef/ObterCodigoIdentificacaoOperacaoTransportePorIdOperacaoCliente', $payload, 'consultar o CIOT');

        return $soap === null ? $rest : ['soap' => $soap, 'rest' => $rest];
    }

    public function pdfOperacao(EmitenteTransporte $config, array $payload): string
    {
        $resultado = $this->post($config, '/services/Pef/ObterOperacaoTransportePdf', $payload, 'baixar o PDF do CIOT');
        $pdf = base64_decode(trim((string) ($resultado['Pdf'] ?? $resultado['PDF'] ?? $resultado['pdf'] ?? '')), true);
        if ($pdf === false || ! str_starts_with($pdf, '%PDF-')) {
            throw new TransporteException('O e-Frete registrou a operação, mas não devolveu um PDF válido do CIOT.');
        }

        return $pdf;
    }

    public function encerrarOperacao(EmitenteTransporte $config, array $payload): array
    {
        return $this->post($config, '/Services/Pef/EncerrarOperacaoTransporte', $payload, 'encerrar o CIOT');
    }

    /** A mesma trava do Transm: homologação e o endereço oficial dela. */
    public function travaHomologacao(EmitenteTransporte $config): void
    {
        $config->loadMissing('emitente');
        if ($config->emitente?->ambiente !== Ambiente::Homologacao || rtrim((string) config('fiscal.efrete.url'), '/') !== 'https://dev.efrete.com.br') {
            throw new TransporteException('O CIOT pelo e-Frete está liberado só em homologação, como no Transm.');
        }
        if (! $config->temEfrete()) {
            throw new TransporteException('Informe usuário, senha e hash do integrador do e-Frete em Transporte, Configuração.');
        }
    }

    private function post(EmitenteTransporte $config, string $caminho, array $payload, string $acao): array
    {
        try {
            $resposta = $this->http($config)->post($caminho, $payload);
        } catch (ConnectionException) {
            throw new TransporteException("Não foi possível {$acao}: o e-Frete não respondeu. Tente de novo.");
        }

        return $this->resultado($resposta, $acao);
    }

    private function http(EmitenteTransporte $config): PendingRequest
    {
        $this->travaHomologacao($config);

        return Http::baseUrl(rtrim((string) config('fiscal.efrete.url'), '/'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout((int) config('fiscal.efrete.connect_timeout', 10))
            ->timeout((int) config('fiscal.efrete.timeout', 45))
            ->withOptions(['allow_redirects' => false, 'verify' => true]);
    }

    private function resultado(Response $resposta, string $acao): array
    {
        if (! $resposta->successful()) {
            throw new TransporteException("Não foi possível {$acao}: o e-Frete respondeu HTTP {$resposta->status()}.");
        }
        $corpo = $resposta->json();
        if (! is_array($corpo)) {
            throw new TransporteException("Não foi possível {$acao}: o e-Frete devolveu uma resposta que não deu para ler.");
        }

        return $this->conferir($corpo, $acao);
    }

    /** "Sucesso: false" vira a mensagem do e-Frete, e o corpo inteiro vai para o log. */
    private function conferir(array $resultado, string $acao): array
    {
        $resultado = $this->desembrulhar($resultado);
        $sucesso = $resultado['Sucesso'] ?? $resultado['sucesso'] ?? null;
        if ($sucesso === false || $sucesso === 0 || in_array(mb_strtolower(trim((string) $sucesso)), ['0', 'false'], true)) {
            $excecao = $resultado['Excecao'] ?? $resultado['excecao'] ?? [];
            $mensagem = is_array($excecao) ? ($excecao['Mensagem'] ?? $excecao['mensagem'] ?? null) : null;
            $mensagem ??= $resultado['Mensagem'] ?? $resultado['mensagem'] ?? null;
            Log::warning("O e-Frete recusou ao {$acao}.", ['resposta' => Ciot::semSegredos($resultado)]);

            throw new TransporteException(trim((string) $mensagem) !== ''
                ? "O e-Frete recusou: {$mensagem}"
                : "O e-Frete recusou ao {$acao}.");
        }

        return $resultado;
    }

    /** O e-Frete embrulha em "d", em "...Response" ou em "...Result". */
    private function desembrulhar(array $resultado): array
    {
        if (isset($resultado['d'])) {
            $dentro = is_string($resultado['d']) ? json_decode($resultado['d'], true) : $resultado['d'];
            if (is_array($dentro)) {
                return $this->desembrulhar($dentro);
            }
        }
        if (count($resultado) === 1) {
            $chave = (string) array_key_first($resultado);
            if ((str_ends_with($chave, 'Response') || str_ends_with($chave, 'Result')) && is_array($resultado[$chave])) {
                return $this->desembrulhar($resultado[$chave]);
            }
        }

        return $resultado;
    }

    /** O SOAP quer NotaFiscal sempre como lista, mesmo com uma nota só. */
    private function notasComoLista(array $payload): array
    {
        $viagens = $payload['Viagens'] ?? null;
        if (! is_array($viagens)) {
            return $payload;
        }
        $normalizar = function (array $viagem): array {
            $notas = $viagem['NotasFiscais']['NotaFiscal'] ?? null;
            if (is_array($notas)) {
                $viagem['NotasFiscais']['NotaFiscal'] = array_is_list($notas) ? array_values($notas) : [$notas];
            }

            return $viagem;
        };
        $payload['Viagens'] = array_is_list($viagens) ? array_map($normalizar, $viagens) : $normalizar($viagens);

        return $payload;
    }

    /** @return array<int, string> */
    private function segredos(EmitenteTransporte $config): array
    {
        return array_values(array_filter([(string) $config->efrete_senha, (string) $config->efrete_usuario, (string) $config->efrete_integrador]));
    }
}
