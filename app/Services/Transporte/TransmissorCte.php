<?php

namespace App\Services\Transporte;

use App\Enums\Transporte\CteStatus;
use App\Models\Cte;
use App\Models\SefazLog;
use App\Models\TransporteSerie;
use App\Models\User;
use App\Services\Fiscal\CertificateService;
use App\Services\Fiscal\RespostaSefaz;
use App\Services\Fiscal\SefazErrorTranslator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Transmite o CT-e à SEFAZ.
 *
 * O fluxo é o do `CteTransmissionService` do Transm (calcula o imposto na
 * hora, numera, monta, assina, envia) com a idempotência do `NFeTransmitter`
 * deste projeto: diante de falha de comunicação ninguém sabe se a SEFAZ
 * recebeu, então antes de qualquer reenvio pergunta-se pela chave.
 */
class TransmissorCte
{
    public function __construct(
        private readonly GatewayCte $gateway,
        private readonly CteXml $montador,
        private readonly RegraIcms $regras,
        private readonly NumeracaoTransporte $numeracao,
        private readonly CertificateService $certificados,
        private readonly SefazErrorTranslator $tradutor,
        private readonly AverbacaoAtm $averbacao,
    ) {}

    public function transmitir(Cte $cte, ?User $user = null): Cte
    {
        return Cache::lock("transporte:cte:{$cte->getKey()}", 120)->block(5, function () use ($cte, $user): Cte {
            $cte->refresh()->load(['notas', 'emitente', 'viagem']);
            $this->conferir($cte);

            $regra = $this->regras->resolver($cte->emitente, $cte->uf_inicio, $cte->uf_fim);
            $cte->forceFill([
                ...$this->regras->calcular($regra, (int) $cte->valor_total_centavos),
                'regra_icms_id' => $regra->exists ? $regra->getKey() : null,
            ]);
            if ($cte->numero === null) {
                $cte->numero = $this->numeracao->proximo($cte->emitente, TransporteSerie::CTE, (int) $cte->serie);
            }
            $cte->save();

            $montado = $this->montador->montar($cte->fresh(['notas', 'emitente', 'viagem', 'regraIcms']));
            $assinado = $this->gateway->assinar($cte->emitente, $montado['xml']);
            $caminho = "transporte/{$cte->emitente_id}/cte/{$montado['chave']}-assinado.xml";
            Storage::disk('fiscal')->put($caminho, $assinado);

            $cte->forceFill([
                'chave' => $montado['chave'],
                'xml_path' => $caminho,
                'emitido_em' => $montado['emitido_em'],
                'status' => CteStatus::EmProcessamento,
                'transmitido_por' => $user?->getKey(),
                'c_stat' => null,
                'x_motivo' => null,
            ])->save();

            $inicio = microtime(true);
            try {
                $resposta = $this->gateway->enviar($cte->emitente, $assinado);
            } catch (TransporteException $e) {
                throw $e;
            } catch (Throwable $e) {
                $this->log($cte, 'cte-autorizacao', null, null, $inicio, $e->getMessage());

                return $this->resolverPelaChave($cte, $assinado);
            }
            $this->log($cte, 'cte-autorizacao', $resposta->cStat, $resposta->xMotivo, $inicio);

            // 204: a SEFAZ já tem um CT-e com esta chave. Quem sabe se é este
            // é a consulta, não o reenvio.
            if ($resposta->cStat === '204') {
                return $this->resolverPelaChave($cte, $assinado);
            }

            return $this->aplicar($cte, $resposta, $user);
        });
    }

    /** Para o CT-e que ficou em processamento depois de uma falha de comunicação. */
    public function consultar(Cte $cte): Cte
    {
        if ($cte->status !== CteStatus::EmProcessamento || blank($cte->chave)) {
            throw new TransporteException('Só um CT-e em processamento precisa ser consultado.');
        }

        return $this->resolverPelaChave($cte, $cte->xml_path ? Storage::disk('fiscal')->get($cte->xml_path) : null);
    }

    private function resolverPelaChave(Cte $cte, ?string $assinado): Cte
    {
        $inicio = microtime(true);
        try {
            $resposta = $this->gateway->consultar($cte->emitente, (string) $cte->chave, $assinado);
        } catch (Throwable $e) {
            $this->log($cte, 'cte-consulta', null, null, $inicio, $e->getMessage());
            $cte->forceFill([
                'status' => CteStatus::EmProcessamento,
                'x_motivo' => 'Sem resposta da SEFAZ. A situação deste CT-e ainda é desconhecida: consulte de novo antes de emitir outro.',
            ])->save();
            $cte->viagem->registrar('cte_sem_resposta', "CT-e {$cte->numeroFormatado()} sem resposta da SEFAZ.");
            $cte->viagem->recalcularStatus();

            return $cte->fresh();
        }
        $this->log($cte, 'cte-consulta', $resposta->cStat, $resposta->xMotivo, $inicio);

        // 217: a SEFAZ não conhece a chave. O envio não chegou; pode reenviar.
        if ($resposta->cStat === '217') {
            $cte->forceFill(['status' => CteStatus::Rejeitado, 'c_stat' => '217', 'x_motivo' => 'O envio anterior não chegou à SEFAZ. Pode transmitir de novo.'])->save();
            $cte->viagem->recalcularStatus();

            return $cte->fresh();
        }

        return $this->aplicar($cte, $resposta, null);
    }

