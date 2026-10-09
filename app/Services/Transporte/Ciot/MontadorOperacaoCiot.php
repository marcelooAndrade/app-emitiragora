<?php

namespace App\Services\Transporte\Ciot;

use App\Enums\Transporte\CteStatus;
use App\Models\ContratoFrete;
use App\Models\Cte;
use App\Models\Veiculo;
use App\Models\Viagem;
use App\Models\ViagemNota;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Monta a `OperacaoCiot` a partir do que a viagem já tem, e diz o que falta.
 *
 * Caminho por dono do caminhão (Res. ANTT 6.078/2026, art. 1º-A):
 * - frota própria: a transportadora é a contratada, quem paga o frete (o
 *   tomador do CT-e) é o contratante, e o valor é o frete dos CT-e;
 * - terceiro TAC: o proprietário do veículo é o contratado, a transportadora
 *   é a contratante, e o valor e o pagamento vêm do contrato do frete.
 *
 * As pendências seguem as regras de recusa do DCS citadas em cada frase,
 * para a pessoa resolver antes de a empresa recusar.
 */
class MontadorOperacaoCiot
{
    public function montar(Viagem $viagem): OperacaoCiot
    {
        $viagem->loadMissing(['emitente', 'ctes', 'notas', 'veiculo', 'reboque', 'reboque2', 'contrato']);
        $emitente = $viagem->emitente;
        $transporte = $emitente->configuracaoTransporte();
        $ctes = $this->ctesValidos($viagem);
        $frotaPropria = ! $viagem->comTerceiro();
        $contrato = $viagem->contrato?->status === 'ativo' ? $viagem->contrato : null;
        $tomadores = $this->tomadores($ctes);
        $principal = $this->notaPrincipal($viagem);
        $inicio = ($viagem->data_carregamento ?? today())->copy();
        $tipo = $viagem->tipoOperacao();

        return new OperacaoCiot(
            viagem: $viagem,
            tipoOperacao: $tipo,
            frotaPropria: $frotaPropria,
            contratadoDocumento: $frotaPropria ? (string) $emitente->cnpj : $this->digitos($viagem->veiculo?->proprietario_documento),
            contratadoRntrc: $frotaPropria ? $this->rntrc($transporte->rntrc) : $this->rntrc($viagem->veiculo?->proprietario_rntrc),
            contratanteDocumento: $frotaPropria ? (string) ($tomadores->first() ?? '') : (string) $emitente->cnpj,
            destinatarioDocumento: $this->digitos($principal?->destinatario['documento'] ?? null) ?: null,
            valorFreteCentavos: $frotaPropria
                ? (int) $ctes->sum('valor_total_centavos')
                : (int) ($contrato?->frete_centavos ?? $viagem->frete_motorista_centavos),
            inicio: $inicio,
            fim: $viagem->previsaoEntrega(),
            veiculos: $this->veiculos($viagem)->map(fn (Veiculo $v): array => [
                'placa' => strtoupper((string) $v->placa),
                'rntrc' => $v->deTerceiro() ? $this->rntrc($v->proprietario_rntrc) : $this->rntrc($transporte->rntrc),
                'eixos' => $v->eixos ? (int) $v->eixos : null,
                'automotor' => $v->tipo === 'tracao',
            ])->all(),
            municipioOrigem: $principal?->municipio_origem_codigo,
            municipioDestino: $principal?->municipio_destino_codigo,
            distanciaKm: $viagem->distancia_km ?: null,
            naturezaCarga: $this->naturezaCarga($viagem),
            pesoKg: $viagem->pesoTotalKg(),
            tipoCarga: (int) $viagem->tipo_carga,
            contratantesFracionada: $tipo === '2' ? $tomadores->all() : [],
            pagamento: $frotaPropria ? $this->recebimento($viagem, $ctes, $inicio) : $this->pagamentoTerceiro($viagem, $contrato, $inicio),
            altoDesempenho: (bool) $viagem->alto_desempenho,
            retornoVazio: (bool) $viagem->retorno_vazio,
            composicaoVeicular: $viagem->reboque !== null || $viagem->reboque2 !== null,
        );
    }

