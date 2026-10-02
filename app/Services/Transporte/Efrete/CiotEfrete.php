<?php

namespace App\Services\Transporte\Efrete;

use App\Enums\Transporte\MdfeStatus;
use App\Models\ContratoFrete;
use App\Models\EmitenteTransporte;
use App\Models\User;
use App\Services\Transporte\TransporteException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Gera, consulta e encerra o CIOT do contrato de frete no e-Frete.
 *
 * Portado do `FiscalCiotTransmissionService` do app-transm, na mesma ordem:
 * cadastra motorista, proprietário e veículos; confere na ANTT se o
 * transportador e as placas estão ativos; abre a operação; e, se o número
 * não volta na hora, procura pelo identificador da operação (que é fixo por
 * contrato, então tentar de novo nunca duplica o CIOT). O número cai sozinho
 * no contrato e no MDF-e.
 */
class CiotEfrete
{
    public function __construct(
        private readonly EfreteCliente $efrete,
        private readonly PayloadEfrete $payload,
    ) {}

    /**
     * @param  array{distancia_km?: int, embalagem?: string, tipo_carga?: int, fim_previsto?: string}  $dados
     */
    public function gerar(ContratoFrete $contrato, array $dados = [], ?User $user = null): ContratoFrete
    {
        return Cache::lock('transporte:ciot:'.$contrato->getKey(), 120)->block(5, function () use ($contrato, $dados, $user): ContratoFrete {
            $contrato->refresh();
            if (filled($contrato->ciot) && $contrato->ciotPeloEfrete()) {
                return $contrato;
            }
            $this->conferir($contrato);
            if ($dados !== []) {
                $contrato->forceFill(array_filter([
                    'ciot_distancia_km' => isset($dados['distancia_km']) ? (int) $dados['distancia_km'] : null,
                    'ciot_embalagem' => $dados['embalagem'] ?? null,
                    'ciot_tipo_carga' => isset($dados['tipo_carga']) ? (int) $dados['tipo_carga'] : null,
                    'ciot_fim_previsto' => $dados['fim_previsto'] ?? null,
                ], fn (mixed $v): bool => $v !== null && $v !== ''))->save();
            }

            $config = $contrato->emitente->configuracaoTransporte();
            $this->efrete->travaHomologacao($config);
            $this->payload->contexto($contrato, $config);
            if (function_exists('set_time_limit')) {
                // Cadastros, consulta e operação são várias chamadas seguidas.
                @set_time_limit(180);
            }

            $token = $this->efrete->login($config);
            if ($contrato->ciot_status === 'processando') {
                $resposta = $this->buscar($contrato, $config, $token);
            } else {
                $operacao = $this->payload->operacao($contrato, $config, $token);
                $this->cadastrar($contrato, $config, $token);
                $resposta = $this->abrir($contrato, $config, $token, $operacao);
            }

            [$resposta, $numero] = $this->numero($contrato, $config, $token, $resposta);
            if ($numero === null) {
                return $this->processando($contrato, $resposta, $user);
            }

            return $this->registrar($contrato, $config, $token, $resposta, $numero, $user);
        });
    }

    public function encerrar(ContratoFrete $contrato, ?User $user = null): ContratoFrete
    {
        if ($contrato->ciot_status !== 'registrado' || blank($contrato->ciot)) {
            throw new TransporteException('Só um CIOT gerado pelo e-Frete e ainda aberto pode ser encerrado.');
        }
        $config = $contrato->emitente->configuracaoTransporte();
        $token = $this->efrete->login($config);
        $this->efrete->encerrarOperacao($config, $this->payload->encerramento($contrato, $config, $token));
        $contrato->forceFill(['ciot_status' => 'encerrado', 'ciot_encerrado_em' => now()])->save();
        $contrato->viagem->registrar('ciot_encerrado', "CIOT {$contrato->ciot} encerrado no e-Frete.", ['contrato_id' => $contrato->getKey()], $user?->getKey());

        return $contrato;
    }

    private function conferir(ContratoFrete $contrato): void
    {
        $contrato->loadMissing(['viagem.mdfe', 'emitente']);
        if ($contrato->status !== 'ativo') {
            throw new TransporteException('O contrato do frete foi cancelado.');
        }
        if (in_array($contrato->viagem->mdfe?->status, [MdfeStatus::Autorizado, MdfeStatus::Encerrado, MdfeStatus::EmProcessamento], true)) {
            throw new TransporteException('O MDF-e já foi para a SEFAZ: o CIOT precisa sair antes dele.');
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

    private function processando(ContratoFrete $contrato, array $resposta, ?User $user): ContratoFrete
    {
        $primeira = $contrato->ciot_status !== 'processando';
        $contrato->forceFill([
            'ciot_status' => 'processando',
            'ciot_protocolo' => Ciot::protocoloEm($resposta) ?? $contrato->ciot_protocolo,
            'ciot_resposta' => Ciot::semSegredos($resposta),
        ])->save();
        if ($primeira) {
            $contrato->viagem->registrar('ciot_processando', 'O e-Frete aceitou o pedido do CIOT e ainda não devolveu o número. Consulte em instantes.', ['contrato_id' => $contrato->getKey()], $user?->getKey());
        }

        return $contrato;
    }

    private function registrar(ContratoFrete $contrato, EmitenteTransporte $config, string $token, array $resposta, string $numero, ?User $user): ContratoFrete
    {
        $verificador = Ciot::verificadorEm($resposta);
        DB::transaction(function () use ($contrato, $resposta, $numero, $verificador, $user): void {
            $contrato->forceFill([
                'ciot' => $numero,
                'ciot_verificador' => $verificador,
                'ciot_status' => 'registrado',
                'ciot_protocolo' => Ciot::protocoloEm($resposta),
                'ciot_resposta' => Ciot::semSegredos($resposta),
                'ciot_emitido_em' => now(),
            ])->save();
            $mdfe = $contrato->viagem->mdfe;
            if ($mdfe !== null && $mdfe->status->transmissivel()) {
                $mdfe->forceFill(['ciot' => $numero])->save();
            }
            $contrato->viagem->registrar('ciot_registrado', "CIOT {$numero} gerado no e-Frete e ligado ao contrato e ao MDF-e.", ['contrato_id' => $contrato->getKey()], $user?->getKey());
        });

        // O PDF é um extra: sem ele o CIOT vale do mesmo jeito.
        try {
            $pdf = $this->efrete->pdfOperacao($config, $this->payload->pdf($config, $token, $verificador ? "{$numero}/{$verificador}" : $numero));
            $caminho = "transporte/{$contrato->emitente_id}/ciot/{$numero}.pdf";
            Storage::disk('fiscal')->put($caminho, $pdf);
            $contrato->forceFill(['ciot_pdf_path' => $caminho])->save();
        } catch (TransporteException $e) {
            report($e);
        }

        return $contrato->fresh();
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
