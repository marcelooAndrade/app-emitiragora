<?php

use App\Enums\Perfil;
use App\Livewire\Usuarios\Cadastro;
use App\Models\Emitente;
use App\Models\User;
use Database\Seeders\PerfilSeeder;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

function contextoComDoisUsuarios(): array
{
    test()->seed(PerfilSeeder::class);
    $emitente = Emitente::factory()->create();

    $admin = User::factory()->create(['tenant_id' => $emitente->tenant_id]);
    $admin->emitentes()->attach($emitente);
    app(PermissionRegistrar::class)->setPermissionsTeamId($emitente->getKey());
    $admin->assignRole(Perfil::Administrador->value);

    $alvo = User::factory()->create(['tenant_id' => $emitente->tenant_id]);
    $alvo->emitentes()->attach($emitente);
    app(PermissionRegistrar::class)->setPermissionsTeamId($emitente->getKey());
    $alvo->assignRole(Perfil::Consulta->value);

    test()->actingAs($admin);

    return [$admin, $alvo, $emitente];
}

it('troca o perfil de um usuario existente', function () {
    [, $alvo, $emitente] = contextoComDoisUsuarios();

    Livewire::test(Cadastro::class)
        ->call('editar', $alvo->id)
        ->assertSet("papeis.{$emitente->id}", Perfil::Consulta->value)
        ->set("papeis.{$emitente->id}", Perfil::Faturamento->value)
        ->call('salvar')
        ->assertHasNoErrors();

    app(PermissionRegistrar::class)->setPermissionsTeamId($emitente->getKey());
    expect($alvo->fresh()->hasRole(Perfil::Faturamento->value))->toBeTrue()
        ->and($alvo->fresh()->hasRole(Perfil::Consulta->value))->toBeFalse();
});

it('remove o vinculo ao escolher sem acesso', function () {
    [, $alvo, $emitente] = contextoComDoisUsuarios();

    Livewire::test(Cadastro::class)
        ->call('editar', $alvo->id)
        ->set("papeis.{$emitente->id}", '')
        ->call('salvar')
        ->assertHasNoErrors();

    expect($alvo->fresh()->podeAcessar($emitente))->toBeFalse();
});
