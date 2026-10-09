<?php

namespace App\Services\Transporte;

use App\Enums\Transporte\CteStatus;
use App\Enums\Transporte\MdfeStatus;
use App\Models\Cte;
use App\Models\CteEvento;
use App\Models\User;
use App\Services\Transporte\Ciot\ServicoCiot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Cancelamento e carta de correção do CT-e. Vieram do
 * `CteCancellationService` e do `CteCorrectionTransmissionService` do Transm.
 */
class EventosCte
{
    /**
     * Campos que a carta de correção do CT-e pode corrigir. A lista é curta
     * de propósito: o Convênio SINIEF 06/89 proíbe corrigir valor, imposto,
     * participantes e datas, e oferecer só o que pode evita rejeição.
     */
    public const CORRECOES = [
        'xObs' => ['grupo' => 'compl', 'rotulo' => 'Observações do CT-e'],
        'proPred' => ['grupo' => 'infCarga', 'rotulo' => 'Produto predominante'],
        'xOutCat' => ['grupo' => 'infCarga', 'rotulo' => 'Outras características da carga'],
        'xLgr' => ['grupo' => 'enderDest', 'rotulo' => 'Endereço do destinatário'],
        'nro' => ['grupo' => 'enderDest', 'rotulo' => 'Número do endereço do destinatário'],
        'xBairro' => ['grupo' => 'enderDest', 'rotulo' => 'Bairro do destinatário'],
    ];

    public function __construct(
        private readonly GatewayCte $gateway,
        private readonly ContratosFrete $contratos,
        private readonly ServicoCiot $ciot,
    ) {}

    public function cancelar(Cte $cte, string $justificativa, ?User $user = null): Cte
    {
        $justificativa = trim($justificativa);
        if (mb_strlen($justificativa) < 15 || mb_strlen($justificativa) > 255) {
            throw new TransporteException('A justificativa do cancelamento precisa ter de 15 a 255 caracteres.');
        }

        return Cache::lock("transporte:cte:{$cte->getKey()}", 120)->block(5, function () use ($cte, $justificativa, $user): Cte {
            $cte->refresh()->load(['viagem.mdfe', 'emitente']);
            if ($cte->status === CteStatus::Cancelado) {
                return $cte;
            }
            if ($cte->status !== CteStatus::Autorizado || blank($cte->protocolo)) {
                throw new TransporteException('Só CT-e autorizado pode ser cancelado.');
            }
            // A SEFAZ recusa cancelar CT-e que está num MDF-e em viagem. O
            // Transm bloqueava aqui também, e é melhor explicar antes.
            if ($cte->viagem->mdfe?->status === MdfeStatus::Autorizado) {
                throw new TransporteException('Este CT-e está no MDF-e da viagem. Cancele ou encerre o MDF-e antes de cancelar o CT-e.');
            }

            try {
                $resposta = $this->gateway->cancelar($cte->emitente, (string) $cte->chave, (string) $cte->protocolo, $justificativa);
            } catch (TransporteException $e) {
                throw $e;
            } catch (Throwable) {
                throw new TransporteException('Não foi possível falar com a SEFAZ agora. O CT-e continua autorizado; tente cancelar de novo em alguns minutos.');
            }
            if (! RetornoSefaz::eventoAceito($resposta)) {
                throw new TransporteException("A SEFAZ recusou o cancelamento: {$resposta->cStat} {$resposta->xMotivo}");
            }

            DB::transaction(function () use ($cte, $justificativa, $resposta, $user): void {
                $cte->forceFill([
                    'status' => CteStatus::Cancelado,
                    'cancelado_em' => now(),
                    'protocolo_cancelamento' => $resposta->protocolo,
                    'c_stat' => $resposta->cStat,
                    'x_motivo' => $resposta->xMotivo,
                ])->save();
                CteEvento::create([
                    'cte_id' => $cte->getKey(),
                    'tipo' => 'cancelamento',
                    'sequencia' => 1,
                    'descricao' => $justificativa,
                    'protocolo' => $resposta->protocolo,
                    'c_stat' => $resposta->cStat,
                    'x_motivo' => $resposta->xMotivo,
                    'user_id' => $user?->getKey(),
                    'created_at' => now(),
                ]);
                $aviso = $cte->fatura_id ? " A fatura #{$cte->fatura_id} continua ativa: cancele em Faturas se não for mais cobrar." : '';
                $cte->viagem->registrar('cte_cancelado', "CT-e {$cte->numeroFormatado()} cancelado.{$aviso}", ['cte_id' => $cte->getKey()], $user?->getKey());
            });

            $cte->viagem->recalcularStatus();
            $this->contratos->cancelarSeSemCte($cte->viagem->fresh());
            $this->ciot->cancelarSeSemCte($cte->viagem->fresh(), $user);

            return $cte->fresh();
        });
    }

    public function cartaCorrecao(Cte $cte, string $campo, string $valor, ?User $user = null): CteEvento
    {
        $valor = trim($valor);
        if (! array_key_exists($campo, self::CORRECOES)) {
            throw new TransporteException('Escolha o campo a corrigir.');
        }
        if (mb_strlen($valor) < 1 || mb_strlen($valor) > 500) {
            throw new TransporteException('Informe o valor correto (até 500 caracteres).');
        }
        $cte->refresh()->load(['viagem', 'emitente', 'eventos']);
        if ($cte->status !== CteStatus::Autorizado) {
            throw new TransporteException('Só CT-e autorizado aceita carta de correção.');
        }
        $sequencia = $cte->eventos->where('tipo', 'carta_correcao')->count() + 1;
        if ($sequencia > 20) {
            throw new TransporteException('Este CT-e já tem 20 cartas de correção, o limite da SEFAZ.');
        }

        $correcao = [[
            'grupoAlterado' => self::CORRECOES[$campo]['grupo'],
            'campoAlterado' => $campo,
            'valorAlterado' => htmlspecialchars($valor, ENT_XML1),
        ]];
        try {
            $resposta = $this->gateway->cartaCorrecao($cte->emitente, (string) $cte->chave, $correcao, $sequencia);
        } catch (TransporteException $e) {
            throw $e;
        } catch (Throwable) {
            throw new TransporteException('Não foi possível falar com a SEFAZ agora. Tente a carta de correção de novo em alguns minutos.');
        }
        if (! RetornoSefaz::eventoAceito($resposta)) {
            throw new TransporteException("A SEFAZ recusou a carta de correção: {$resposta->cStat} {$resposta->xMotivo}");
        }

        $evento = CteEvento::create([
            'cte_id' => $cte->getKey(),
            'tipo' => 'carta_correcao',
            'sequencia' => $sequencia,
            'descricao' => self::CORRECOES[$campo]['rotulo'].': '.$valor,
            'protocolo' => $resposta->protocolo,
            'c_stat' => $resposta->cStat,
            'x_motivo' => $resposta->xMotivo,
            'user_id' => $user?->getKey(),
            'created_at' => now(),
        ]);
        $cte->viagem->registrar('cte_corrigido', "Carta de correção {$sequencia} do CT-e {$cte->numeroFormatado()}: ".self::CORRECOES[$campo]['rotulo'].'.', ['cte_id' => $cte->getKey()], $user?->getKey());

        return $evento;
    }
}
