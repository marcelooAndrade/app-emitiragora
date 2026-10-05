<?php

use App\Enums\Perfil;
use App\Models\Emitente;
use App\Models\User;
use Database\Seeders\PerfilSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(PerfilSeeder::class);
});

it('cria os quatro perfis do sistema', function () {
    expect(Role::query()->pluck('name')->all())
        ->toEqualCanonicalizing(['Administrador', 'Faturamento', 'Contador', 'Consulta']);
});

it('permite perfis diferentes para o mesmo usuario em emitentes diferentes', function () {
    $user = User::factory()->create();
    $matriz = Emitente::factory()->create();
    $filial = Emitente::factory()->create();

    setPermissionsTeamId($matriz->id);
    $user->assignRole(Perfil::Faturamento->value);

    setPermissionsTeamId($filial->id);
    $user->assignRole(Perfil::Consulta->value);

    setPermissionsTeamId($matriz->id);
    expect($user->fresh()->hasRole(Perfil::Faturamento->value))->toBeTrue();

    setPermissionsTeamId($filial->id);
    expect($user->fresh()->hasRole(Perfil::Faturamento->value))->toBeFalse()
        ->and($user->fresh()->hasRole(Perfil::Consulta->value))->toBeTrue();
});

it('nega emissao de CT-e e MDF-e ao perfil de consulta', function () {
    $user = User::factory()->create();
    $emitente = Emitente::factory()->create();

    setPermissionsTeamId($emitente->id);
    $user->assignRole(Perfil::Consulta->value);

    expect($user->can('transporte.ver'))->toBeTrue()
        ->and($user->can('transporte.operar'))->toBeFalse()
        ->and($user->can('transporte.cancelar'))->toBeFalse();
});

it('faturamento opera e cancela a viagem, mas nao configura o transporte', function () {
    $user = User::factory()->create();
    $emitente = Emitente::factory()->create();

    setPermissionsTeamId($emitente->id);
    $user->assignRole(Perfil::Faturamento->value);

    expect($user->can('transporte.operar'))->toBeTrue()
        ->and($user->can('transporte.cancelar'))->toBeTrue()
        ->and($user->can('transporte.configurar'))->toBeFalse()
        ->and($user->can('financeiro.gerenciar'))->toBeFalse();
});

it('nega virada para producao a quem nao for administrador', function () {
    $user = User::factory()->create();
    $emitente = Emitente::factory()->create();

    setPermissionsTeamId($emitente->id);
    $user->assignRole(Perfil::Faturamento->value);

    expect($user->can('emitente.ativar-producao'))->toBeFalse();
});

it('nao sobrou permissao de nota, NFS-e, estoque ou produto', function () {
    expect(Permission::query()->pluck('name')->filter(
        fn (string $nome): bool => str_starts_with($nome, 'nota.') || str_starts_with($nome, 'nfse.')
            || str_starts_with($nome, 'estoque.') || str_starts_with($nome, 'produto.')
            || str_starts_with($nome, 'tributacao.') || str_starts_with($nome, 'importacao.'),
    ))->toBeEmpty();
});
