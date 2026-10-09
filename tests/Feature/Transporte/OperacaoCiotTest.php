<?php

/**
 * CIOT para todos (Res. ANTT 6.078/2026, DF-026): toda viagem tem CIOT, e a
 * operação que vai para a empresa do CIOT sai da viagem, nos mesmos campos
 * que a ANTT define no DCS. Aqui ficam os dados e a montagem dessa operação;
 * o fluxo de emissão está em CiotParaTodosTest.
 */

use App\Enums\Fiscal\Ambiente;
use App\Models\Veiculo;
use App\Models\Viagem;
use App\Services\Transporte\Ciot\MontadorOperacaoCiot;
use App\Services\Transporte\ContratosFrete;
use App\Services\Transporte\TransmissorCte;

it('sugere lotação com um tomador e respeita o tipo informado na tela', function () {
    $viagem = viagemPronta();
    expect($viagem->fresh('ctes')->tipoOperacao())->toBe('1');

    $viagem->update(['tipo_operacao' => '2']);
    expect($viagem->fresh('ctes')->tipoOperacao())->toBe('2');
});

it('prevê a entrega três dias depois do carregamento quando ninguém informa', function () {
    $viagem = viagemPronta(dadosViagem: ['data_carregamento' => '2026-10-09']);

    expect($viagem->previsaoEntrega()->toDateString())->toBe('2026-10-12');

    $viagem->update(['previsao_entrega' => '2026-10-20']);
    expect($viagem->fresh()->previsaoEntrega()->toDateString())->toBe('2026-10-20');
});

it('guarda credenciais por empresa e por ambiente, cifradas', function () {
    $config = transportadora()->configuracaoCiot();
    $config->guardarCredenciais('efrete', Ambiente::Homologacao, ['usuario' => 'u', 'senha' => 's']);
    $config->guardarCredenciais('strada', Ambiente::Homologacao, ['token' => 't']);

    $config = $config->fresh();
    expect($config->provedor)->toBe('manual')
        ->and($config->credenciais('efrete', Ambiente::Homologacao))->toBe(['usuario' => 'u', 'senha' => 's'])
        ->and($config->credenciais('strada', Ambiente::Homologacao))->toBe(['token' => 't'])
        ->and($config->credenciais('efrete', Ambiente::Producao))->toBe([])
        ->and($config->getRawOriginal('credenciais_homologacao'))->not->toContain('usuario');
});

it('o CIOT vigente ignora recusados e cancelados', function () {
    $viagem = viagemPronta();
    $base = ['emitente_id' => $viagem->emitente_id, 'provedor' => 'manual', 'ambiente' => Ambiente::Homologacao];
    $viagem->ciots()->create([...$base, 'situacao' => 'recusado']);
    $vigente = $viagem->ciots()->create([...$base, 'situacao' => 'registrado', 'numero' => '123456789012', 'verificador' => '4321']);
    $viagem->ciots()->create([...$base, 'situacao' => 'cancelado']);

    expect($viagem->fresh()->ciotVigente->is($vigente))->toBeTrue()
        ->and($vigente->registrado())->toBeTrue()
        ->and($vigente->numeroCompleto())->toBe('123456789012/4321');
});

function viagemComCteAutorizado(): Viagem
{
    $viagem = viagemPronta();
    comGatewayCte(['enviar' => cteAutorizado()]);
    app(TransmissorCte::class)->transmitir($viagem->fresh()->ctes->sole());

    return $viagem->fresh();
}

