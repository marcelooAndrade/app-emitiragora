<?php

namespace App\Services\Transporte;

use App\Enums\Fiscal\Ambiente;
use App\Enums\Transporte\CteStatus;
use App\Enums\Transporte\MdfeStatus;
use App\Models\Cte;
use App\Models\User;
use App\Models\Viagem;
use App\Services\Transporte\Efrete\CiotEfrete;

/**
 * O botão "Emitir": transmite os CT-e pendentes e, com todos autorizados,
 * o MDF-e. É o "automatizar" do Transm (FiscalProcessoOrchestrator): com
 * veículo de terceiro e e-Frete configurado, o CIOT sai no meio, entre os
 * CT-e autorizados e o MDF-e, sem outro clique.
 *
 * Não para no primeiro CT-e rejeitado: transmite todos, para quem opera ver
 * de uma vez o que precisa corrigir.
 *
 * @phpstan-type Resultado array{ok: array<int, string>, erros: array<int, string>}
 */
class EmissaoViagem
{
    public function __construct(
        private readonly MontadorCtes $montador,
        private readonly TransmissorCte $ctes,
        private readonly TransmissorMdfe $mdfe,
        private readonly CiotEfrete $ciot,
    ) {}

    /** @return array{ok: array<int, string>, erros: array<int, string>} */
    public function emitir(Viagem $viagem, ?User $user = null): array
    {
        $ok = [];
        $erros = [];

        $viagem->load('ctes');
        if (! $viagem->travada()) {
            $this->montador->montar($viagem);
        }

        foreach ($viagem->fresh()->ctes as $cte) {
            /** @var Cte $cte */
            if (! $cte->status->transmissivel()) {
                continue;
            }
            if ($cte->notas()->doesntExist()) {
                continue;
            }
            try {
                $resultado = $this->ctes->transmitir($cte, $user);
                $resultado->status === CteStatus::Autorizado
                    ? $ok[] = "CT-e {$resultado->numeroFormatado()} autorizado."
                    : $erros[] = "CT-e {$resultado->numeroFormatado()}: ".($resultado->x_motivo ?: $resultado->status->rotulo());
            } catch (TransporteException $e) {
                $erros[] = $e->getMessage();

                // Erro de configuração (certificado, RNTRC, regra) é igual
                // para todos os CT-e: repetir a mesma mensagem N vezes só
                // esconde o que importa.
                break;
            }
        }

        $viagem = $viagem->fresh(['ctes', 'mdfe']);
        $validos = $viagem->ctes->reject(fn (Cte $c): bool => $c->status === CteStatus::Cancelado);
        $todosAutorizados = $validos->isNotEmpty() && $validos->every(fn (Cte $c): bool => $c->status === CteStatus::Autorizado);

        $mdfePendente = ! in_array($viagem->mdfe?->status, [MdfeStatus::Autorizado, MdfeStatus::Encerrado], true);
        if ($erros === [] && $todosAutorizados && $mdfePendente) {
            $this->gerarCiot($viagem, $user, $ok, $erros);
        }

        if ($erros === [] && $todosAutorizados && $mdfePendente) {
            try {
                $mdfe = $this->mdfe->transmitir($viagem, $user);
                $mdfe->status === MdfeStatus::Autorizado
                    ? $ok[] = "MDF-e {$mdfe->numeroFormatado()} autorizado."
                    : $erros[] = "MDF-e {$mdfe->numeroFormatado()}: ".($mdfe->x_motivo ?: $mdfe->status->rotulo());
            } catch (TransporteException $e) {
                $erros[] = 'MDF-e: '.$e->getMessage();
            }
        }

        return ['ok' => $ok, 'erros' => $erros];
    }

    /** Só quando dá para gerar sozinho: terceiro, contrato sem CIOT e e-Frete ligado. */
    private function gerarCiot(Viagem $viagem, ?User $user, array &$ok, array &$erros): void
    {
        $viagem->loadMissing(['contrato', 'emitente', 'veiculo']);
        $contrato = $viagem->contrato;
        if (! $viagem->comTerceiro() || $contrato?->status !== 'ativo' || (filled($contrato->ciot) && $contrato->ciot_status !== 'processando')
            || ! $viagem->emitente->configuracaoTransporte()->temEfrete()
            // Fora de homologação a trava do e-Frete vale: o CIOT é digitado.
            || $viagem->emitente->ambiente !== Ambiente::Homologacao) {
            return;
        }
        try {
            $contrato = $this->ciot->gerar($contrato, [], $user);
            $contrato->ciot_status === 'registrado'
                ? $ok[] = "CIOT {$contrato->ciot} gerado no e-Frete."
                : $erros[] = 'O e-Frete aceitou o CIOT e ainda não devolveu o número. Aperte Emitir de novo em instantes.';
        } catch (TransporteException $e) {
            $erros[] = 'CIOT: '.$e->getMessage();
        }
    }
}