    /**
     * Averba na AT&M logo depois de autorizar, quando a empresa usa. Falha da
     * seguradora fica registrada no CT-e e na viagem, e a emissão segue:
     * o CT-e já está autorizado e a averbação pode ser refeita pela tela.
     */
    private function averbar(Cte $cte, ?User $user): void
    {
        if (! $this->averbacao->disponivel($cte)) {
            return;
        }
        try {
            $this->averbacao->averbar($cte, $user);
        } catch (Throwable $e) {
            $mensagem = $e instanceof TransporteException ? $e->getMessage() : 'A AT&M não respondeu: '.$e->getMessage();
            $cte->forceFill(['averbacao_status' => 'recusada', 'averbacao_mensagem' => mb_substr($mensagem, 0, 2000)])->save();
            $cte->viagem->registrar('cte_averbacao_recusada', "Averbação do CT-e {$cte->numeroFormatado()} não saiu: {$mensagem}", ['cte_id' => $cte->getKey()], $user?->getKey());
        }
    }

    private function aplicar(Cte $cte, RespostaSefaz $resposta, ?User $user): Cte
    {
        if ($resposta->autorizada()) {
            DB::transaction(function () use ($cte, $resposta, $user): void {
                $caminho = null;
                if (filled($resposta->xmlProtocolado)) {
                    $caminho = "transporte/{$cte->emitente_id}/cte/{$cte->chave}-autorizado.xml";
                    Storage::disk('fiscal')->put($caminho, $resposta->xmlProtocolado);
                }
                $cte->forceFill([
                    'status' => CteStatus::Autorizado,
                    'protocolo' => $resposta->protocolo,
                    'c_stat' => $resposta->cStat,
                    'x_motivo' => $resposta->xMotivo,
                    'autorizado_em' => now(),
                    'xml_autorizado_path' => $caminho,
                ])->save();
                $cte->viagem->registrar(
                    'cte_autorizado',
                    "CT-e {$cte->numeroFormatado()} autorizado. Protocolo {$resposta->protocolo}.",
                    ['cte_id' => $cte->getKey(), 'chave' => $cte->chave],
                    $user?->getKey(),
                );
            });
            $this->averbar($cte, $user);
        } elseif ($resposta->denegada() || $resposta->emProcessamento()) {
            $cte->forceFill([
                'status' => $resposta->denegada() ? CteStatus::Denegado : CteStatus::EmProcessamento,
                'c_stat' => $resposta->cStat,
                'x_motivo' => $this->tradutor->mensagemCompleta($resposta->cStat, $resposta->xMotivo),
            ])->save();
        } else {
            $cte->forceFill([
                'status' => CteStatus::Rejeitado,
                'c_stat' => $resposta->cStat,
                'x_motivo' => $this->tradutor->mensagemCompleta($resposta->cStat, $resposta->xMotivo),
            ])->save();
            $cte->viagem->registrar(
                'cte_rejeitado',
                "CT-e {$cte->numeroFormatado()} rejeitado: {$resposta->cStat} {$resposta->xMotivo}",
                ['cte_id' => $cte->getKey()],
                $user?->getKey(),
            );
        }

        $cte->viagem->recalcularStatus();

        return $cte->fresh();
    }

    /** O que impede a transmissão, dito de forma que dá para resolver. */
    private function conferir(Cte $cte): void
    {
        if (! $cte->status->transmissivel()) {
            throw new TransporteException("O CT-e {$cte->numeroFormatado()} está {$cte->status->rotulo()} e não pode ser transmitido.");
        }
        if ($this->certificados->ativo($cte->emitente) === null) {
            throw new TransporteException('Envie o certificado digital A1 da empresa em Configuração, Certificado.');
        }
        $config = $cte->emitente->configuracaoTransporte();
        if (strlen((string) $config->rntrc) !== 8) {
            throw new TransporteException('Informe o RNTRC da empresa (8 dígitos) em Transporte, Configuração.');
        }
        if ($cte->notas->isEmpty()) {
            throw new TransporteException("O CT-e {$cte->numeroFormatado()} ficou sem NF-e. Remonte a viagem.");
        }
        $semPeso = $cte->notas->first(fn ($n): bool => (float) $n->peso_kg <= 0);
        if ($semPeso !== null) {
            throw new TransporteException("Informe o peso da NF-e {$semPeso->numero}: o XML não trouxe e o CT-e precisa dele.");
        }
        if ((int) $cte->valor_total_centavos <= 0) {
            throw new TransporteException('Informe o frete da viagem antes de emitir.');
        }
    }

    private function log(Cte $cte, string $operacao, ?string $cStat, ?string $xMotivo, float $inicio, ?string $erro = null): void
    {
        SefazLog::create([
            'emitente_id' => $cte->emitente_id,
            'operacao' => $operacao,
            'ambiente' => $cte->ambiente->value,
            'c_stat' => $cStat,
            'x_motivo' => $xMotivo,
            'duracao_ms' => (int) round((microtime(true) - $inicio) * 1000),
            'erro' => $erro === null ? null : mb_substr($erro, 0, 2000),
            'created_at' => now(),
        ]);
    }
}
