<?php

namespace App\Services\Transporte;

use App\Enums\Transporte\CteStatus;
use App\Enums\Transporte\MdfeStatus;
use App\Models\ContaPagar;
use App\Models\ContratoFrete;
use App\Models\Pessoa;
use App\Models\User;
use App\Models\Viagem;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Contrato do frete com o motorista terceiro e as contas a pagar dele.
 *
 * Junta o `ContratoMotorista` e o `FiscalDriverPayableService` do app-transm:
 * o contrato guarda o combinado (frete, adiantamento, descontos, saldo) e
 * cada parte vira uma conta a pagar no financeiro. Uma conta já paga nunca é
 * mexida, como lá.
 */
class ContratosFrete
{
    /** Pagamento por Pix ou transferência, que é o que o MDF-e aceita. */
    public const FORMAS = ['pix' => 'Pix', 'transferencia' => 'Transferência bancária'];

    /** O que a tela mostra antes de existir contrato: o padrão da empresa. */
    public function sugestao(Viagem $viagem): array
    {
        $viagem->loadMissing(['emitente', 'motorista', 'contrato']);
        if ($viagem->contrato !== null) {
            $c = $viagem->contrato;

            return [
                'frete_centavos' => $c->frete_centavos,
                'adiantamento_centavos' => $c->adiantamento_centavos,
                'imposto_renda_centavos' => $c->imposto_renda_centavos,
                'falta_mercadoria_centavos' => $c->falta_mercadoria_centavos,
                'seguro_motorista_centavos' => $c->seguro_motorista_centavos,
                'seguro_carga_centavos' => $c->seguro_carga_centavos,
                'vencimento_saldo' => $c->vencimento_saldo->toDateString(),
                'forma_pagamento' => $c->forma_pagamento,
                'chave_pix' => $c->chave_pix,
                'banco_codigo' => $c->banco_codigo,
                'agencia' => $c->agencia,
                'conta' => $c->conta,
                'ciot' => $c->ciot,
            ];
        }

        $frete = (int) $viagem->frete_motorista_centavos;

        return [
            'frete_centavos' => $frete,
            'adiantamento_centavos' => $viagem->adiantamento_centavos ?: $this->adiantamentoPadrao($viagem, $frete),
            'imposto_renda_centavos' => 0,
            'falta_mercadoria_centavos' => 0,
            'seguro_motorista_centavos' => 0,
            'seguro_carga_centavos' => 0,
            'vencimento_saldo' => $this->dataBase($viagem)->addDays(7)->toDateString(),
            'forma_pagamento' => 'pix',
            'chave_pix' => $viagem->motorista?->chave_pix,
            'banco_codigo' => null,
            'agencia' => null,
            'conta' => null,
            'ciot' => null,
        ];
    }

    /** Arredondado como no Transm: 80% de R$ 1.000,05 dá R$ 800,04. */
    public function adiantamentoPadrao(Viagem $viagem, int $freteCentavos): int
    {
        $percentual = (int) $viagem->emitente->configuracaoTransporte()->adiantamento_percentual;

        return intdiv(($freteCentavos * $percentual) + 50, 100);
    }

