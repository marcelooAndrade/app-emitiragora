<?php

namespace App\Services\Transporte;

use App\Enums\Transporte\CteStatus;
use App\Enums\Transporte\MdfeStatus;
use App\Models\Cte;
use App\Models\Mdfe;
use App\Models\SefazLog;
use App\Models\TransporteSerie;
use App\Models\User;
use App\Models\Viagem;
use App\Services\Fiscal\CertificateService;
use App\Services\Fiscal\RespostaSefaz;
use App\Services\Fiscal\SefazErrorTranslator;
use App\Support\Documento;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Prepara e transmite o MDF-e da viagem. Mesmo desenho do TransmissorCte,
 * portado do `MdfeTransmissionService` do app-transm.
 */
class TransmissorMdfe
{
    public function __construct(
        private readonly GatewayMdfe $gateway,
        private readonly MdfeXml $montador,
        private readonly NumeracaoTransporte $numeracao,
        private readonly RegraIcms $regras,
        private readonly CertificateService $certificados,
        private readonly SefazErrorTranslator $tradutor,
    ) {}

    /**
     * Cria (ou atualiza) o rascunho do MDF-e a partir dos CT-e autorizados.
     * O que a pessoa pode ajustar antes de transmitir (percurso, averbação)
     * fica preservado entre uma preparação e outra.
     */
    public function preparar(Viagem $viagem): Mdfe
    {
        $viagem->load(['ctes', 'mdfe', 'emitente', 'contrato']);
        $ctes = $viagem->ctes->filter(fn (Cte $c): bool => $c->status === CteStatus::Autorizado)->values();
        if ($ctes->isEmpty()) {
            throw new TransporteException('O MDF-e precisa de pelo menos um CT-e autorizado.');
        }
        $ufsInicio = $ctes->pluck('uf_inicio')->unique();
        $ufsFim = $ctes->pluck('uf_fim')->unique();
        if ($ufsInicio->count() > 1) {
            throw new TransporteException('As NF-e desta viagem saem de estados diferentes ('.$ufsInicio->join(', ').'). O MDF-e tem uma UF de carregamento só: separe em viagens por estado de origem.');
        }
        if ($ufsFim->count() > 1) {
            throw new TransporteException('Esta viagem descarrega em mais de um estado ('.$ufsFim->join(', ').'). O MDF-e tem uma UF de destino só: separe em viagens por estado de destino.');
        }

        $mdfe = $viagem->mdfe;
        if ($mdfe !== null && ! $mdfe->status->transmissivel()) {
            return $mdfe;
        }

        $config = $viagem->emitente->configuracaoTransporte();
        $primeiro = $ctes->first();
        $mdfe ??= new Mdfe([
            'emitente_id' => $viagem->emitente_id,
            'viagem_id' => $viagem->getKey(),
            'serie' => $config->mdfe_serie,
        ]);

        $percursoPadrao = null;
        try {
            $percursoPadrao = $this->regras->resolver($viagem->emitente, $primeiro->uf_inicio, $primeiro->uf_fim)->percurso_ufs;
        } catch (TransporteException) {
            // Sem regra o percurso fica vazio, e a tela pede.
        }
        $seguroAtual = (array) $mdfe->seguro;

        $mdfe->fill([
            'ambiente' => $viagem->emitente->ambiente,
            'uf_inicio' => $primeiro->uf_inicio,
            'uf_fim' => $primeiro->uf_fim,
            'municipio_carregamento_codigo' => $primeiro->municipio_inicio_codigo,
            'municipio_carregamento' => $primeiro->municipio_inicio,
            'percurso_ufs' => $mdfe->exists && $mdfe->percurso_ufs !== null ? $mdfe->percurso_ufs : $percursoPadrao,
            'ciot' => $viagem->contrato?->status === 'ativo' ? $viagem->contrato->ciot : null,
            'seguro' => $config->seguradora_nome ? [
                'responsavel' => $config->responsavel_seguro,
                'seguradora_nome' => $config->seguradora_nome,
                'seguradora_cnpj' => $config->seguradora_cnpj,
                'apolice' => $config->apolice,
                // As averbações digitadas mais as que a AT&M devolveu nos CT-e.
                'averbacoes' => array_values(array_unique(array_filter([
                    ...(array) ($seguroAtual['averbacoes'] ?? []),
                    ...$ctes->pluck('averbacao_numero')->all(),
                ]))),
            ] : null,
        ]);
        $mdfe->save();

        return $mdfe;
    }

