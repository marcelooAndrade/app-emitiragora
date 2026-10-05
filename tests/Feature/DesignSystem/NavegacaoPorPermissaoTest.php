<?php

use App\Enums\Perfil;
use App\Models\Emitente;
use App\Models\User;
use Database\Seeders\PerfilSeeder;

beforeEach(fn () => $this->seed(PerfilSeeder::class));

function usuarioNav(string $perfil): User
{
    $user = User::factory()->create();
    $emitente = Emitente::factory()->create();
    $user->emitentes()->attach($emitente);
    setPermissionsTeamId($emitente->id);
    $user->assignRole($perfil);
    setPermissionsTeamId(null);

    return $user;
}

it('o contador nao ve certificado na navegacao', function () {
    $this->actingAs(usuarioNav(Perfil::Contador->value))
        ->get('/contabilidade')
        ->assertOk()
        ->assertDontSee('Certificado');
});

it('o contador ve a contabilidade na navegacao', function () {
    $this->actingAs(usuarioNav(Perfil::Contador->value))
        ->get('/destinatarios')
        ->assertSee('Contabilidade');
});

it('faturamento nao ve item so de administrador', function () {
    $this->actingAs(usuarioNav(Perfil::Faturamento->value))
        ->get('/destinatarios')
        ->assertOk()
        ->assertDontSee('Usuários')
        ->assertDontSee('Marca');
});

it('o administrador ve tudo o que e da transportadora', function () {
    $this->actingAs(usuarioNav(Perfil::Administrador->value))
        ->get('/certificados')
        ->assertOk()
        ->assertSee('Viagens')
        ->assertSee('Contabilidade')
        ->assertSee('Certificado')
        ->assertSee('Marca');
});

it('ninguem ve nota fiscal, NFS-e, estoque ou produtos na navegacao', function () {
    $html = $this->actingAs(usuarioNav(Perfil::Administrador->value))->get('/certificados')->getContent();

    foreach (['/notas"', '/notas-servico', '/nfse', '/estoque', '/produtos', '/regras-fiscais', '/importacao'] as $caminho) {
        expect($html)->not->toContain($caminho);
    }
});

it('consulta nao ve item so de administrador', function () {
    $this->actingAs(usuarioNav(Perfil::Consulta->value))
        ->get('/destinatarios')
        ->assertOk()
        ->assertDontSee('Marca');
});
