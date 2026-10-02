<?php

namespace App\Services\Transporte;

use App\Enums\Transporte\CteStatus;
use App\Models\Cte;
use App\Models\Viagem;
use App\Models\ViagemNota;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Transforma as NF-e da viagem em CT-e, sem ninguém preencher CT-e à mão.
 *
 * No Transm cada CT-e era um formulário de 500 linhas. Aqui o CT-e é
 * consequência das notas: um por grupo de NF-e com o mesmo remetente,
 * destinatário, origem e destino (a regra da SEFAZ), com o frete da viagem
 * rateado pelo peso de cada grupo.
 *
 * Só mexe em CT-e que ainda pode ir para a SEFAZ. O que já foi autorizado
 * fica como está.
 */
class MontadorCtes
{
    public function montar(Viagem $viagem): void
    {
        DB::transaction(function () use ($viagem): void {
            $viagem->load(['notas', 'ctes', 'emitente']);
            $configuracao = $viagem->emitente->configuracaoTransporte();

            $editaveis = $viagem->ctes->filter(fn (Cte $cte): bool => $cte->status->transmissivel());
            $travados = $viagem->ctes->reject(fn (Cte $cte): bool => $cte->status->transmissivel());
            $notasTravadas = $viagem->notas->filter(fn (ViagemNota $n): bool => $travados->contains('id', $n->cte_id));

            // Recomeça os rascunhos do zero: é mais simples e mais correto do
            // que tentar casar grupo antigo com grupo novo depois de uma nota
            // entrar ou sair. O número só existe depois da transmissão, então
            // rascunho apagado não queima numeração.
            $semNumero = $editaveis->filter(fn (Cte $cte): bool => $cte->numero === null);
            $comNumero = $editaveis->reject(fn (Cte $cte): bool => $cte->numero === null)->values();
            ViagemNota::query()->whereIn('cte_id', $semNumero->pluck('id'))->update(['cte_id' => null]);
            Cte::query()->whereIn('id', $semNumero->pluck('id'))->delete();

            $livres = $viagem->notas->reject(fn (ViagemNota $n): bool => $notasTravadas->contains('id', $n->id));
            $grupos = $livres->groupBy(fn (ViagemNota $n): string => $n->grupoCte())->values();

            $fretes = $this->fretes($viagem, $grupos);
            $pedagios = Rateio::dividir((int) $viagem->pedagio_centavos, $grupos->map(fn (Collection $g): float => $this->peso($g))->all());

            foreach ($grupos as $i => $notas) {
                /** @var ViagemNota $primeira */
                $primeira = $notas->first();
                // CT-e rejeitado já tem número reservado: reaproveita em vez
                // de queimar outro, que exigiria inutilização formal.
                $cte = $comNumero->shift() ?? new Cte([
                    'emitente_id' => $viagem->emitente_id,
                    'viagem_id' => $viagem->getKey(),
                    'serie' => $configuracao->cte_serie,
                    'status' => CteStatus::Rascunho,
                ]);

                $remetente = $primeira->remetente;
                $destinatario = $primeira->destinatario;
                $frete = $fretes[$i] ?? 0;
                $pedagio = $pedagios[$i] ?? 0;

                $cte->fill([
                    'ambiente' => $viagem->emitente->ambiente,
                    'cfop' => $configuracao->cfopPara((string) $remetente['uf'], (string) $destinatario['uf']),
                    'tomador_tipo' => $cte->exists ? $cte->tomador_tipo : $this->tomadorPadrao($notas),
                    'remetente' => $remetente,
                    'destinatario' => $destinatario,
                    'municipio_inicio_codigo' => $remetente['municipio_codigo'],
                    'municipio_inicio' => mb_substr((string) $remetente['municipio'], 0, 60),
                    'uf_inicio' => $remetente['uf'],
                    'municipio_fim_codigo' => $destinatario['municipio_codigo'],
                    'municipio_fim' => mb_substr((string) $destinatario['municipio'], 0, 60),
                    'uf_fim' => $destinatario['uf'],
                    'peso_kg' => number_format($this->peso($notas), 3, '.', ''),
                    'valor_carga_centavos' => (int) $notas->sum('valor_centavos'),
                    'produto_predominante' => mb_substr((string) ($notas->pluck('produto')->filter()->first() ?: 'CARGA GERAL'), 0, 60),
                    'valor_frete_centavos' => $frete,
                    'valor_pedagio_centavos' => $pedagio,
                    'valor_total_centavos' => $frete + $pedagio,
                ]);
                $cte->save();

                ViagemNota::query()->whereIn('id', $notas->pluck('id'))->update(['cte_id' => $cte->getKey()]);
            }

            // Sobrou CT-e rejeitado com número e sem nota (a nota dele saiu da
            // viagem). Ele não é apagado, porque o número já foi reservado e
            // número que some sem rastro vira buraco na sequência: fica com
            // valor zero, à vista na tela, até alguém inutilizar o número.
            foreach ($comNumero as $orfao) {
                $orfao->forceFill(['valor_frete_centavos' => 0, 'valor_pedagio_centavos' => 0, 'valor_total_centavos' => 0])->save();
            }
        });
    }

    /**
     * Frete de cada grupo. Por tonelada, é a conta direta. Fechado, o valor
     * combinado da viagem é rateado pelo peso, fechando no centavo.
     *
     * @param  Collection<int, Collection<int, ViagemNota>>  $grupos
     * @return array<int, int>
     */
    private function fretes(Viagem $viagem, Collection $grupos): array
    {
        if ($viagem->frete_modo === 'fechado') {
            return Rateio::dividir(
                (int) $viagem->frete_fechado_centavos,
                $grupos->map(fn (Collection $g): float => $this->peso($g) ?: (float) $g->sum('valor_centavos'))->all(),
            );
        }

        return $grupos->map(
            fn (Collection $g): int => (int) round((int) $viagem->frete_tonelada_centavos * $this->peso($g) / 1000)
        )->all();
    }

    /** @param  Collection<int, ViagemNota>  $notas */
    private function peso(Collection $notas): float
    {
        return (float) $notas->sum(fn (ViagemNota $n): float => (float) $n->peso_kg);
    }

    /**
     * Quem paga o frete, pela modalidade da própria NF-e: 1 (FOB) é o
     * destinatário; o resto, inclusive CIF (0), é o remetente.
     *
     * @param  Collection<int, ViagemNota>  $notas
     */
    private function tomadorPadrao(Collection $notas): string
    {
        return $notas->first()?->mod_frete === '1' ? '3' : '0';
    }
}