    /**
     * Grava o contrato e acerta as contas a pagar.
     *
     * @param  array<string, mixed>  $dados  valores em centavos, datas em Y-m-d
     */
    public function salvar(Viagem $viagem, array $dados, ?User $user = null): ContratoFrete
    {
        $viagem->load(['emitente', 'motorista', 'veiculo', 'reboque', 'reboque2', 'mdfe', 'contrato']);
        $this->conferir($viagem, $dados);

        return DB::transaction(function () use ($viagem, $dados, $user): ContratoFrete {
            $veiculo = $viagem->veiculo;
            $frete = (int) $dados['frete_centavos'];
            $adiantamento = (int) ($dados['adiantamento_centavos'] ?? 0);
            $descontos = (int) ($dados['imposto_renda_centavos'] ?? 0) + (int) ($dados['falta_mercadoria_centavos'] ?? 0)
                + (int) ($dados['seguro_motorista_centavos'] ?? 0) + (int) ($dados['seguro_carga_centavos'] ?? 0);
            $forma = ($dados['forma_pagamento'] ?? 'pix') === 'transferencia' ? 'transferencia' : 'pix';

            $contrato = $viagem->contrato ?? new ContratoFrete;
            $contrato->forceFill([
                'emitente_id' => $viagem->emitente_id,
                'viagem_id' => $viagem->getKey(),
                'numero' => $viagem->numeroFormatado(),
                'status' => 'ativo',
                'contratado_documento' => preg_replace('/\D/', '', (string) $veiculo->proprietario_documento),
                'contratado_nome' => mb_substr((string) $veiculo->proprietario_nome, 0, 60),
                'contratado_rntrc' => str_pad((string) $veiculo->proprietario_rntrc, 8, '0', STR_PAD_LEFT),
                'contratado_tp' => $veiculo->proprietario_tp,
                'motorista_nome' => mb_substr((string) $viagem->motorista->nome, 0, 60),
                'motorista_cpf' => $viagem->motorista->cpf,
                'placas' => collect([$veiculo, $viagem->reboque, $viagem->reboque2])->filter()->map->placa->join(' / '),
                'frete_centavos' => $frete,
                'adiantamento_centavos' => $adiantamento,
                'imposto_renda_centavos' => (int) ($dados['imposto_renda_centavos'] ?? 0),
                'falta_mercadoria_centavos' => (int) ($dados['falta_mercadoria_centavos'] ?? 0),
                'seguro_motorista_centavos' => (int) ($dados['seguro_motorista_centavos'] ?? 0),
                'seguro_carga_centavos' => (int) ($dados['seguro_carga_centavos'] ?? 0),
                'saldo_centavos' => $frete - $adiantamento - $descontos,
                'vencimento_saldo' => Carbon::parse((string) $dados['vencimento_saldo'])->toDateString(),
                'forma_pagamento' => $forma,
                'chave_pix' => $forma === 'pix' ? trim((string) ($dados['chave_pix'] ?? '')) : null,
                'banco_codigo' => $forma === 'transferencia' ? preg_replace('/\D/', '', (string) ($dados['banco_codigo'] ?? '')) : null,
                'agencia' => $forma === 'transferencia' ? trim((string) ($dados['agencia'] ?? '')) : null,
                'conta' => $forma === 'transferencia' ? trim((string) ($dados['conta'] ?? '')) : null,
                'ciot' => filled($dados['ciot'] ?? null) ? preg_replace('/\D/', '', (string) $dados['ciot']) : null,
                'emitido_em' => $contrato->emitido_em ?? now(),
            ])->save();

            // Os campos da viagem acompanham o contrato, para quem lê só a viagem.
            $viagem->forceFill(['frete_motorista_centavos' => $frete, 'adiantamento_centavos' => $adiantamento])->save();
            $this->sincronizarContas($contrato->setRelation('viagem', $viagem));
            $viagem->registrar('contrato_salvo', "Contrato de frete com {$contrato->contratado_nome}: frete ".$this->reais($frete).', adiantamento '.$this->reais($adiantamento).', saldo '.$this->reais($contrato->saldo_centavos).'.', null, $user?->getKey());

            return $contrato->fresh();
        });
    }

    /**
     * Sem CT-e autorizado não há frete a pagar: as contas pendentes caem.
     * É o `cancelWhenProcessHasNoAuthorizedCte` do Transm.
     */
    public function cancelarSeSemCte(Viagem $viagem): void
    {
        $viagem->loadMissing(['ctes', 'contrato']);
        $contrato = $viagem->contrato;
        if ($contrato === null || $contrato->status === 'cancelado'
            || $viagem->ctes->contains(fn ($c): bool => $c->status === CteStatus::Autorizado)) {
            return;
        }

        DB::transaction(function () use ($contrato, $viagem): void {
            ContaPagar::query()
                ->whereIn('id', array_filter([$contrato->conta_pagar_adiantamento_id, $contrato->conta_pagar_saldo_id]))
                ->where('status', 'pendente')
                ->update(['status' => 'cancelado', 'updated_at' => now()]);
            $contrato->forceFill(['status' => 'cancelado'])->save();
            $viagem->registrar('contrato_cancelado', 'Contrato de frete cancelado junto com os CT-e. As contas a pagar ainda não pagas foram canceladas.');
        });
    }

    /** O contrato entra no MDF-e: depois dele autorizado, não muda mais. */
    public function travado(Viagem $viagem): bool
    {
        return in_array($viagem->mdfe?->status, [MdfeStatus::Autorizado, MdfeStatus::Encerrado, MdfeStatus::EmProcessamento], true);
    }

