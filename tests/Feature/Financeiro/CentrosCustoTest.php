<?php

use App\Enums\Perfil;
use App\Livewire\Financeiro\CentrosCusto;
use App\Models\CentroCusto;
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

it('quem nao tem permissao nao abre a tela', function () {
    $consulta = User::factory()->create(['tenant_id' => $this->emitente->tenant_id]);
    $consulta->emitentes()->attach($this->emitente);
    setPermissionsTeamId($this->emitente->id);

    $this->actingAs($consulta)->get('/centros-de-custo')->assertForbidden();
});

it('o administrador abre a tela', function () {
    $this->actingAs($this->user)->get('/centros-de-custo')->assertOk();
});

it('cria um centro de custo raiz', function () {
    Livewire::actingAs($this->user)->test(CentrosCusto::class)
        ->set('codigo', '001')
        ->set('nome', 'Folha de pagamento')
        ->set('natureza', 'despesa')
        ->call('criar')
        ->assertHasNoErrors();

    $centro = CentroCusto::firstWhere('nome', 'Folha de pagamento');

    expect($centro)->not->toBeNull()
        ->and($centro->codigo)->toBe('001')
        ->and($centro->pai_id)->toBeNull();
});

it('formata o codigo digitado sem pontuacao', function () {
    Livewire::actingAs($this->user)->test(CentrosCusto::class)
        ->set('codigo', '001002')
        ->set('nome', 'Salarios')
        ->set('natureza', 'despesa')
        ->call('criar')
        ->assertHasNoErrors();

    expect(CentroCusto::firstWhere('nome', 'Salarios')->codigo)->toBe('001.002');
});

it('recusa codigo repetido no mesmo emitente', function () {
    CentroCusto::create([
        'emitente_id' => $this->emitente->id, 'codigo' => '001',
        'nome' => 'Existente', 'natureza' => 'despesa',
    ]);

    Livewire::actingAs($this->user)->test(CentrosCusto::class)
        ->set('codigo', '001')
        ->set('nome', 'Duplicado')
        ->set('natureza', 'despesa')
        ->call('criar')
        ->assertHasErrors('codigo');

    expect(CentroCusto::where('nome', 'Duplicado')->count())->toBe(0);
});

it('centro filho herda o essencial do pai quando nao classificado', function () {
    $pai = CentroCusto::create([
        'emitente_id' => $this->emitente->id, 'codigo' => '001',
        'nome' => 'Custos fixos', 'natureza' => 'despesa', 'grupo' => true, 'essencial' => true,
    ]);

    Livewire::actingAs($this->user)->test(CentrosCusto::class)
        ->set('codigo', '001.001')
        ->set('nome', 'Aluguel')
        ->set('natureza', 'despesa')
        ->set('paiId', $pai->id)
        ->call('criar')
        ->assertHasNoErrors();

    $filho = CentroCusto::firstWhere('nome', 'Aluguel');

    expect($filho->essencial)->toBeNull()
        ->and($filho->eEssencial())->toBeTrue();
});

it('alterna um centro entre ativo e inativo', function () {
    $centro = CentroCusto::create([
        'emitente_id' => $this->emitente->id, 'codigo' => '001',
        'nome' => 'Marketing', 'natureza' => 'despesa',
    ]);

    Livewire::actingAs($this->user)->test(CentrosCusto::class)
        ->call('alternarAtivo', $centro->id);

    expect($centro->fresh()->ativo)->toBeFalse();
});
