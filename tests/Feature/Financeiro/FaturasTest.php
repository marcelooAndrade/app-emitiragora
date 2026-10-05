<?php

use App\Enums\Perfil;
use App\Livewire\Financeiro\Faturas;
use App\Models\CentroCusto;
use App\Models\Emitente;
use App\Models\Fatura;
use App\Models\User;
use Database\Seeders\PerfilSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PerfilSeeder::class);
    $this->emitente = emitenteCompleto();
    $this->user = User::factory()->create(['tenant_id' => $this->emitente->tenant_id]);
    $this->user->emitentes()->attach($this->emitente);
    setPermissionsTeamId($this->emitente->id);
    $this->user->assignRole(Perfil::Administrador->value);
});

it('o administrador abre a tela e o item esta no menu', function () {
    $this->actingAs($this->user)->get('/faturas')->assertOk()->assertSee(route('faturas'));
});

it('quem nao tem permissao nao abre', function () {
    $consulta = User::factory()->create(['tenant_id' => $this->emitente->tenant_id]);
    $consulta->emitentes()->attach($this->emitente);
    setPermissionsTeamId($this->emitente->id);

    $this->actingAs($consulta)->get('/faturas')->assertForbidden();
});

it('toda fatura nasce com token publico proprio', function () {
    Livewire::actingAs($this->user)->test(Faturas::class)
        ->set('titulo', 'Venda 4001')
        ->set('valor', '100,00')
        ->set('parcelas', 1)
        ->call('lancar')
        ->assertHasNoErrors()
        ->assertRedirect();

    $fatura = Fatura::firstWhere('titulo', 'Venda 4001');

    expect($fatura->public_token)->toMatch('/^[0-9a-f-]{36}$/');
});

it('com plano de contas, o centro de receita e obrigatorio', function () {
    $centro = CentroCusto::create([
        'emitente_id' => $this->emitente->id, 'codigo' => '015.001.001',
        'nome' => 'Projetos', 'natureza' => 'receita',
    ]);
    CentroCusto::create([
        'emitente_id' => $this->emitente->id, 'codigo' => '021.001.001',
        'nome' => 'Despesa', 'natureza' => 'despesa',
    ]);

    Livewire::actingAs($this->user)->test(Faturas::class)
        ->set('titulo', 'Sem centro')
        ->set('valor', '100,00')
        ->call('lancar')
        ->assertHasErrors('centroCustoId');

    expect(Fatura::count())->toBe(0);

    Livewire::actingAs($this->user)->test(Faturas::class)
        ->set('titulo', 'Com centro')
        ->set('valor', '100,00')
        ->set('centroCustoId', $centro->id)
        ->set('observacoes', 'Obrigado pela parceria.')
        ->call('lancar')
        ->assertHasNoErrors();

    $fatura = Fatura::firstWhere('titulo', 'Com centro');

    expect($fatura->centro_custo_id)->toBe($centro->id)
        ->and($fatura->observacoes)->toBe('Obrigado pela parceria.');
});

it('lista as faturas com total, em aberto e proximo vencimento', function () {
    $fatura = Fatura::create(['emitente_id' => $this->emitente->id, 'titulo' => 'Consultoria']);
    $fatura->parcelas()->create(['numero' => 1, 'descricao' => 'Entrada', 'valor_centavos' => 30_000, 'vencimento' => '2026-09-01', 'status' => 'pago', 'pago_em' => now()]);
    $fatura->parcelas()->create(['numero' => 2, 'descricao' => 'Saldo', 'valor_centavos' => 70_000, 'vencimento' => '2026-10-10']);

    $this->actingAs($this->user)->get('/faturas')
        ->assertOk()
        ->assertSee('Consultoria')
        ->assertSee('1.000,00')
        ->assertSee('700,00')
        ->assertSee('10/10/2026')
        ->assertSee(route('faturas.detalhe', $fatura));
});

it('nao lista fatura de outro emitente', function () {
    $outro = Emitente::factory()->create();
    Fatura::create(['emitente_id' => $outro->id, 'titulo' => 'Da outra empresa']);

    Livewire::actingAs($this->user)->test(Faturas::class)->assertDontSee('Da outra empresa');
});
