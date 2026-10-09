<?php

/**
 * O padrão das telas de cadastro desde 09/10/2026, pedido a partir das
 * listagens de referência: a lista na largura toda, com busca; "Novo" e o
 * lápis de cada linha abrem o formulário numa janela; a lixeira exclui o que
 * nunca foi usado e só inativa o que já está em algum documento.
 */

use App\Enums\Perfil;
use App\Livewire\Financeiro\CentrosCusto;
use App\Livewire\Financeiro\ContasBancarias;
use App\Livewire\Pessoas\Cadastro as Clientes;
use App\Livewire\Transporte\Motoristas;
use App\Livewire\Transporte\Veiculos;
use App\Livewire\Usuarios\Cadastro as Usuarios;
use App\Models\CentroCusto;
use App\Models\ContaFinanceira;
use App\Models\Emitente;
use App\Models\Motorista;
use App\Models\MovimentoCaixa;
use App\Models\Pessoa;
use App\Models\User;
use App\Models\Veiculo;
use Database\Seeders\PerfilSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PerfilSeeder::class);
    $this->emitente = transportadora();
    $user = User::factory()->create(['tenant_id' => $this->emitente->tenant_id]);
    $user->emitentes()->attach($this->emitente);
    setPermissionsTeamId($this->emitente->id);
    $user->assignRole(Perfil::Administrador->value);
    $this->actingAs($user);
});

function motoristaCadastrado(Emitente $emitente, string $nome = 'JOAO DA SILVA', string $cpf = '52998224725'): Motorista
{
    $m = (new Motorista(['nome' => $nome, 'cpf' => $cpf]))->forceFill(['emitente_id' => $emitente->id]);
    $m->save();

    return $m;
}

describe('motoristas', function () {
    it('Novo abre a janela, Cadastrar salva e fecha', function () {
        Livewire::test(Motoristas::class)
            ->assertDontSee('Cadastrar motorista')
            ->call('novo')
            ->assertSet('formularioAberto', true)
            ->assertSee('Cadastrar motorista')
            ->set('nome', 'Maria Souza')
            ->set('cpf', '529.982.247-25')
            ->call('salvar')
            ->assertHasNoErrors()
            ->assertSet('formularioAberto', false)
            ->assertSee('MARIA SOUZA');

        expect(Motorista::sole()->cpf)->toBe('52998224725');
    });

    it('o lápis abre a janela com os dados e salva a alteração', function () {
        $m = motoristaCadastrado($this->emitente);

        Livewire::test(Motoristas::class)
            ->call('editar', $m->id)
            ->assertSet('formularioAberto', true)
            ->assertSet('nome', 'JOAO DA SILVA')
            ->set('telefone', '19999990000')
            ->call('salvar')
            ->assertSet('formularioAberto', false);

        expect($m->fresh()->telefone)->toBe('19999990000');
    });

    it('Cancelar fecha sem salvar e limpa o formulário', function () {
        $m = motoristaCadastrado($this->emitente);

        Livewire::test(Motoristas::class)
            ->call('editar', $m->id)
            ->set('nome', 'OUTRO NOME')
            ->call('fecharFormulario')
            ->assertSet('formularioAberto', false)
            ->assertSet('nome', '')
            ->assertSet('editandoId', null);

        expect($m->fresh()->nome)->toBe('JOAO DA SILVA');
    });

    it('a lixeira exclui o motorista que nunca rodou e só inativa o que está em viagem', function () {
        $semViagem = motoristaCadastrado($this->emitente, 'SEM VIAGEM', '11144477735');
        $viagem = viagemPronta($this->emitente);

        Livewire::test(Motoristas::class)
            ->call('excluir', $semViagem->id)
            ->call('excluir', $viagem->motorista_id)
            ->assertSee('foi inativado');

        expect(Motorista::find($semViagem->id))->toBeNull()
            ->and($viagem->motorista->fresh()->ativo)->toBeFalse();
    });

    it('a busca filtra por nome ou CPF', function () {
        motoristaCadastrado($this->emitente, 'ANA LIMA', '52998224725');
        motoristaCadastrado($this->emitente, 'BRUNO COSTA', '11144477735');

        Livewire::test(Motoristas::class)
            ->set('busca', 'bruno')
            ->assertSee('BRUNO COSTA')
            ->assertDontSee('ANA LIMA')
            ->set('busca', '529.982')
            ->assertSee('ANA LIMA')
            ->assertDontSee('BRUNO COSTA');
    });
});

