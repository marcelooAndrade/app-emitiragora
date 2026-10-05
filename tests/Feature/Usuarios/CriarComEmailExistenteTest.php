<?php

use App\Enums\Perfil;
use App\Livewire\Usuarios\Cadastro;
use App\Models\Emitente;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PerfilSeeder;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

// Cada lado precisa do próprio tenant, de propósito: sem isso, dois
// `Emitente::factory()->create()` sem tenant explícito caem no mesmo tenant
// "teste" ambiente do TestCase, e o emitente "de outra empresa" passaria a
// contar como desta empresa em `emitentesDaEmpresa()`.
it('anexa e-mail existente de outra empresa, sem criar conta nova', function () {
    test()->seed(PerfilSeeder::class);
    $tenantExistente = Tenant::create(['nome' => 'Empresa Existente', 'slug' => 'existente-'.uniqid()]);
    $emitenteExistente = Emitente::factory()->create(['tenant_id' => $tenantExistente->id]);
    $pessoa = User::factory()->create(['tenant_id' => $emitenteExistente->tenant_id, 'name' => 'Fulano']);
    $pessoa->emitentes()->attach($emitenteExistente);

    $tenantNovo = Tenant::create(['nome' => 'Empresa Nova', 'slug' => 'nova-'.uniqid()]);
    $emitenteNovo = Emitente::factory()->create(['tenant_id' => $tenantNovo->id]);
    $admin = User::factory()->create(['tenant_id' => $emitenteNovo->tenant_id]);
    $admin->emitentes()->attach($emitenteNovo);
    app(PermissionRegistrar::class)->setPermissionsTeamId($emitenteNovo->getKey());
    $admin->assignRole(Perfil::Administrador->value);
    test()->actingAs($admin);

    Livewire::test(Cadastro::class)
        ->set('form.email', $pessoa->email)
        ->call('verificarEmail')
        ->assertSet('contaExistente', true)
        ->set("papeis.{$emitenteNovo->id}", Perfil::Contador->value)
        ->call('salvar')
        ->assertHasNoErrors();

    // Sem escopo de tenant: pessoa e admin estão em empresas diferentes de
    // propósito, e User::count() sozinho contaria só a do tenant em foco.
    expect(User::withoutGlobalScopes()->count())->toBe(2) // pessoa + admin, nenhuma terceira criada
        ->and($pessoa->fresh()->podeAcessar($emitenteExistente))->toBeTrue()
        ->and($pessoa->fresh()->podeAcessar($emitenteNovo))->toBeTrue();
});

it('nao mostra em qual outra empresa o e-mail ja esta', function () {
    test()->seed(PerfilSeeder::class);
    $tenantExistente = Tenant::create(['nome' => 'Empresa Sigilosa', 'slug' => 'sigilosa-'.uniqid()]);
    $emitenteExistente = Emitente::factory()->create(['tenant_id' => $tenantExistente->id, 'razao_social' => 'Empresa Sigilosa Ltda']);
    $pessoa = User::factory()->create(['tenant_id' => $emitenteExistente->tenant_id]);
    $pessoa->emitentes()->attach($emitenteExistente);

    $tenantNovo = Tenant::create(['nome' => 'Empresa Nova', 'slug' => 'nova-'.uniqid()]);
    $emitenteNovo = Emitente::factory()->create(['tenant_id' => $tenantNovo->id]);
    $admin = User::factory()->create(['tenant_id' => $emitenteNovo->tenant_id]);
    $admin->emitentes()->attach($emitenteNovo);
    app(PermissionRegistrar::class)->setPermissionsTeamId($emitenteNovo->getKey());
    $admin->assignRole(Perfil::Administrador->value);
    test()->actingAs($admin);

    $componente = Livewire::test(Cadastro::class)
        ->set('form.email', $pessoa->email)
        ->call('verificarEmail');

    expect($componente->html())->not->toContain('Empresa Sigilosa Ltda');
});

it('nao pede nem grava senha nova para conta ja existente', function () {
    test()->seed(PerfilSeeder::class);
    $tenantExistente = Tenant::create(['nome' => 'Empresa Existente', 'slug' => 'existente-'.uniqid()]);
    $emitenteExistente = Emitente::factory()->create(['tenant_id' => $tenantExistente->id]);
    $pessoa = User::factory()->create(['tenant_id' => $emitenteExistente->tenant_id]);
    $pessoa->emitentes()->attach($emitenteExistente);
    $senhaOriginal = $pessoa->password;

    $tenantNovo = Tenant::create(['nome' => 'Empresa Nova', 'slug' => 'nova-'.uniqid()]);
    $emitenteNovo = Emitente::factory()->create(['tenant_id' => $tenantNovo->id]);
    $admin = User::factory()->create(['tenant_id' => $emitenteNovo->tenant_id]);
    $admin->emitentes()->attach($emitenteNovo);
    app(PermissionRegistrar::class)->setPermissionsTeamId($emitenteNovo->getKey());
    $admin->assignRole(Perfil::Administrador->value);
    test()->actingAs($admin);

    Livewire::test(Cadastro::class)
        ->set('form.email', $pessoa->email)
        ->call('verificarEmail')
        ->assertDontSee('senha', escape: false)
        ->set("papeis.{$emitenteNovo->id}", Perfil::Consulta->value)
        ->call('salvar');

    expect($pessoa->fresh()->password)->toBe($senhaOriginal);
});

it('e-mail ja vinculado a esta mesma empresa segue para editar, sem duplicar', function () {
    test()->seed(PerfilSeeder::class);
    $emitente = Emitente::factory()->create();
    $admin = User::factory()->create(['tenant_id' => $emitente->tenant_id]);
    $admin->emitentes()->attach($emitente);
    app(PermissionRegistrar::class)->setPermissionsTeamId($emitente->getKey());
    $admin->assignRole(Perfil::Administrador->value);

    $jaVinculado = User::factory()->create(['tenant_id' => $emitente->tenant_id]);
    $jaVinculado->emitentes()->attach($emitente);
    app(PermissionRegistrar::class)->setPermissionsTeamId($emitente->getKey());
    $jaVinculado->assignRole(Perfil::Consulta->value);

    test()->actingAs($admin);

    Livewire::test(Cadastro::class)
        ->set('form.email', $jaVinculado->email)
        ->call('verificarEmail')
        ->assertSet('contaExistente', true);

    expect(User::where('email', $jaVinculado->email)->count())->toBe(1);
});
