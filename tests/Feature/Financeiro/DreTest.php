<?php

use App\Enums\Perfil;
use App\Livewire\Financeiro\Dre;
use App\Models\ContaFinanceira;
use App\Models\MovimentoCaixa;
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

    $this->conta = ContaFinanceira::create(['emitente_id' => $this->emitente->id, 'nome' => 'Caixa']);
});

it('quem nao tem permissao nao abre a tela', function () {
    $consulta = User::factory()->create(['tenant_id' => $this->emitente->tenant_id]);
    $consulta->emitentes()->attach($this->emitente);
    setPermissionsTeamId($this->emitente->id);

    $this->actingAs($consulta)->get('/dre')->assertForbidden();
});

it('o administrador abre a tela no mes atual', function () {
    $this->actingAs($this->user)->get('/dre')->assertOk();
});

it('troca de mes e refaz o relatorio', function () {
    MovimentoCaixa::create([
        'emitente_id' => $this->emitente->id, 'conta_financeira_id' => $this->conta->id,
        'sentido' => 'credito', 'valor_centavos' => 7_000, 'descricao' => 'Venda de agosto',
        'ocorrido_em' => '2026-08-20', 'origem_tipo' => 'ajuste', 'created_at' => now(),
    ]);

    $componente = Livewire::actingAs($this->user)->test(Dre::class);

    expect($componente->get('dados')['receitaCentavos'])->toBe(0);

    $componente->set('mes', '2026-08');

    expect($componente->get('dados')['receitaCentavos'])->toBe(7_000);
});