describe('veículos', function () {
    it('Novo abre a janela, Cadastrar salva e fecha', function () {
        Livewire::test(Veiculos::class)
            ->call('novo')
            ->assertSet('formularioAberto', true)
            ->assertSet('uf', $this->emitente->uf)
            ->set('placa', 'xyz-9a87')
            ->set('tara', '7500')
            ->set('eixos', '3')
            ->call('salvar')
            ->assertHasNoErrors()
            ->assertSet('formularioAberto', false)
            ->assertSee('XYZ-9A87');

        expect(Veiculo::sole()->eixos)->toBe(3);
    });

    it('a lixeira exclui o veículo que nunca rodou e só inativa o que está em viagem', function () {
        $viagem = viagemPronta($this->emitente);
        $parado = (new Veiculo(['tipo' => 'reboque', 'placa' => 'CAR1E23', 'uf' => 'SP', 'tara_kg' => 6000, 'tipo_carroceria' => '02']))
            ->forceFill(['emitente_id' => $this->emitente->id]);
        $parado->save();

        Livewire::test(Veiculos::class)
            ->call('excluir', $parado->id)
            ->call('excluir', $viagem->veiculo_id)
            ->assertSee('foi inativado');

        expect(Veiculo::find($parado->id))->toBeNull()
            ->and($viagem->veiculo->fresh()->ativo)->toBeFalse();
    });

    it('a busca acha pela placa com ou sem traço', function () {
        viagemPronta($this->emitente);

        Livewire::test(Veiculos::class)
            ->set('busca', 'abc-1d')
            ->assertSee('ABC-1D23')
            ->set('busca', 'ZZZ')
            ->assertSee('Nenhum veículo encontrado');
    });
});

function clienteCadastrado(Emitente $emitente, string $nome, string $cnpj): Pessoa
{
    return Pessoa::create([
        'emitente_id' => $emitente->id, 'tipo_pessoa' => 'J', 'documento' => $cnpj, 'razao_social' => $nome,
        'ind_ie_dest' => '9', 'logradouro' => 'RUA A', 'numero' => '1', 'bairro' => 'CENTRO',
        'codigo_municipio' => '3503307', 'municipio' => 'Araras', 'uf' => 'SP', 'cep' => '13600000', 'e_cliente' => true,
    ]);
}

describe('clientes', function () {
    it('Novo cadastro abre a janela e o lápis abre com os dados', function () {
        $cliente = clienteCadastrado($this->emitente, 'CLIENTE UM LTDA', '11444777000161');

        Livewire::test(Clientes::class)
            ->assertSee('CLIENTE UM LTDA')
            ->assertSet('formularioAberto', false)
            ->call('novo')
            ->assertSet('formularioAberto', true)
            ->assertSet('form.razao_social', '')
            ->call('fecharFormulario')
            ->call('editar', $cliente->id)
            ->assertSet('formularioAberto', true)
            ->assertSet('form.razao_social', 'CLIENTE UM LTDA');
    });

    it('a lixeira exclui cliente sem documento e só inativa o tomador de CT-e', function () {
        $solto = clienteCadastrado($this->emitente, 'SEM DOCUMENTO LTDA', '11444777000161');
        $tomador = clienteCadastrado($this->emitente, 'TOMADOR LTDA', '11222333000181');
        $viagem = viagemPronta($this->emitente);
        DB::table('ctes')->where('viagem_id', $viagem->id)->update(['tomador_pessoa_id' => $tomador->id]);

        Livewire::test(Clientes::class)
            ->call('excluir', $solto->id)
            ->call('excluir', $tomador->id)
            ->assertSee('foi inativado');

        expect(Pessoa::find($solto->id))->toBeNull()
            ->and($tomador->fresh()->ativo)->toBeFalse();
    });
});

describe('centros de custo', function () {
    it('o lápis edita nome e essencial, mas não o código', function () {
        $centro = CentroCusto::create(['emitente_id' => $this->emitente->id, 'codigo' => '021', 'nome' => 'Despesas', 'natureza' => 'despesa', 'grupo' => true]);

        Livewire::test(CentrosCusto::class)
            ->call('editar', $centro->id)
            ->assertSet('formularioAberto', true)
            ->set('codigo', '999')
            ->set('nome', 'Despesas operacionais')
            ->set('essencial', '1')
            ->call('salvar')
            ->assertHasNoErrors()
            ->assertSet('formularioAberto', false);

        expect($centro->fresh())->codigo->toBe('021')->nome->toBe('Despesas operacionais')->essencial->toBeTrue();
    });

    it('grupo com filho não deixa de ser grupo', function () {
        $pai = CentroCusto::create(['emitente_id' => $this->emitente->id, 'codigo' => '021', 'nome' => 'Despesas', 'natureza' => 'despesa', 'grupo' => true]);
        CentroCusto::create(['emitente_id' => $this->emitente->id, 'pai_id' => $pai->id, 'codigo' => '021.001', 'nome' => 'Energia', 'natureza' => 'despesa']);

        Livewire::test(CentrosCusto::class)
            ->call('editar', $pai->id)
            ->set('grupo', false)
            ->call('salvar')
            ->assertHasErrors('grupo');
    });

    it('a lixeira exclui centro sem uso e só inativa o que tem filho', function () {
        $pai = CentroCusto::create(['emitente_id' => $this->emitente->id, 'codigo' => '021', 'nome' => 'Despesas', 'natureza' => 'despesa', 'grupo' => true]);
        $filho = CentroCusto::create(['emitente_id' => $this->emitente->id, 'pai_id' => $pai->id, 'codigo' => '021.001', 'nome' => 'Energia', 'natureza' => 'despesa']);

        Livewire::test(CentrosCusto::class)
            ->call('excluir', $pai->id)
            ->call('excluir', $filho->id);

        expect(CentroCusto::find($filho->id))->toBeNull()
            ->and($pai->fresh()->ativo)->toBeFalse();
    });
});

