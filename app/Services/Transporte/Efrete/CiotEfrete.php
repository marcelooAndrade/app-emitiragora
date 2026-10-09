<?php

namespace App\Services\Transporte\Efrete;

use App\Models\ContratoFrete;
use App\Models\EmitenteTransporte;
use App\Services\Transporte\Ciot\RespostaCiot;
use App\Services\Transporte\TransporteException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Gera, consulta e encerra o CIOT do contrato de frete no e-Frete.
 *
 * Portado do `FiscalCiotTransmissionService` do app-transm, na mesma ordem:
 * cadastra motorista, proprietário e veículos; confere na ANTT se o
 * transportador e as placas estão ativos; abre a operação; e, se o número
 * não volta na hora, procura pelo identificador da operação (que é fixo por
 * contrato, então tentar de novo nunca duplica o CIOT).
 *
 * Desde 09/10/2026 (CIOT para todos) não grava nada nem trava a viagem:
 * devolve a `RespostaCiot`, e quem grava, trava e decide é o `ServicoCiot`,
 * pelo adaptador `CiotEfreteProvedor`.
 */
class CiotEfrete
{
    public function __construct(
        private readonly EfreteCliente $efrete,
        private readonly PayloadEfrete $payload,
    ) {}

    /** Abre a operação, ou, com `$consultar`, só procura a que já foi pedida. */
    public function gerar(ContratoFrete $contrato, EmitenteTransporte $config, bool $consultar = false): RespostaCiot
    {
        $this->efrete->travaHomologacao($config);
        $this->payload->contexto($contrato, $config);
        if (function_exists('set_time_limit')) {
            // Cadastros, consulta e operação são várias chamadas seguidas.
            @set_time_limit(180);
        }

        $token = $this->efrete->login($config);
        if ($consultar) {
            $resposta = $this->buscar($contrato, $config, $token);
        } else {
            $operacao = $this->payload->operacao($contrato, $config, $token);
            $this->cadastrar($contrato, $config, $token);
            $resposta = $this->abrir($contrato, $config, $token, $operacao);
        }

        [$resposta, $numero] = $this->numero($contrato, $config, $token, $resposta);
        if ($numero === null) {
            return new RespostaCiot('processando', protocolo: Ciot::protocoloEm($resposta), resposta: Ciot::semSegredos($resposta));
        }
        $verificador = Ciot::verificadorEm($resposta);

        return new RespostaCiot(
            'registrado',
            numero: $numero,
            verificador: $verificador,
            protocolo: Ciot::protocoloEm($resposta),
            resposta: Ciot::semSegredos($resposta),
            pdf: $this->pdf($config, $token, $verificador ? "{$numero}/{$verificador}" : $numero),
            responsavel: (string) $contrato->emitente->cnpj,
        );
    }

    public function encerrar(string $numero, ContratoFrete $contrato, EmitenteTransporte $config): RespostaCiot
    {
        $token = $this->efrete->login($config);
        $this->efrete->encerrarOperacao($config, $this->payload->encerramento($contrato, $config, $token, $numero));

        return new RespostaCiot('encerrado');
    }

    /** O PDF é um extra: sem ele o CIOT vale do mesmo jeito. */
    private function pdf(EmitenteTransporte $config, string $token, string $codigo): ?string
    {
        try {
            return $this->efrete->pdfOperacao($config, $this->payload->pdf($config, $token, $codigo));
        } catch (TransporteException $e) {
            report($e);

            return null;
        }
    }

    /** Motorista, depois proprietário e veículo aos pares, depois a ANTT. */
    private function cadastrar(ContratoFrete $contrato, EmitenteTransporte $config, string $token): void
    {
        $this->efrete->gravarMotorista($config, $this->payload->motorista($contrato, $config, $token));
        if (! $this->payload->usaMassaAntt($config)) {
            $donos = $this->payload->proprietarios($contrato, $config, $token);
            foreach ($this->payload->veiculos($contrato, $config, $token) as $i => $veiculo) {
                $this->efrete->gravarProprietario($config, $donos[$i]);
                $this->efrete->gravarVeiculo($config, $veiculo);
            }
        }

        $situacao = $this->efrete->consultarTransportador($config, $this->payload->consultaTransportador($contrato, $config, $token));
        Log::info('e-Frete: situação do transportador.', ['contrato_id' => $contrato->getKey(), 'resposta' => Ciot::semSegredos($situacao)]);
        $this->exigirTransportadorAtivo($situacao);
    }

