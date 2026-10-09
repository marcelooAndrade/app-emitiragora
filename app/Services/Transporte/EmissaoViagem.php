<?php

namespace App\Services\Transporte;

use App\Enums\Transporte\CteStatus;
use App\Enums\Transporte\MdfeStatus;
use App\Models\Cte;
use App\Models\User;
use App\Models\Viagem;
use App\Services\Transporte\Ciot\ProvedoresCiot;
use App\Services\Transporte\Ciot\ServicoCiot;

/**
 * O botão "Emitir": transmite os CT-e pendentes e, com todos autorizados,
 * o CIOT e depois o MDF-e. É o "automatizar" do Transm
 * (FiscalProcessoOrchestrator). Desde 09/10/2026 (CIOT para todos, DF-026)
 * o CIOT sai em toda viagem, com caminhão próprio ou de terceiro, pela
 * empresa configurada no emitente, e sem CIOT o MDF-e não sai.
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
        private readonly ServicoCiot $ciot,
        private readonly ProvedoresCiot $provedores,
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

    /** Entre os CT-e autorizados e o MDF-e. Sem CIOT registrado, para aqui. */
    private function gerarCiot(Viagem $viagem, ?User $user, array &$ok, array &$erros): void
    {
        $jaTinha = (bool) $viagem->ciotVigente()->first()?->registrado();
        try {
            $ciot = $this->ciot->garantir($viagem, $user);
        } catch (TransporteException $e) {
            $erros[] = 'CIOT: '.$e->getMessage();

            return;
        }
        $nome = $this->provedores->nome($ciot->provedor);
        if (! $ciot->registrado()) {
            $erros[] = "{$nome} aceitou o pedido do CIOT e ainda não devolveu o número. Aperte Emitir de novo em instantes.";
        } elseif (! $jaTinha) {
            $ok[] = "CIOT {$ciot->numeroCompleto()} gerado via {$nome}.";
        }
    }
}