describe('contas bancárias', function () {
    it('Nova conta abre a janela, cria e fecha', function () {
        Livewire::test(ContasBancarias::class)
            ->call('novaConta')
            ->assertSet('formularioAberto', true)
            ->set('nome', 'Conta Banco X')
            ->set('saldoInicial', '1.500,00')
            ->call('salvarConta')
            ->assertHasNoErrors()
            ->assertSet('formularioAberto', false);

        expect(ContaFinanceira::sole()->saldo_inicial_centavos)->toBe(150000);
    });

    it('conta com lançamento não muda o saldo inicial na edição', function () {
        $conta = ContaFinanceira::create(['emitente_id' => $this->emitente->id, 'nome' => 'Caixa', 'tipo' => 'caixa', 'saldo_inicial_centavos' => 1000]);
        MovimentoCaixa::create(['emitente_id' => $this->emitente->id, 'conta_financeira_id' => $conta->id, 'sentido' => 'credito',
            'valor_centavos' => 500, 'descricao' => 'Ajuste', 'ocorrido_em' => today(), 'origem_tipo' => 'ajuste', 'created_at' => now()]);

        Livewire::test(ContasBancarias::class)
            ->call('editarConta', $conta->id)
            ->set('nome', 'Caixa da loja')
            ->set('saldoInicial', '9.999,00')
            ->call('salvarConta')
            ->assertHasNoErrors();

        expect($conta->fresh())->nome->toBe('Caixa da loja')->saldo_inicial_centavos->toBe(1000);
    });

    it('a lixeira exclui conta sem lançamento e só inativa a que tem', function () {
        $vazia = ContaFinanceira::create(['emitente_id' => $this->emitente->id, 'nome' => 'Vazia', 'tipo' => 'corrente']);
        $usada = ContaFinanceira::create(['emitente_id' => $this->emitente->id, 'nome' => 'Usada', 'tipo' => 'corrente']);
        MovimentoCaixa::create(['emitente_id' => $this->emitente->id, 'conta_financeira_id' => $usada->id, 'sentido' => 'debito',
            'valor_centavos' => 500, 'descricao' => 'Taxa', 'ocorrido_em' => today(), 'origem_tipo' => 'ajuste', 'created_at' => now()]);

        Livewire::test(ContasBancarias::class)
            ->call('excluirConta', $vazia->id)
            ->call('excluirConta', $usada->id);

        expect(ContaFinanceira::find($vazia->id))->toBeNull()
            ->and($usada->fresh()->ativo)->toBeFalse();
    });
});

describe('usuários', function () {
    it('a lixeira tira o acesso a esta empresa e não deixa tirar o próprio', function () {
        $outro = User::factory()->create(['tenant_id' => $this->emitente->tenant_id]);
        $outro->emitentes()->attach($this->emitente);
        setPermissionsTeamId($this->emitente->id);
        $outro->assignRole(Perfil::Faturamento->value);

        Livewire::test(Usuarios::class)
            ->call('removerAcesso', $outro->id)
            ->call('removerAcesso', auth()->id())
            ->assertSee('não pode remover o próprio acesso');

        expect($outro->fresh()->emitentes()->withoutGlobalScope('tenant')->exists())->toBeFalse()
            ->and($outro->fresh()->ativo)->toBeFalse()
            ->and(auth()->user()->fresh()->emitentes()->exists())->toBeTrue();
    });

    it('Novo usuário abre a janela e Enter no e-mail verifica antes de salvar', function () {
        Livewire::test(Usuarios::class)
            ->call('novoUsuario')
            ->assertSet('formularioAberto', true)
            ->set('form.email', 'novo@exemplo.com.br')
            ->call('verificarEmail')
            ->assertSet('emailVerificado', true)
            ->assertSet('contaExistente', false)
            ->call('fecharFormulario')
            ->assertSet('formularioAberto', false)
            ->assertSet('emailVerificado', false);
    });
});
