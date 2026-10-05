<?php

use App\Enums\Perfil;
use App\Livewire\Financeiro\ContasPagar;
use App\Livewire\Financeiro\Faturas;
use App\Livewire\Financeiro\Painel as PainelFinanceiro;
use App\Models\ContaFinanceira;
use App\Models\ContaPagar as TituloPagar;
use App\Models\Emitente;
use App\Models\Fatura;
use App\Models\FaturaParcela;
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

    $this->conta = ContaFinanceira::create([
        'emitente_id' => $this->emitente->id,
        'nome' => 'Conta movimento',
        'saldo_inicial_centavos' => 100_000,
    ]);
});

it('quem nao tem permissao nao abre o financeiro', function (string $rota) {
    $consulta = User::factory()->create(['tenant_id' => $this->emitente->tenant_id]);
    $consulta->emitentes()->attach($this->emitente);
    setPermissionsTeamId($this->emitente->id);

    $this->actingAs($consulta)->get($rota)->assertForbidden();
})->with(['/financeiro', '/contas-a-pagar', '/contas-a-receber']);

it('o administrador abre as tres telas', function (string $rota) {
    $this->actingAs($this->user)->get($rota)->assertOk();
})->with(['/financeiro', '/contas-a-pagar', '/contas-a-receber']);

it('lanca um titulo a pagar com o valor digitado em reais', function () {
    Livewire::actingAs($this->user)->test(ContasPagar::class)
        ->set('descricao', 'Energia de setembro')
        ->set('fornecedor', 'CPFL')
        ->set('valor', 'R$ 1.250,90')
        ->set('vencimento', today()->toDateString())
        ->call('lancar')
        ->assertHasNoErrors();

    $titulo = TituloPagar::firstWhere('descricao', 'Energia de setembro');

    expect($titulo)->not->toBeNull()
        ->and($titulo->valor_centavos)->toBe(125090)
        ->and($titulo->status)->toBe('pendente');
});

it('recusa titulo sem valor', function () {
    Livewire::actingAs($this->user)->test(ContasPagar::class)
        ->set('descricao', 'Sem valor')
        ->set('valor', 'abc')
        ->set('vencimento', today()->toDateString())
        ->call('lancar')
        ->assertHasErrors('valor');

    expect(TituloPagar::count())->toBe(0);
});

it('baixa um titulo pela tela e o caixa acompanha', function () {
    $titulo = TituloPagar::create([
        'emitente_id' => $this->emitente->id, 'descricao' => 'Energia',
        'valor_centavos' => 35_000, 'vencimento' => today(),
    ]);

    Livewire::actingAs($this->user)->test(ContasPagar::class)
        ->set('contaBaixaId', $this->conta->id)
        ->call('baixar', $titulo->id)
        ->assertHasNoErrors();

    expect($titulo->fresh()->status)->toBe('pago')
        ->and($this->conta->fresh()->saldoCentavos())->toBe(65_000)
        ->and(MovimentoCaixa::count())->toBe(1);
});

it('nao enxerga titulo de outro emitente', function () {
    $outro = Emitente::factory()->create();
    TituloPagar::create([
        'emitente_id' => $outro->id, 'descricao' => 'Da outra empresa',
        'valor_centavos' => 1_000, 'vencimento' => today(),
    ]);

    Livewire::actingAs($this->user)->test(ContasPagar::class)
        ->assertDontSee('Da outra empresa');
});

it('cria fatura com parcelas e o total confere', function () {
    Livewire::actingAs($this->user)->test(Faturas::class)
        ->set('titulo', 'Venda 1001')
        ->set('valor', '900,00')
        ->set('parcelas', 3)
        ->set('primeiroVencimento', today()->toDateString())
        ->call('lancar')
        ->assertHasNoErrors();

    $fatura = Fatura::firstWhere('titulo', 'Venda 1001');

    expect($fatura->parcelas)->toHaveCount(3)
        ->and($fatura->totalCentavos())->toBe(90_000)
        ->and($fatura->parcelas->pluck('valor_centavos')->all())->toBe([30_000, 30_000, 30_000]);
});

it('divide sobra de centavo na primeira parcela, e nao no ar', function () {
    Livewire::actingAs($this->user)->test(Faturas::class)
        ->set('titulo', 'Venda 1002')
        ->set('valor', '100,00')
        ->set('parcelas', 3)
        ->set('primeiroVencimento', today()->toDateString())
        ->call('lancar');

    $fatura = Fatura::firstWhere('titulo', 'Venda 1002');

    expect($fatura->parcelas->pluck('valor_centavos')->all())->toBe([3_334, 3_333, 3_333])
        ->and($fatura->totalCentavos())->toBe(10_000);
});

