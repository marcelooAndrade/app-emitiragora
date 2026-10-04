<?php

use App\Enums\Perfil;
use App\Livewire\Financeiro\Faturas;
use App\Models\Emitente;
use App\Models\Fatura;
use App\Models\FaturaParcela;
use App\Models\Pessoa;
use App\Models\Tenant;
use App\Services\Financeiro\BaixaService;
use App\Support\TenantAtual;
use Database\Seeders\PerfilSeeder;
use Livewire\Livewire;

/**
 * Cobrança com fatura e Pix: a empresa que cobra o EmitirAgora lança a
 * mensalidade no financeiro dela e, a cada parcela paga, a conta do cliente
 * (achada pelo CNPJ) ganha mais um mês.
 */
beforeEach(function () {
    $this->seed(PerfilSeeder::class);
    $this->travelTo('2026-10-05 15:00:00'); // 12h em Brasília

    // Quem cobra: o tenant da TestCase, com o CNPJ configurado.
    $this->admin = usuarioMarca(Perfil::Administrador->value);
    $this->emissor = $this->admin->emitentes()->first();
    $this->emissor->forceFill(['cnpj' => '11222333000181'])->saveQuietly();
    config(['planos.cobranca.cnpjEmissor' => '11.222.333/0001-81']);
    $this->actingAs($this->admin);

    // O cliente: outra conta do EmitirAgora, em teste até 19/10.
    $this->cliente = Tenant::create(['nome' => 'Transportes Leme', 'slug' => 'leme', 'teste_ate' => Tenant::fimDoTeste()]);
    Emitente::factory()->create(['tenant_id' => $this->cliente->id, 'cnpj' => '11444777000161']);

    $this->pessoa = Pessoa::withoutGlobalScopes()->create([
        'emitente_id' => $this->emissor->id, 'tipo_pessoa' => 'J', 'documento' => '11444777000161',
        'razao_social' => 'Transportes Leme', 'ind_ie_dest' => '2', 'logradouro' => 'R', 'numero' => '1',
        'bairro' => 'C', 'codigo_municipio' => '3503307', 'municipio' => 'Araras', 'uf' => 'SP',
        'cep' => '13602200', 'e_cliente' => true,
    ]);
});

function mensalidade(object $teste, bool $mensalidade = true, int $parcelas = 2): Fatura
{
    $fatura = Fatura::create([
        'emitente_id' => $teste->emissor->id, 'pessoa_id' => $teste->pessoa->id,
        'titulo' => 'Mensalidade EmitirAgora', 'mensalidade' => $mensalidade,
    ]);

    foreach (range(1, $parcelas) as $n) {
        FaturaParcela::create([
            'fatura_id' => $fatura->id, 'numero' => $n, 'descricao' => "Mês {$n}",
            'valor_centavos' => 49_900, 'vencimento' => now()->addMonths($n - 1)->toDateString(),
        ]);
    }

    return $fatura;
}

function receberParcela(Fatura $fatura, int $numero): void
{
    app(BaixaService::class)->receber($fatura->parcelas()->where('numero', $numero)->sole(), null);
}

it('pagar durante o teste soma um mês depois do fim do teste', function () {
    receberParcela(mensalidade($this), 1);

    $cliente = $this->cliente->fresh();
    expect($cliente->pago_ate->toDateString())->toBe('2026-11-19')
        ->and($cliente->assinante())->toBeTrue()
        ->and($cliente->emTeste())->toBeFalse()
        ->and($cliente->somenteConsulta())->toBeFalse();
});

it('cada parcela paga soma mais um mês', function () {
    $fatura = mensalidade($this);
    receberParcela($fatura, 1);
    receberParcela($fatura, 2);

    expect($this->cliente->fresh()->pago_ate->toDateString())->toBe('2026-12-19');
});

