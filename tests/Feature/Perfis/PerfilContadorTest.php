<?php

use App\Enums\Perfil;
use App\Models\Emitente;
use App\Models\User;
use Database\Seeders\PerfilSeeder;

beforeEach(function () {
    $this->seed(PerfilSeeder::class);
    $this->user = User::factory()->create();
    $emitente = Emitente::factory()->create();
    $this->user->emitentes()->attach($emitente);
    setPermissionsTeamId($emitente->id);
    $this->user->assignRole(Perfil::Contador->value);
});

it('existe como perfil do sistema', function () {
    expect(Perfil::cases())->toHaveCount(4)
        ->and(Perfil::Contador->value)->toBe('Contador');
});

it('exporta o pacote da contabilidade', function () {
    expect($this->user->can('contador.exportar'))->toBeTrue();
});

it('enxerga as viagens para conferir', function () {
    expect($this->user->can('transporte.ver'))->toBeTrue();
});

it('nao emite nem cancela CT-e e MDF-e', function () {
    expect($this->user->can('transporte.operar'))->toBeFalse()
        ->and($this->user->can('transporte.cancelar'))->toBeFalse();
});

it('cuida do financeiro', function () {
    expect($this->user->can('financeiro.gerenciar'))->toBeTrue();
});

it('nao mexe no certificado', function () {
    expect($this->user->can('certificado.gerenciar'))->toBeFalse();
});

it('nao vira o ambiente para producao', function () {
    expect($this->user->can('emitente.ativar-producao'))->toBeFalse();
});
