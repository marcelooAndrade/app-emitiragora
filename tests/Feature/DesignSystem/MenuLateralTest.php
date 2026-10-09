<?php

/**
 * Até 09/10/2026 o menu era uma lista corrida de 20 itens que não cabia em
 * 900px de altura, repetia "Transporte" e "Financeiro" como grupo e como
 * item, e não existia no celular: a barra lateral sumia abaixo de `md` e
 * nada a substituía. Agora os grupos recolhem, Configurações fica embaixo
 * e o celular abre a mesma navegação numa gaveta (`<dialog>`), sem Alpine,
 * no mesmo padrão nativo do menu do usuário.
 */

use App\Enums\Perfil;
use App\Models\Emitente;
use App\Models\User;
use Database\Seeders\PerfilSeeder;

beforeEach(function () {
    $this->seed(PerfilSeeder::class);

    $emitente = Emitente::factory()->create();
    $this->admin = User::factory()->create(['tenant_id' => $emitente->tenant_id]);
    $this->admin->emitentes()->attach($emitente);
    setPermissionsTeamId($emitente->id);
    $this->admin->assignRole(Perfil::Administrador->value);
    setPermissionsTeamId(null);
});

it('agrupa a navegacao em operacao, financeiro, relatorios e configuracoes', function () {
    $this->actingAs($this->admin)->get('/viagens')
        ->assertOk()
        ->assertSeeInOrder(['Painel', 'Operação', 'Viagens', 'Financeiro', 'Visão geral', 'Relatórios', 'DRE', 'Configurações']);
});

it('leva centros de custo para configuracoes, junto da empresa e do CT-e e MDF-e', function () {
    $this->actingAs($this->admin)->get('/viagens')
        ->assertSeeInOrder(['Configurações', 'Empresa', 'CT-e e MDF-e', 'Certificado', 'Usuários', 'Marca', 'Centros de custo']);
});

it('deixa configuracoes recolhido fora das telas dela', function () {
    $html = $this->actingAs($this->admin)->get('/viagens')->getContent();

    expect($html)->toMatch('/<details[^>]*data-grupo="configuracoes"(?![^>]*\sopen)[^>]*>/')
        ->and($html)->toMatch('/<details[^>]*data-grupo="operacao"[^>]*\sopen[^>]*>/');
});

it('abre configuracoes quando a tela atual e uma delas', function () {
    $html = $this->actingAs($this->admin)->get('/certificados')->getContent();

    expect($html)->toMatch('/<details[^>]*data-grupo="configuracoes"[^>]*\sopen[^>]*>/');
});

it('no celular um botao abre a mesma navegacao numa gaveta', function () {
    $html = $this->actingAs($this->admin)->get('/viagens')->getContent();

    expect($html)->toContain('aria-controls="menu-celular"')
        ->and($html)->toContain('Abrir menu')
        ->and($html)->toMatch('/<dialog[^>]*id="menu-celular"/');

    $gaveta = str($html)->after('id="menu-celular"')->before('</dialog>');

    expect((string) $gaveta)->toContain('href="'.route('viagens').'"')
        ->and((string) $gaveta)->toContain('href="'.route('certificados').'"');
});