/**
 * Saldo e saldo projetado moram lado a lado, então o cenário os separa de
 * propósito: com os dois iguais, uma asserção solta no número passaria mesmo
 * se a tela trocasse um pelo outro.
 */
it('o painel mostra o saldo em caixa e a projecao, cada um no seu lugar', function () {
    MovimentoCaixa::create([
        'emitente_id' => $this->emitente->id, 'conta_financeira_id' => $this->conta->id,
        'sentido' => 'credito', 'valor_centavos' => 50_000,
        'descricao' => 'Recebimento', 'ocorrido_em' => today(), 'origem_tipo' => 'ajuste',
    ]);

    TituloPagar::create([
        'emitente_id' => $this->emitente->id, 'descricao' => 'Fornecedor',
        'valor_centavos' => 50_000, 'vencimento' => today()->addDays(10),
    ]);

    $html = Livewire::actingAs($this->user)->test(PainelFinanceiro::class)->html();

    $caixa = strpos($html, 'Saldo em caixa');
    $projetado = strpos($html, 'Saldo projetado');

    expect($caixa)->not->toBeFalse()
        ->and($projetado)->toBeGreaterThan($caixa)
        // 1.500,00 de caixa aparece entre um rótulo e o outro.
        ->and(substr($html, $caixa, $projetado - $caixa))->toContain('1.500,00')
        // 1.000,00 de projeção, que é o caixa menos o título em aberto.
        ->and(substr($html, $projetado))->toContain('1.000,00');
});

/**
 * O escopo do financeiro é por emitente, não por tenant. Uma matriz e sua
 * filial vivem no mesmo tenant e não podem somar caixa uma da outra, que é
 * exatamente o erro já cometido nas outras duas telas deste módulo.
 */
it('o painel nao soma o caixa de outro emitente do mesmo tenant', function () {
    $filial = Emitente::factory()->create(['tenant_id' => $this->emitente->tenant_id]);

    ContaFinanceira::create([
        'emitente_id' => $filial->id,
        'nome' => 'Caixa da filial',
        'saldo_inicial_centavos' => 7_777_00,
    ]);

    TituloPagar::create([
        'emitente_id' => $filial->id, 'descricao' => 'Aluguel da filial',
        'valor_centavos' => 4_444_00, 'vencimento' => today(),
    ]);

    Livewire::actingAs($this->user)->test(PainelFinanceiro::class)
        ->assertSee('1.000,00')
        ->assertDontSee('7.777,00')
        ->assertDontSee('Aluguel da filial');
});

/**
 * Sem um teto comum, cada mês seria desenhado na própria escala e um mês de
 * mil reais teria a mesma barra de um de cem mil.
 */
it('a serie de seis meses usa um teto unico, o maior valor de qualquer lado', function () {
    foreach ([['credito', 30_000], ['debito', 90_000]] as [$sentido, $valor]) {
        MovimentoCaixa::create([
            'emitente_id' => $this->emitente->id, 'conta_financeira_id' => $this->conta->id,
            'sentido' => $sentido, 'valor_centavos' => $valor,
            'descricao' => 'Movimento', 'ocorrido_em' => today(), 'origem_tipo' => 'ajuste',
        ]);
    }

    $componente = Livewire::actingAs($this->user)->test(PainelFinanceiro::class);

    expect($componente->instance()->tetoDaSerie)->toBe(90_000);
});

/**
 * A lista de compromissos funde recebíveis e contas a pagar, e os recebíveis
 * são concatenados primeiro. Por isso o cenário põe o recebível longe e a
 * conta a pagar perto: nenhuma consulta consegue reordenar entre as duas
 * listas, então só a ordenação do serviço explica o resultado. Ordenar dois
 * títulos da mesma tabela não provaria nada, porque o próprio banco costuma
 * devolvê-los já na ordem certa.
 */
it('o painel ordena os compromissos por vencimento, atravessando receber e pagar', function () {
    $fatura = Fatura::create(['emitente_id' => $this->emitente->id, 'titulo' => 'Venda antiga']);

    FaturaParcela::create([
        'fatura_id' => $fatura->id, 'numero' => 1, 'descricao' => 'Recebivel distante',
        'valor_centavos' => 1_000, 'vencimento' => today()->addDays(20),
    ]);

    TituloPagar::create([
        'emitente_id' => $this->emitente->id, 'descricao' => 'Conta proxima',
        'valor_centavos' => 1_000, 'vencimento' => today()->addDay(),
    ]);

    $html = Livewire::actingAs($this->user)->test(PainelFinanceiro::class)->html();

    $proxima = strpos($html, 'Conta proxima');
    $distante = strpos($html, 'Recebivel distante');

    expect($proxima)->not->toBeFalse()
        ->and($distante)->not->toBeFalse()
        ->and($proxima)->toBeLessThan($distante);
});