it('quem pagou atrasado volta a contar de hoje', function () {
    $this->cliente->update(['teste_ate' => null, 'pago_ate' => '2026-08-01']);

    receberParcela(mensalidade($this), 1);

    expect($this->cliente->fresh()->pago_ate->toDateString())->toBe('2026-11-05');
});

it('reabrir a parcela paga devolve o mês', function () {
    $fatura = mensalidade($this);
    receberParcela($fatura, 1);

    app(BaixaService::class)->estornar($fatura->parcelas()->where('numero', 1)->sole());

    expect($this->cliente->fresh()->pago_ate->toDateString())->toBe('2026-10-19');
});

it('fatura que não é mensalidade não mexe na assinatura', function () {
    receberParcela(mensalidade($this, mensalidade: false), 1);

    expect($this->cliente->fresh()->pago_ate)->toBeNull();
});

it('sem o CNPJ de quem cobra configurado, nada é renovado', function () {
    config(['planos.cobranca.cnpjEmissor' => '']);

    receberParcela(mensalidade($this), 1);

    expect($this->cliente->fresh()->pago_ate)->toBeNull();
});

it('com a mensalidade vencida, segue emitindo nos dias de tolerância e avisa', function () {
    $tenant = app(TenantAtual::class)->obter();
    $tenant->update(['pago_ate' => '2026-10-03']);

    expect($tenant->assinante())->toBeTrue()
        ->and($tenant->pagamentoVencido())->toBeTrue()
        ->and($this->admin->can('nota.emitir'))->toBeTrue();

    $this->get('/dashboard')->assertOk()
        ->assertSee('A mensalidade venceu em 03/10/2026')
        ->assertSee('08/10/2026');
});

it('passada a tolerância, a conta fica só para consulta', function () {
    $tenant = app(TenantAtual::class)->obter();
    $tenant->update(['pago_ate' => '2026-09-29']); // tolerância até 04/10

    expect($tenant->somenteConsulta())->toBeTrue()
        ->and($this->admin->can('nota.emitir'))->toBeFalse()
        ->and($this->admin->can('nota.ver'))->toBeTrue();

    $this->get('/dashboard')->assertOk()->assertSee('A mensalidade está em aberto');
});

it('em dia, não aparece aviso nenhum', function () {
    app(TenantAtual::class)->obter()->update(['pago_ate' => '2026-11-05']);

    $this->get('/dashboard')->assertOk()
        ->assertDontSee('A mensalidade')
        ->assertDontSee('Teste grátis');
});

it('a opção de mensalidade só aparece para quem cobra o EmitirAgora', function () {
    Livewire::test(Faturas::class)->set('formularioAberto', true)->assertSee('Mensalidade do EmitirAgora');

    config(['planos.cobranca.cnpjEmissor' => '99888777000155']);

    Livewire::test(Faturas::class)->set('formularioAberto', true)->assertDontSee('Mensalidade do EmitirAgora');
});

it('lança a mensalidade pela tela e a fatura sai marcada', function () {
    Livewire::test(Faturas::class)
        ->set('titulo', 'Mensalidade outubro')
        ->set('pessoaId', $this->pessoa->id)
        ->set('mensalidade', true)
        ->set('valor', '499,00')
        ->set('parcelas', 1)
        ->set('primeiroVencimento', '2026-10-10')
        ->call('lancar')
        ->assertHasNoErrors();

    expect(Fatura::sole()->mensalidade)->toBeTrue();
});

it('recusa mensalidade para cliente que não tem conta no EmitirAgora', function () {
    $this->pessoa->forceFill(['documento' => '45723174000110'])->saveQuietly();

    Livewire::test(Faturas::class)
        ->set('titulo', 'Mensalidade outubro')
        ->set('pessoaId', $this->pessoa->id)
        ->set('mensalidade', true)
        ->set('valor', '499,00')
        ->set('parcelas', 1)
        ->set('primeiroVencimento', '2026-10-10')
        ->call('lancar')
        ->assertHasErrors('pessoaId');

    expect(Fatura::count())->toBe(0);
});
