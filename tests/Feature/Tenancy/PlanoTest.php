<?php

use App\Enums\PlanoTenant;
use App\Models\Tenant;

/**
 * O plano é gravado, e não deduzido do host.
 *
 * Deduzir pelo domínio funcionaria enquanto ninguém cancelasse: o plano cai,
 * o DNS continua apontado, e o cliente seguiria com o benefício.
 */
beforeEach(fn () => Tenant::query()->delete());

it('tenant nasce no plano Transporte, que libera tudo', function () {
    $t = Tenant::create(['nome' => 'Leme', 'slug' => 'leme']);

    expect($t->plano)->toBe(PlanoTenant::Transporte)
        ->and($t->plano->permiteTransporte())->toBeTrue();
});

it('Pequena Empresa nao libera o transporte', function () {
    expect(PlanoTenant::PequenaEmpresa->permiteTransporte())->toBeFalse();
});

it('o plano sugerido vem do tipo de empresa respondido no cadastro', function (?array $perfil, PlanoTenant $esperado) {
    expect(PlanoTenant::paraPerfil($perfil))->toBe($esperado);
})->with([
    'sem resposta' => [null, PlanoTenant::Transporte],
    'transportadora' => [['tipo' => 'transportadora'], PlanoTenant::Transporte],
    'empresa sem frota' => [['tipo' => 'empresa'], PlanoTenant::PequenaEmpresa],
    'contabilidade' => [['tipo' => 'contabilidade'], PlanoTenant::PequenaEmpresa],
    'outro' => [['tipo' => 'outro'], PlanoTenant::PequenaEmpresa],
    'so frota, sem tipo' => [['frota' => '1-5'], PlanoTenant::Transporte],
]);

it('o preco de cada plano vem de config/planos.php', function () {
    expect(PlanoTenant::Transporte->precoCentavos())->toBe(49_900)
        ->and(PlanoTenant::PequenaEmpresa->precoCentavos())->toBe(9_900);
});

it('os dois planos aceitam dominio proprio', function (PlanoTenant $plano) {
    $t = Tenant::create([
        'nome' => 'RCM', 'slug' => 'rcm',
        'plano' => $plano, 'dominio' => 'app.rcmdobrasil.com.br',
    ]);

    expect($t->fresh()->dominio)->toBe('app.rcmdobrasil.com.br');
})->with([PlanoTenant::Transporte, PlanoTenant::PequenaEmpresa]);

it('no dominio do produto a porta e do EmitirAgora, mesmo havendo cliente com logo', function () {
    // Nenhum tenant é resolvido pelo host do produto, então não há marca de
    // cliente a mostrar.
    $t = Tenant::create(['nome' => 'Leme', 'slug' => 'leme']);
    $t->forceFill(['logo_path' => 'marca/tenant/leme.png'])->saveQuietly();

    $this->get('http://vendaredonda.com.br/login')
        ->assertOk()
        ->assertSee('EmitirAgora')
        ->assertDontSee(route('logo'));
});

it('no dominio do cliente a marca dele abre a porta', function () {
    $rcm = Tenant::create([
        'nome' => 'RCM', 'slug' => 'rcm',
        'plano' => PlanoTenant::Transporte, 'dominio' => 'app.rcmdobrasil.com.br',
    ]);
    $rcm->forceFill(['logo_path' => 'marca/tenant/rcm.png'])->saveQuietly();

    $this->get('http://app.rcmdobrasil.com.br/login')
        ->assertOk()
        ->assertSee('src="'.route('logo').'"', false);
})->skip('Domínio próprio de cliente desativado em 14/09/2026, ver TenantAtual::resolverHost');
