<?php

use App\Enums\PlanoTenant;
use App\Models\Tenant;
use App\Models\User;

function tenantHost(string $slug, string $host): Tenant
{
    return Tenant::create(['nome' => ucfirst($slug), 'slug' => $slug, 'dominio' => $host,
        'plano' => PlanoTenant::Transporte]);
}

it('guarda o tenant do usuario', function () {
    $tenant = tenantHost('rcm', 'rcm.test');

    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Marcelo',
        'email' => 'marcelo@rcm.test',
        'password' => 'senha-de-teste',
    ]);

    expect($user->fresh()->tenant_id)->toBe($tenant->id);
});

it('nao autentica usuario de outro tenant no host errado', function () {
    $rcm = tenantHost('rcm', 'rcm.test');
    $leme = tenantHost('leme', 'leme.test');

    User::create([
        'tenant_id' => $leme->id,
        'name' => 'Operador Leme',
        'email' => 'operador@leme.test',
        'password' => 'senha-de-teste',
        'email_verified_at' => now(),
    ]);

    // Credencial correta, host errado.
    $this->post('http://rcm.test/login', [
        'email' => 'operador@leme.test',
        'password' => 'senha-de-teste',
    ])->assertSessionHasErrors();

    $this->assertGuest();
})->skip('Domínio próprio de cliente desativado em 14/09/2026, ver TenantAtual::resolverHost');

it('autentica no host do proprio tenant', function () {
    $leme = tenantHost('leme', 'leme.test');

    User::create([
        'tenant_id' => $leme->id,
        'name' => 'Operador Leme',
        'email' => 'operador@leme.test',
        'password' => 'senha-de-teste',
        'email_verified_at' => now(),
    ]);

    $this->post('http://leme.test/login', [
        'email' => 'operador@leme.test',
        'password' => 'senha-de-teste',
    ]);

    $this->assertAuthenticated();
})->skip('Domínio próprio de cliente desativado em 14/09/2026, ver TenantAtual::resolverHost');
