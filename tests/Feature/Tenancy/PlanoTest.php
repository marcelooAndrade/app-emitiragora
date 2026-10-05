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

it('tenant nasce no plano Transporte, o unico desde que o EmitirAgora e so de transportadora', function () {
    $t = Tenant::create(['nome' => 'Leme', 'slug' => 'leme']);

    expect($t->plano)->toBe(PlanoTenant::Transporte)
        ->and(PlanoTenant::cases())->toBe([PlanoTenant::Transporte]);
});

it('o preco do plano vem de config/planos.php', function () {
    expect(PlanoTenant::Transporte->precoCentavos())->toBe(49_900);
});

it('o plano aceita dominio proprio', function () {
    $t = Tenant::create([
        'nome' => 'RCM', 'slug' => 'rcm',
        'plano' => PlanoTenant::Transporte, 'dominio' => 'app.rcmdobrasil.com.br',
    ]);

    expect($t->fresh()->dominio)->toBe('app.rcmdobrasil.com.br');
});

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
