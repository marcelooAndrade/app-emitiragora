<?php

use App\Enums\Perfil;
use App\Livewire\Transporte\Viagens;
use App\Models\Tenant;
use App\Support\TenantAtual;
use Database\Seeders\PerfilSeeder;

/**
 * Teste grátis de 14 dias: quem se cadastra testa o plano inteiro e, sem
 * assinar, a conta fica só para consulta. As empresas que já existiam antes
 * do teste não têm prazo nenhum.
 */
beforeEach(function () {
    $this->seed(PerfilSeeder::class);
    $this->travelTo('2026-10-05 22:30:00'); // 19h30 em Brasília
    $this->tenant = app(TenantAtual::class)->obter();
    $this->user = usuarioMarca(Perfil::Administrador->value);
    $this->actingAs($this->user);
});

it('o teste vai até o fim do 14º dia no horário de Brasília', function () {
    expect(Tenant::fimDoTeste()->setTimezone('America/Sao_Paulo')->format('Y-m-d H:i:s'))
        ->toBe('2026-10-19 23:59:59');
});

it('quem se cadastra ganha 14 dias de teste', function () {
    auth()->logout();
    Tenant::query()->whereKeyNot($this->tenant->id)->delete();

    $this->post('http://vendaredonda.com.br/register', [
        'name' => 'Marcelo Andrade', 'email' => 'novo@exemplo.com.br',
        'password' => 'Senha-forte-123', 'password_confirmation' => 'Senha-forte-123',
        'razao_social' => 'TRANSPORTES TESTE LTDA', 'cnpj' => '11222333000181',
        'inscricao_estadual' => '123456789012', 'crt' => '3', 'telefone' => '1930960072',
        'perfil_tipo' => 'transportadora',
    ]);

    $novo = Tenant::firstWhere('nome', 'TRANSPORTES TESTE LTDA');

    expect($novo->emTeste())->toBeTrue()
        ->and($novo->diasDeTesteRestantes())->toBe(14);
});

it('empresa que já existia não tem teste, não vê aviso e não é bloqueada', function () {
    expect($this->tenant->teste_ate)->toBeNull()
        ->and($this->tenant->somenteConsulta())->toBeFalse();

    $this->get('/dashboard')->assertOk()->assertDontSee('Teste grátis')->assertDontSee('Seu teste grátis acabou');
    expect($this->user->can('nota.emitir'))->toBeTrue();
});

it('durante o teste mostra quantos dias faltam e libera tudo', function () {
    $this->tenant->update(['teste_ate' => Tenant::fimDoTeste()]);

    $this->get('/dashboard')->assertOk()
        ->assertSee('Teste grátis do Plano Transporte')
        ->assertSee('faltam 14 dias')
        ->assertSee('wa.me/5519971351777', false);

    expect($this->user->can('nota.emitir'))->toBeTrue()
        ->and($this->user->can('transporte.operar'))->toBeTrue();
});

it('no último dia avisa que é o último', function () {
    $this->tenant->update(['teste_ate' => Tenant::fimDoTeste()]);
    $this->travelTo('2026-10-19 23:00:00'); // 20h em Brasília do dia 19

    $this->get('/dashboard')->assertOk()->assertSee('hoje é o último dia');
});

it('com o teste acabado a conta só consulta, até para o Administrador', function () {
    $this->tenant->update(['teste_ate' => Tenant::fimDoTeste()]);
    $this->travelTo('2026-10-20 03:00:01'); // 00h00min01 do dia 20 em Brasília

    $this->get('/dashboard')->assertOk()->assertSee('Seu teste grátis acabou');

    expect($this->user->can('nota.ver'))->toBeTrue()
        ->and($this->user->can('financeiro.ver'))->toBeTrue()
        ->and($this->user->can('transporte.ver'))->toBeTrue()
        ->and($this->user->can('contador.exportar'))->toBeTrue()
        ->and($this->user->can('nota.emitir'))->toBeFalse()
        ->and($this->user->can('nfse.emitir'))->toBeFalse()
        ->and($this->user->can('transporte.operar'))->toBeFalse()
        ->and($this->user->can('financeiro.gerenciar'))->toBeFalse()
        ->and($this->user->can('usuario.gerenciar'))->toBeFalse();
});

it('a tela de viagens abre para consulta, mas não cria viagem', function () {
    $this->tenant->update(['teste_ate' => now()->subMinute()]);

    $this->get('/viagens')->assertOk();

    Livewire\Livewire::test(Viagens::class)
        ->call('nova')
        ->assertForbidden();
});