    /** @return array<int, string> */
    public function pendencias(Viagem $viagem): array
    {
        $viagem->loadMissing(['emitente', 'ctes', 'notas', 'veiculo', 'reboque', 'reboque2', 'contrato']);
        $pendencias = [];

        if (! $viagem->distancia_km) {
            $pendencias[] = 'Informe a distância da viagem em km, em Dados do CIOT.';
        }

        // DCS, regra B21: no máximo 90 dias entre o início e o fim.
        $inicio = ($viagem->data_carregamento ?? today())->copy()->startOfDay();
        $fim = $viagem->previsaoEntrega()->copy()->startOfDay();
        if ($fim->lt($inicio) || $inicio->diffInDays($fim) > 90) {
            $pendencias[] = 'A previsão de entrega precisa ficar entre o carregamento e 90 dias depois dele.';
        }

        // DCS, regra B101: automotor de 2 a 4 eixos, implemento de 1 a 4.
        foreach ($this->veiculos($viagem) as $veiculo) {
            $minimo = $veiculo->tipo === 'tracao' ? 2 : 1;
            if ((int) $veiculo->eixos < $minimo || (int) $veiculo->eixos > 4) {
                $pendencias[] = "Informe os eixos do veículo {$veiculo->placaFormatada()} (2 a 4 no cavalo ou caminhão, 1 a 4 na carreta), em Veículos.";
            }
        }

        // DCS, regra B117: cavalo mecânico precisa de implemento.
        if ($viagem->veiculo?->tipo_rodado === '03' && $viagem->reboque === null && $viagem->reboque2 === null) {
            $pendencias[] = 'Cavalo mecânico precisa de ao menos uma carreta na viagem para o CIOT.';
        }

        if (strlen($this->naturezaCarga($viagem)) !== 4) {
            $pendencias[] = 'Nenhuma NF-e da viagem tem NCM: o CIOT precisa da natureza da carga.';
        }

        if ($viagem->comTerceiro()) {
            if ($viagem->contrato?->status !== 'ativo') {
                $pendencias[] = 'Preencha o contrato do frete com o terceiro.';
            }

            return $pendencias;
        }

        if ($this->tomadores($this->ctesValidos($viagem))->isEmpty()) {
            $pendencias[] = 'Os CT-e da viagem precisam ter tomador para o CIOT.';
        }
        $config = $viagem->emitente->configuracaoCiot();
        if ($config->recebimento_tipo === 'pix' && blank($viagem->emitente->chave_pix)) {
            $pendencias[] = 'Informe a chave Pix da empresa em Configurações, Empresa, ou escolha outra forma de recebimento em Configurações, CIOT.';
        }
        if ($config->recebimento_tipo === 'transferencia'
            && (strlen((string) $config->recebimento_banco) !== 3 || blank($config->recebimento_agencia) || blank($config->recebimento_conta))) {
            $pendencias[] = 'Informe banco, agência e conta de recebimento em Configurações, CIOT.';
        }

        return $pendencias;
    }

    /** @return Collection<int, Cte> */
    private function ctesValidos(Viagem $viagem): Collection
    {
        return $viagem->ctes->reject(fn (Cte $c): bool => $c->status === CteStatus::Cancelado)->values();
    }

    /** @return Collection<int, string> */
    private function tomadores(Collection $ctes): Collection
    {
        return $ctes->map(fn (Cte $c): string => $this->digitos($c->tomador()['documento'] ?? null))->filter()->unique()->values();
    }

    /** @return Collection<int, Veiculo> */
    private function veiculos(Viagem $viagem): Collection
    {
        return collect([$viagem->veiculo, $viagem->reboque, $viagem->reboque2])->filter()->values();
    }