    private function conferir(Viagem $viagem, array $dados): void
    {
        if (! $viagem->comTerceiro()) {
            throw new TransporteException('Contrato de frete é para veículo de terceiro. Esta viagem usa veículo próprio.');
        }
        if ($this->travado($viagem)) {
            throw new TransporteException('O MDF-e desta viagem já foi para a SEFAZ com o contrato. Para mudar, cancele o MDF-e.');
        }
        if ($viagem->motorista === null) {
            throw new TransporteException('Escolha o motorista antes do contrato.');
        }
        $veiculo = $viagem->veiculo;
        if (blank($veiculo->proprietario_documento) || blank($veiculo->proprietario_nome) || blank($veiculo->proprietario_rntrc)) {
            throw new TransporteException('Complete o proprietário do veículo '.$veiculo->placaFormatada().' (documento, nome e RNTRC) em Veículos.');
        }

        $frete = (int) ($dados['frete_centavos'] ?? 0);
        $adiantamento = (int) ($dados['adiantamento_centavos'] ?? 0);
        $descontos = (int) ($dados['imposto_renda_centavos'] ?? 0) + (int) ($dados['falta_mercadoria_centavos'] ?? 0)
            + (int) ($dados['seguro_motorista_centavos'] ?? 0) + (int) ($dados['seguro_carga_centavos'] ?? 0);
        if ($frete <= 0) {
            throw new TransporteException('Informe quanto o motorista vai receber de frete.');
        }
        if ($adiantamento < 0 || $adiantamento > $frete) {
            throw new TransporteException('O adiantamento não pode passar do frete do motorista.');
        }
        if ($adiantamento + $descontos > $frete) {
            throw new TransporteException('Adiantamento e descontos passam do frete: o saldo ficaria negativo.');
        }
        if (blank($dados['vencimento_saldo'] ?? null)) {
            throw new TransporteException('Informe quando o saldo vai ser pago.');
        }
        if (($dados['forma_pagamento'] ?? 'pix') === 'transferencia') {
            if (strlen(preg_replace('/\D/', '', (string) ($dados['banco_codigo'] ?? ''))) !== 3 || blank($dados['agencia'] ?? null)) {
                throw new TransporteException('Para transferência, informe o banco (3 dígitos) e a agência.');
            }
        } elseif (blank($dados['chave_pix'] ?? null)) {
            throw new TransporteException('Informe a chave Pix de quem recebe o frete.');
        } elseif (mb_strlen(trim((string) $dados['chave_pix'])) > 60) {
            throw new TransporteException('A chave Pix tem no máximo 60 caracteres no MDF-e.');
        }
        $ciot = preg_replace('/\D/', '', (string) ($dados['ciot'] ?? ''));
        if ($ciot !== '' && strlen($ciot) !== 12) {
            throw new TransporteException('O CIOT tem 12 dígitos.');
        }
    }

    /** Adiantamento e saldo como contas a pagar, sem tocar no que já foi pago. */
    private function sincronizarContas(ContratoFrete $contrato): void
    {
        $viagem = $contrato->viagem;
        $pessoa = Pessoa::query()
            ->where('emitente_id', $contrato->emitente_id)
            ->where('documento', $contrato->contratado_documento)
            ->value('id');
        $partes = [
            'conta_pagar_adiantamento_id' => ['Adiantamento', $contrato->adiantamento_centavos, $this->dataBase($viagem)],
            'conta_pagar_saldo_id' => ['Saldo', $contrato->saldo_centavos, $contrato->vencimento_saldo],
        ];

        foreach ($partes as $coluna => [$rotulo, $valor, $vencimento]) {
            $conta = $contrato->{$coluna} ? ContaPagar::query()->find($contrato->{$coluna}) : null;
            if ($conta !== null && $conta->status === 'pago') {
                continue;
            }
            if ($valor <= 0) {
                $conta?->forceFill(['status' => 'cancelado'])->save();

                continue;
            }

            $conta ??= new ContaPagar;
            $conta->forceFill([
                'emitente_id' => $contrato->emitente_id,
                'pessoa_id' => $pessoa,
                'descricao' => mb_substr("{$rotulo} do frete · viagem {$viagem->numeroFormatado()} · {$contrato->placas}", 0, 160),
                'fornecedor' => $contrato->contratado_nome,
                'valor_centavos' => $valor,
                'vencimento' => Carbon::parse($vencimento)->toDateString(),
                'status' => 'pendente',
                'observacoes' => "Gerada pelo contrato de frete {$contrato->numero}."
                    .($contrato->forma_pagamento === 'pix' ? " Pix: {$contrato->chave_pix}." : " Banco {$contrato->banco_codigo}, agência {$contrato->agencia}, conta {$contrato->conta}."),
            ])->save();
            $contrato->forceFill([$coluna => $conta->getKey()]);
        }

        $contrato->save();
    }

    private function dataBase(Viagem $viagem): CarbonInterface
    {
        return ($viagem->data_carregamento ?? today())->copy();
    }

    private function reais(int $centavos): string
    {
        return 'R$ '.number_format($centavos / 100, 2, ',', '.');
    }
}