    /** Se a abertura falha no meio, a operação pode ter sido criada: procura antes de desistir. */
    private function abrir(ContratoFrete $contrato, EmitenteTransporte $config, string $token, array $operacao): array
    {
        try {
            return $this->efrete->abrirOperacao($config, $operacao);
        } catch (TransporteException $falha) {
            try {
                return $this->efrete->acharOperacao($config, $this->payload->busca($contrato, $config, $token));
            } catch (Throwable) {
                $mensagem = mb_strtolower($falha->getMessage());
                if (str_contains($mensagem, 'operação de transporte já cadastrada') || str_contains($mensagem, 'operacao de transporte ja cadastrada')) {
                    return ['Sucesso' => true, 'OperacaoJaCadastrada' => true, 'Mensagem' => $falha->getMessage()];
                }

                throw $falha;
            }
        }
    }

    private function buscar(ContratoFrete $contrato, EmitenteTransporte $config, string $token): array
    {
        try {
            return $this->efrete->acharOperacao($config, $this->payload->busca($contrato, $config, $token));
        } catch (TransporteException $e) {
            return ['consulta' => ['erro' => $e->getMessage()]];
        }
    }

    /** @return array{0: array, 1: ?string} */
    private function numero(ContratoFrete $contrato, EmitenteTransporte $config, string $token, array $resposta): array
    {
        if (($numero = Ciot::numeroEm($resposta)) !== null) {
            return [$resposta, $numero];
        }
        $consultas = [];
        $espera = max(0, (int) config('fiscal.efrete.espera_consulta_ms', 1500));
        for ($i = 1; $i <= max(1, (int) config('fiscal.efrete.consultas', 3)); $i++) {
            if ($i > 1 && $espera > 0) {
                usleep($espera * 1000);
            }
            $consulta = $this->buscar($contrato, $config, $token);
            $consultas[] = $consulta;
            if (($numero = Ciot::numeroEm($consulta)) !== null) {
                return [['emissao' => $resposta, 'consultas' => $consultas], $numero];
            }
        }

        return [['emissao' => $resposta, 'consultas' => $consultas], null];
    }

    /** Portado do `EfreteCarrierResponseValidator`: RNTRC ativo e placas na frota. */
    private function exigirTransportadorAtivo(array $resposta): void
    {
        if ($this->temFalso($resposta, 'RNTRCAtivo')) {
            throw new TransporteException('A ANTT diz que o RNTRC do transportador está inativo.');
        }
        if ($this->temFalso($resposta, 'RNTRCAtivoDataPrevistaFimViagem')) {
            throw new TransporteException('A ANTT diz que o RNTRC do transportador não estará ativo até o fim previsto da viagem.');
        }
        $placas = $this->placasForaDaFrota($resposta);
        if ($placas !== []) {
            throw new TransporteException('A ANTT não encontrou '.implode(', ', $placas).' na frota do RNTRC do proprietário. Confira o RNTRC e o vínculo dos veículos na ANTT.');
        }
        if ($this->temFalso($resposta, 'FazParteDaFrota')) {
            throw new TransporteException('A ANTT diz que um dos veículos não pertence à frota do RNTRC do proprietário.');
        }
    }

    private function placasForaDaFrota(array $resposta): array
    {
        $placas = [];
        foreach ($resposta as $valor) {
            if (! is_array($valor)) {
                continue;
            }
            $frota = $this->campo($valor, 'FazParteDaFrota');
            if ($frota !== null && $this->falso($frota)) {
                $placa = (string) preg_replace('/[^A-Z0-9]/', '', mb_strtoupper((string) $this->campo($valor, 'Placa')));
                if ($placa !== '') {
                    $placas[] = $placa;
                }
            }
            $placas = [...$placas, ...$this->placasForaDaFrota($valor)];
        }

        return array_values(array_unique($placas));
    }

    private function campo(array $valores, string $chave): mixed
    {
        foreach ($valores as $k => $v) {
            if (strcasecmp((string) $k, $chave) === 0) {
                return $v;
            }
        }

        return null;
    }

    private function falso(mixed $valor): bool
    {
        return in_array($valor, [false, 0, '0', 'false', 'False', 'FALSE'], true);
    }

    private function temFalso(array $resposta, string $chave): bool
    {
        foreach ($resposta as $k => $v) {
            if ((strcasecmp((string) $k, $chave) === 0 && $this->falso($v)) || (is_array($v) && $this->temFalso($v, $chave))) {
                return true;
            }
        }

        return false;
    }
}