    public function transmitir(Viagem $viagem, ?User $user = null): Mdfe
    {
        return Cache::lock("transporte:mdfe:viagem:{$viagem->getKey()}", 120)->block(5, function () use ($viagem, $user): Mdfe {
            $viagem->load(['ctes', 'mdfe', 'emitente', 'motorista', 'veiculo', 'reboque', 'reboque2']);
            if ($viagem->mdfe?->status === MdfeStatus::Autorizado || $viagem->mdfe?->status === MdfeStatus::Encerrado) {
                return $viagem->mdfe;
            }
            $this->conferir($viagem);
            $mdfe = $this->preparar($viagem);

            if ($mdfe->numero === null) {
                $mdfe->numero = $this->numeracao->proximo($viagem->emitente, TransporteSerie::MDFE, (int) $mdfe->serie);
                $mdfe->save();
            }

            $montado = $this->montador->montar($mdfe->fresh());
            $assinado = $this->gateway->assinar($viagem->emitente, $montado['xml']);
            $caminho = "transporte/{$viagem->emitente_id}/mdfe/{$montado['chave']}-assinado.xml";
            Storage::disk('fiscal')->put($caminho, $assinado);
            $mdfe->forceFill([
                'chave' => $montado['chave'],
                'xml_path' => $caminho,
                'emitido_em' => $montado['emitido_em'],
                'status' => MdfeStatus::EmProcessamento,
                'transmitido_por' => $user?->getKey(),
                'c_stat' => null,
                'x_motivo' => null,
            ])->save();

            $inicio = microtime(true);
            try {
                $resposta = $this->gateway->enviar($viagem->emitente, $assinado);
            } catch (TransporteException $e) {
                throw $e;
            } catch (Throwable $e) {
                $this->log($mdfe, 'mdfe-autorizacao', null, null, $inicio, $e->getMessage());

                return $this->resolverPelaChave($mdfe, $assinado);
            }
            $this->log($mdfe, 'mdfe-autorizacao', $resposta->cStat, $resposta->xMotivo, $inicio);

            return $this->aplicar($mdfe, $resposta, $user);
        });
    }

    public function consultar(Mdfe $mdfe): Mdfe
    {
        if ($mdfe->status !== MdfeStatus::EmProcessamento || blank($mdfe->chave)) {
            throw new TransporteException('Só um MDF-e em processamento precisa ser consultado.');
        }

        return $this->resolverPelaChave($mdfe, $mdfe->xml_path ? Storage::disk('fiscal')->get($mdfe->xml_path) : null);
    }

    /** O que impede o MDF-e, dito de um jeito que dá para resolver. */
    public function pendencias(Viagem $viagem): array
    {
        $viagem->loadMissing(['ctes', 'emitente', 'motorista', 'veiculo', 'reboque', 'reboque2', 'contrato']);
        $pendencias = [];
        $validos = $viagem->ctes->reject(fn (Cte $c): bool => $c->status === CteStatus::Cancelado);
        if ($validos->isEmpty() || $validos->contains(fn (Cte $c): bool => $c->status !== CteStatus::Autorizado)) {
            $pendencias[] = 'Todos os CT-e da viagem precisam estar autorizados.';
        }
        if ($viagem->motorista === null) {
            $pendencias[] = 'Escolha o motorista.';
        } elseif (! Documento::cpfValido((string) $viagem->motorista->cpf)) {
            $pendencias[] = 'O CPF do motorista '.$viagem->motorista->nome.' é inválido.';
        }
        if ($viagem->veiculo === null) {
            $pendencias[] = 'Escolha o veículo (cavalo).';
        } elseif ($faltando = $viagem->veiculo->pendenciasMdfe()) {
            $pendencias[] = 'Complete o cadastro do veículo '.$viagem->veiculo->placaFormatada().': '.implode(', ', $faltando).'.';
        }
        foreach ([$viagem->reboque, $viagem->reboque2] as $reboque) {
            if ($reboque && ($faltando = $reboque->pendenciasMdfe())) {
                $pendencias[] = 'Complete o cadastro da carreta '.$reboque->placaFormatada().': '.implode(', ', $faltando).'.';
            }
        }
        if ($viagem->comTerceiro()) {
            // Frete pago a terceiro: o MDF-e leva o pagamento (infPag) e, para
            // TAC, o CIOT. Sem contrato não há de onde tirar nenhum dos dois.
            $contrato = $viagem->contrato?->status === 'ativo' ? $viagem->contrato : null;
            if ($contrato === null) {
                $pendencias[] = 'Veículo de terceiro: preencha o contrato do frete (quanto o motorista recebe e como).';
            } elseif ($contrato->exigeCiot() && blank($contrato->ciot)) {
                $pendencias[] = $viagem->emitente->configuracaoTransporte()->temEfrete()
                    ? 'O proprietário do veículo é TAC: gere o CIOT no e-Frete (botão no contrato ou Emitir).'
                    : 'O proprietário do veículo é TAC: informe o CIOT no contrato do frete.';
            }
        }
        if ($faltando = $viagem->emitente->configuracaoTransporte()->pendenciasMdfe()) {
            $pendencias[] = 'Complete em Transporte, Configuração: '.implode('; ', $faltando).'.';
        }
        if ($this->certificados->ativo($viagem->emitente) === null) {
            $pendencias[] = 'Envie o certificado digital A1 em Configuração, Certificado.';
        }

        return $pendencias;
    }

