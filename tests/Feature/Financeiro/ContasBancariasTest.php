<?php

use App\Enums\Perfil;
use App\Livewire\Financeiro\ContasBancarias;
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
});

it('quem nao tem permissao nao abre a tela', function () {
    $consulta = User::factory()->create(['tenant_id' => $this->emitente->tenant_id]);
    $consulta->emitentes()->attach($this->emitente);
    setPermissionsTeamId($this->emitente->id);

    $this->actingAs($consulta)->get('/contas-bancarias')->assertForbidden();
});

it('o administrador abre a tela', function () {
    $this->actingAs($this->user)->get('/contas-bancarias')->assertOk();
});

it('cria uma conta com saldo inicial em reais', function () {
    Livewire::actingAs($this->user)->test(ContasBancarias::class)
        ->set('nome', 'Conta corrente Banco X')
        ->set('banco', 'Banco X')
        ->set('tipo', 'corrente')
        ->set('saldoInicial', 'R$ 1.500,00')
        ->call('criarConta')
        ->assertHasNoErrors();

    $conta = ContaFinanceira::firstWhere('nome', 'Conta corrente Banco X');

    expect($conta)->not->toBeNull()
        ->and($conta->saldo_inicial_centavos)->toBe(150000)
        ->and($conta->emitente_id)->toBe($this->emitente->id);
});

it('o saldo da conta soma o inicial com o razao', function () {
    $conta = ContaFinanceira::create([
        'emitente_id' => $this->emitente->id, 'nome' => 'Caixa', 'saldo_inicial_centavos' => 10_000,
    ]);

    MovimentoCaixa::create([
        'emitente_id' => $this->emitente->id, 'conta_financeira_id' => $conta->id,
        'sentido' => 'credito', 'valor_centavos' => 5_000, 'descricao' => 'Entrada',
        'ocorrido_em' => today(), 'origem_tipo' => 'ajuste', 'created_at' => now(),
    ]);

    MovimentoCaixa::create([
        'emitente_id' => $this->emitente->id, 'conta_financeira_id' => $conta->id,
        'sentido' => 'debito', 'valor_centavos' => 2_000, 'descricao' => 'Saida',
        'ocorrido_em' => today(), 'origem_tipo' => 'ajuste', 'created_at' => now(),
    ]);

    expect($conta->saldoCentavos())->toBe(13_000);
});

it('lanca um ajuste manual na conta selecionada', function () {
    $conta = ContaFinanceira::create(['emitente_id' => $this->emitente->id, 'nome' => 'Caixa']);

    Livewire::actingAs($this->user)->test(ContasBancarias::class)
        ->call('selecionar', $conta->id)
        ->set('ajusteSentido', 'debito')
        ->set('ajusteValor', 'R$ 50,00')
        ->set('ajusteDescricao', 'Taxa de manutencao')
        ->call('lancarAjuste')
        ->assertHasNoErrors();

    $movimento = MovimentoCaixa::firstWhere('descricao', 'Taxa de manutencao');

    expect($movimento)->not->toBeNull()
        ->and($movimento->origem_tipo)->toBe('ajuste')
        ->and($movimento->origem_id)->toBeNull()
        ->and($movimento->valor_centavos)->toBe(5_000);
});

it('recusa ajuste sem valor', function () {
    $conta = ContaFinanceira::create(['emitente_id' => $this->emitente->id, 'nome' => 'Caixa']);

    Livewire::actingAs($this->user)->test(ContasBancarias::class)
        ->call('selecionar', $conta->id)
        ->set('ajusteValor', '')
        ->set('ajusteDescricao', 'Sem valor')
        ->call('lancarAjuste')
        ->assertHasErrors('ajusteValor');

    expect(MovimentoCaixa::count())->toBe(0);
});

it('alterna a conta entre ativa e inativa', function () {
    $conta = ContaFinanceira::create(['emitente_id' => $this->emitente->id, 'nome' => 'Caixa']);

    Livewire::actingAs($this->user)->test(ContasBancarias::class)
        ->call('alternarAtiva', $conta->id);

    expect($conta->fresh()->ativo)->toBeFalse();
});
