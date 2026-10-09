<?php

/**
 * CIOT para todos (Res. ANTT 6.078/2026, DF-026): toda viagem tem CIOT, e a
 * operação que vai para a empresa do CIOT sai da viagem, nos mesmos campos
 * que a ANTT define no DCS. Aqui ficam os dados e a montagem dessa operação;
 * o fluxo de emissão está em CiotParaTodosTest.
 */

use App\Enums\Fiscal\Ambiente;

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