    private function conferir(Viagem $viagem): void
    {
        $pendencias = $this->pendencias($viagem);
        if ($pendencias !== []) {
            throw new TransporteException(implode(' ', $pendencias));
        }
    }

    private function resolverPelaChave(Mdfe $mdfe, ?string $assinado): Mdfe
    {
        $inicio = microtime(true);
        try {
            $resposta = $this->gateway->consultar($mdfe->emitente, (string) $mdfe->chave, $assinado);
        } catch (Throwable $e) {
            $this->log($mdfe, 'mdfe-consulta', null, null, $inicio, $e->getMessage());
            $mdfe->forceFill([
                'status' => MdfeStatus::EmProcessamento,
                'x_motivo' => 'Sem resposta da SEFAZ. A situação deste MDF-e ainda é desconhecida: consulte de novo antes de emitir outro.',
            ])->save();
            $mdfe->viagem->recalcularStatus();

            return $mdfe->fresh();
        }
        $this->log($mdfe, 'mdfe-consulta', $resposta->cStat, $resposta->xMotivo, $inicio);
        if ($resposta->cStat === '217') {
            $mdfe->forceFill(['status' => MdfeStatus::Rejeitado, 'c_stat' => '217', 'x_motivo' => 'O envio anterior não chegou à SEFAZ. Pode transmitir de novo.'])->save();
            $mdfe->viagem->recalcularStatus();

            return $mdfe->fresh();
        }

        return $this->aplicar($mdfe, $resposta, null);
    }

    private function aplicar(Mdfe $mdfe, RespostaSefaz $resposta, ?User $user): Mdfe
    {
        if ($resposta->autorizada()) {
            $caminho = null;
            if (filled($resposta->xmlProtocolado)) {
                $caminho = "transporte/{$mdfe->emitente_id}/mdfe/{$mdfe->chave}-autorizado.xml";
                Storage::disk('fiscal')->put($caminho, $resposta->xmlProtocolado);
            }
            $mdfe->forceFill([
                'status' => MdfeStatus::Autorizado,
                'protocolo' => $resposta->protocolo,
                'c_stat' => $resposta->cStat,
                'x_motivo' => $resposta->xMotivo,
                'autorizado_em' => now(),
                'xml_autorizado_path' => $caminho,
            ])->save();
            $mdfe->viagem->registrar('mdfe_autorizado', "MDF-e {$mdfe->numeroFormatado()} autorizado. Boa viagem.", ['mdfe_id' => $mdfe->getKey()], $user?->getKey());
        } elseif ($resposta->emProcessamento()) {
            $mdfe->forceFill(['status' => MdfeStatus::EmProcessamento, 'c_stat' => $resposta->cStat, 'x_motivo' => $resposta->xMotivo])->save();
        } else {
            $mdfe->forceFill([
                'status' => MdfeStatus::Rejeitado,
                'c_stat' => $resposta->cStat,
                'x_motivo' => $this->tradutor->mensagemCompleta($resposta->cStat, $resposta->xMotivo),
            ])->save();
            $mdfe->viagem->registrar('mdfe_rejeitado', "MDF-e {$mdfe->numeroFormatado()} rejeitado: {$resposta->cStat} {$resposta->xMotivo}", ['mdfe_id' => $mdfe->getKey()], $user?->getKey());
        }
        $mdfe->viagem->recalcularStatus();

        return $mdfe->fresh();
    }

    private function log(Mdfe $mdfe, string $operacao, ?string $cStat, ?string $xMotivo, float $inicio, ?string $erro = null): void
    {
        SefazLog::create([
            'emitente_id' => $mdfe->emitente_id,
            'operacao' => $operacao,
            'ambiente' => $mdfe->ambiente->value,
            'c_stat' => $cStat,
            'x_motivo' => $xMotivo,
            'duracao_ms' => (int) round((microtime(true) - $inicio) * 1000),
            'erro' => $erro === null ? null : mb_substr($erro, 0, 2000),
            'created_at' => now(),
        ]);
    }
}