    /** A nota mais pesada fala pela carga: mesma regra que o e-Frete já usava. */
    private function notaPrincipal(Viagem $viagem): ?ViagemNota
    {
        return $viagem->notas->sortByDesc(fn (ViagemNota $n): float => (float) $n->peso_kg)->first();
    }

    private function naturezaCarga(Viagem $viagem): string
    {
        $nota = $viagem->notas
            ->sortByDesc(fn (ViagemNota $n): float => (float) $n->peso_kg)
            ->first(fn (ViagemNota $n): bool => strlen($this->digitos($n->ncm)) >= 4);

        return $nota ? substr($this->digitos($nota->ncm), 0, 4) : '';
    }

    /**
     * Frota própria: como o cliente paga o frete à transportadora. A prazo,
     * uma parcela com o vencimento da fatura; prazo zero é à vista.
     */
    private function recebimento(Viagem $viagem, Collection $ctes, CarbonInterface $inicio): array
    {
        $config = $viagem->emitente->configuracaoCiot();
        $prazo = (int) $viagem->emitente->configuracaoTransporte()->prazo_fatura_dias;
        $valor = (int) $ctes->sum('valor_total_centavos');
        $tipo = match ($config->recebimento_tipo) {
            'transferencia' => 2,
            'boleto' => 5,
            default => 6,
        };

        return [
            'tipo' => $tipo,
            'credor' => (string) $viagem->emitente->cnpj,
            'chave_pix' => $tipo === 6 ? $viagem->emitente->chave_pix : null,
            'banco' => $tipo === 2 ? $config->recebimento_banco : null,
            'agencia' => $tipo === 2 ? $config->recebimento_agencia : null,
            'conta' => $tipo === 2 ? $config->recebimento_conta : null,
            'a_prazo' => $prazo > 0,
            'parcelas' => $prazo > 0
                ? [['numero' => 1, 'vencimento' => $inicio->copy()->addDays($prazo)->toDateString(), 'valor_centavos' => $valor]]
                : [],
        ];
    }

    /** Terceiro: o que o contrato combinou, adiantamento e saldo. */
    private function pagamentoTerceiro(Viagem $viagem, ?ContratoFrete $contrato, CarbonInterface $inicio): array
    {
        $pix = ($contrato?->forma_pagamento ?? 'pix') === 'pix';
        $parcelas = collect([
            [$contrato?->adiantamento_centavos ?? 0, $inicio->toDateString()],
            [$contrato?->saldo_centavos ?? 0, $contrato?->vencimento_saldo?->toDateString() ?? $inicio->toDateString()],
        ])->filter(fn (array $p): bool => $p[0] > 0)->values()
            ->map(fn (array $p, int $i): array => ['numero' => $i + 1, 'vencimento' => $p[1], 'valor_centavos' => (int) $p[0]])
            ->all();

        return [
            'tipo' => $pix ? 6 : 2,
            'credor' => $this->digitos($contrato?->contratado_documento ?? $viagem->veiculo?->proprietario_documento),
            'chave_pix' => $pix ? $contrato?->chave_pix : null,
            'banco' => $pix ? null : $contrato?->banco_codigo,
            'agencia' => $pix ? null : $contrato?->agencia,
            'conta' => $pix ? null : $contrato?->conta,
            'a_prazo' => $parcelas !== [],
            'parcelas' => $parcelas,
        ];
    }

    /** DCS, regra B60: RNTRC de 8 dígitos ganha um zero à esquerda. */
    private function rntrc(?string $valor): ?string
    {
        $digitos = $this->digitos($valor);

        return $digitos === '' ? null : str_pad($digitos, 9, '0', STR_PAD_LEFT);
    }

    private function digitos(?string $valor): string
    {
        return (string) preg_replace('/\D/', '', (string) $valor);
    }
}