it('frota própria: a transportadora é a contratada e o tomador é o contratante', function () {
    $viagem = viagemComCteAutorizado();
    $cte = $viagem->ctes->sole();

    $op = app(MontadorOperacaoCiot::class)->montar($viagem);

    expect($op->frotaPropria)->toBeTrue()
        ->and($op->contratadoDocumento)->toBe($viagem->emitente->cnpj)
        ->and($op->contratadoRntrc)->toBe('012345678')
        ->and($op->contratanteDocumento)->toBe(preg_replace('/\D/', '', (string) $cte->tomador()['documento']))
        ->and($op->valorFreteCentavos)->toBe($cte->valor_total_centavos)
        ->and($op->naturezaCarga)->toHaveLength(4)
        ->and($op->tipoOperacao)->toBe('1')
        ->and($op->composicaoVeicular)->toBeFalse()
        ->and($op->veiculos)->toBe([['placa' => 'ABC1D23', 'rntrc' => '012345678', 'eixos' => null, 'automotor' => true]])
        ->and($op->pagamento['credor'])->toBe($viagem->emitente->cnpj);
});

it('terceiro TAC: o proprietário é o contratado e a transportadora contrata', function () {
    $viagem = viagemComCteAutorizado();
    $viagem->veiculo->update(['proprietario_tipo' => 'terceiro', 'proprietario_documento' => '52998224725',
        'proprietario_nome' => 'JOSE', 'proprietario_rntrc' => '87654321', 'proprietario_tp' => '1']);
    app(ContratosFrete::class)->salvar($viagem->fresh(), [
        'frete_centavos' => 100000, 'adiantamento_centavos' => 80000,
        'vencimento_saldo' => today()->addDays(7)->toDateString(), 'forma_pagamento' => 'pix', 'chave_pix' => 'jose@exemplo.com',
    ]);

    $op = app(MontadorOperacaoCiot::class)->montar($viagem->fresh());

    expect($op->frotaPropria)->toBeFalse()
        ->and($op->contratadoDocumento)->toBe('52998224725')
        ->and($op->contratadoRntrc)->toBe('087654321')
        ->and($op->contratanteDocumento)->toBe($viagem->emitente->cnpj)
        ->and($op->valorFreteCentavos)->toBe(100000)
        ->and($op->pagamento['credor'])->toBe('52998224725')
        ->and($op->pagamento['parcelas'])->toHaveCount(2);
});

it('fracionada leva os tomadores em contratantes da carga fracionada', function () {
    $viagem = viagemComCteAutorizado();
    $viagem->update(['tipo_operacao' => '2']);

    $op = app(MontadorOperacaoCiot::class)->montar($viagem->fresh());

    expect($op->tipoOperacao)->toBe('2')->and($op->contratantesFracionada)->toHaveCount(1);
});

it('lista o que falta para declarar o CIOT, e nada quando está completo', function () {
    $viagem = viagemComCteAutorizado();
    $montador = app(MontadorOperacaoCiot::class);

    $pendencias = implode(' ', $montador->pendencias($viagem));
    expect($pendencias)->toContain('distância')
        ->and($pendencias)->toContain('eixos do veículo ABC-1D23')
        ->and($pendencias)->toContain('carreta')
        ->and($pendencias)->toContain('chave Pix');

    $carreta = (new Veiculo(['tipo' => 'reboque', 'placa' => 'CAR1E23', 'uf' => 'SP', 'tara_kg' => 6000,
        'tipo_carroceria' => '02', 'eixos' => 3]))->forceFill(['emitente_id' => $viagem->emitente_id]);
    $carreta->save();
    $viagem->veiculo->update(['eixos' => 3]);
    $viagem->update(['distancia_km' => 180, 'reboque_id' => $carreta->id]);
    $viagem->emitente->update(['chave_pix' => 'financeiro@transportadora.com.br']);

    expect($montador->pendencias($viagem->fresh()))->toBe([]);
});

it('previsão de entrega além de 90 dias do carregamento é pendência', function () {
    $viagem = viagemComCteAutorizado();
    $viagem->update(['previsao_entrega' => $viagem->data_carregamento->copy()->addDays(91)]);

    expect(implode(' ', app(MontadorOperacaoCiot::class)->pendencias($viagem->fresh())))
        ->toContain('90 dias');
});
