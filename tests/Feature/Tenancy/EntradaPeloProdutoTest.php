<?php

use App\Enums\PlanoTenant;
use App\Models\Emitente;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantAtual;

/**
 * O sistema mora no domínio do produto, por caminho. Lá nenhum tenant é
 * resolvido pelo host, então quem diz de quem é a sessão é o próprio usuário.
 *
 * A restrição por host continua valendo onde ela faz sentido: no domínio
 * próprio de um cliente. Ver LoginEntreTenantsTest.
 */
function clienteCom(string $slug, ?string $host = null): Tenant
{
    return Tenant::create(['nome' => ucfirst($slug), 'slug' => $slug, 'dominio' => $host,
        'plano' => $host === null ? PlanoTenant::PequenaEmpresa : PlanoTenant::Transporte]);
}

function usuarioDe(Tenant $t, string $email): User
{
    $user = User::withoutGlobalScopes()->create([
        'tenant_id' => $t->id,
        'name' => 'Operador',
        'email' => $email,
        'password' => 'senha-de-teste',
        'email_verified_at' => now(),
    ]);

    // Sem isto o usuário não tem como o tenant ser derivado dele: o tenant
    // da sessão agora segue o emitente resolvido, não mais `tenant_id` cru.
    $user->emitentes()->attach(Emitente::factory()->create(['tenant_id' => $t->id]));

    return $user;
}

beforeEach(function () {
    Tenant::query()->delete();
    config(['produto.dominio' => 'vendaredonda.com.br']);
});

it('usuario de qualquer cliente entra pelo dominio do produto', function () {
    $leme = clienteCom('leme');
    usuarioDe($leme, 'operador@leme.test');

    $this->post('http://vendaredonda.com.br/login', [
        'email' => 'operador@leme.test',
        'password' => 'senha-de-teste',
    ]);

    $this->assertAuthenticated();
});

it('depois de entrar, o contexto e o tenant do usuario', function () {
    $leme = clienteCom('leme');
    $user = usuarioDe($leme, 'operador@leme.test');

    $this->actingAs($user)->get('http://vendaredonda.com.br/dashboard');

    expect(app(TenantAtual::class)->id())->toBe($leme->id);
});

it('o tenant vem do host, e nao do usuario, quando os dois discordam', function () {
    // Até 14/09/2026 valia o oposto: o tenant do usuário vencia o do host,
    // mesmo em domínio próprio de cliente. Mudou junto da gestão de
    // usuários, porque ali um login passou a poder alcançar mais de uma
    // empresa, e nesse cenário o domínio de um cliente não pode depender de
    // qual sessão está por trás para decidir de quem são os dados.
    $leme = clienteCom('leme');
    clienteCom('rcm', 'app.rcmdobrasil.com.br');
    $user = usuarioDe($leme, 'operador@leme.test');

    $this->actingAs($user)->get('http://app.rcmdobrasil.com.br/dashboard');

    expect(app(TenantAtual::class)->id())->toBe(Tenant::where('dominio', 'app.rcmdobrasil.com.br')->value('id'));
})->skip('Domínio próprio de cliente desativado em 14/09/2026, ver TenantAtual::resolverHost');

it('visitante no dominio do produto nao tem tenant', function () {
    clienteCom('leme');

    $this->get('http://vendaredonda.com.br/login')->assertOk();

    expect(app(TenantAtual::class)->id())->toBeNull();
});
