<?php

namespace App\Services\Transporte;

use App\Enums\Transporte\MdfeStatus;
use App\Models\Mdfe;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use NFePHP\Common\UFList;
use Throwable;

/**
 * Encerramento e cancelamento do MDF-e. O encerramento veio do
 * `FiscalTripClosingService` do Transm, sem a parte do CIOT, que aqui só
 * existe quando a viagem tem motorista terceiro.
 */
class EventosMdfe
{
    public function __construct(
        private readonly GatewayMdfe $gateway,
    ) {}

    /**
     * Encerra na chegada. Sem município informado, usa o destino do último
     * CT-e, que é onde a carga de fato termina na maioria das viagens.
     */
    public function encerrar(Mdfe $mdfe, ?string $municipioCodigo = null, ?string $data = null, ?User $user = null): Mdfe
    {
        return Cache::lock("transporte:mdfe:{$mdfe->getKey()}", 120)->block(5, function () use ($mdfe, $municipioCodigo, $data, $user): Mdfe {
            $mdfe->refresh()->load(['viagem.ctes', 'emitente']);
            if ($mdfe->status === MdfeStatus::Encerrado) {
                return $mdfe;
            }
            if ($mdfe->status !== MdfeStatus::Autorizado) {
                throw new TransporteException('Só MDF-e autorizado (em viagem) pode ser encerrado.');
            }

            // A UF sai dos dois primeiros dígitos do código IBGE, sem depender
            // da tabela de municípios estar importada no ambiente.
            $municipioCodigo = preg_replace('/\D/', '', (string) ($municipioCodigo ?? $mdfe->viagem->ctes->last()?->municipio_fim_codigo));
            try {
                $uf = strlen($municipioCodigo) === 7 ? UFList::getUFByCode(substr($municipioCodigo, 0, 2)) : null;
            } catch (Throwable) {
                $uf = null;
            }
            if ($uf === null) {
                throw new TransporteException('Escolha o município onde a viagem terminou.');
            }
            $nome = $mdfe->viagem->ctes->firstWhere('municipio_fim_codigo', $municipioCodigo)?->municipio_fim ?? $municipioCodigo;
            $data = Carbon::parse($data ?? today()->toDateString());
            if ($data->isFuture() || $data->lt($mdfe->autorizado_em?->copy()->startOfDay() ?? $data)) {
                throw new TransporteException('A data de encerramento não pode ser futura nem anterior à autorização do MDF-e.');
            }

            try {
                $resposta = $this->gateway->encerrar($mdfe->emitente, (string) $mdfe->chave, (string) $mdfe->protocolo, $uf, $municipioCodigo, $data->toDateString());
            } catch (TransporteException $e) {
                throw $e;
            } catch (Throwable) {
                throw new TransporteException('Não foi possível falar com a SEFAZ agora. O MDF-e continua em viagem; tente encerrar de novo em alguns minutos.');
            }
            if (! RetornoSefaz::eventoAceito($resposta)) {
                throw new TransporteException("A SEFAZ recusou o encerramento: {$resposta->cStat} {$resposta->xMotivo}");
            }

            $mdfe->forceFill([
                'status' => MdfeStatus::Encerrado,
                'encerrado_em' => $data,
                'protocolo_encerramento' => $resposta->protocolo,
                'c_stat' => $resposta->cStat,
                'x_motivo' => $resposta->xMotivo,
            ])->save();
            $mdfe->viagem->registrar('mdfe_encerrado', "MDF-e {$mdfe->numeroFormatado()} encerrado em {$nome}/{$uf}.", ['mdfe_id' => $mdfe->getKey()], $user?->getKey());
            $mdfe->viagem->recalcularStatus();

            return $mdfe->fresh();
        });
    }

    /** Cancelamento: a SEFAZ só aceita em até 24 h e antes de encerrar. */
    public function cancelar(Mdfe $mdfe, string $justificativa, ?User $user = null): Mdfe
    {
        $justificativa = trim($justificativa);
        if (mb_strlen($justificativa) < 15 || mb_strlen($justificativa) > 255) {
            throw new TransporteException('A justificativa do cancelamento precisa ter de 15 a 255 caracteres.');
        }

        return Cache::lock("transporte:mdfe:{$mdfe->getKey()}", 120)->block(5, function () use ($mdfe, $justificativa, $user): Mdfe {
            $mdfe->refresh()->load(['viagem', 'emitente']);
            if ($mdfe->status === MdfeStatus::Cancelado) {
                return $mdfe;
            }
            if ($mdfe->status !== MdfeStatus::Autorizado) {
                throw new TransporteException('Só MDF-e autorizado e ainda não encerrado pode ser cancelado.');
            }

            try {
                $resposta = $this->gateway->cancelar($mdfe->emitente, (string) $mdfe->chave, (string) $mdfe->protocolo, $justificativa);
            } catch (TransporteException $e) {
                throw $e;
            } catch (Throwable) {
                throw new TransporteException('Não foi possível falar com a SEFAZ agora. Tente cancelar de novo em alguns minutos.');
            }
            if (! RetornoSefaz::eventoAceito($resposta)) {
                throw new TransporteException("A SEFAZ recusou o cancelamento: {$resposta->cStat} {$resposta->xMotivo}");
            }

            $mdfe->forceFill([
                'status' => MdfeStatus::Cancelado,
                'cancelado_em' => now(),
                'protocolo_cancelamento' => $resposta->protocolo,
                'c_stat' => $resposta->cStat,
                'x_motivo' => $resposta->xMotivo,
            ])->save();
            $mdfe->viagem->registrar('mdfe_cancelado', "MDF-e {$mdfe->numeroFormatado()} cancelado: {$justificativa}", ['mdfe_id' => $mdfe->getKey()], $user?->getKey());
            $mdfe->viagem->recalcularStatus();

            return $mdfe->fresh();
        });
    }
}
