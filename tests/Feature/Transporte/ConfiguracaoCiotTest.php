<?php

/**
 * A página Configurações, CIOT (escolha da empresa, credenciais e forma de
 * recebimento) e o CIOT na tela da viagem. CIOT para todos, DF-026.
 */

use App\Enums\Fiscal\Ambiente;
use App\Enums\Perfil;
use App\Livewire\Transporte\ConfiguracaoCiot;
use App\Livewire\Transporte\ViagemDetalhe;
use App\Models\Ciot;
use App\Models\Emitente;
use App\Models\User;
use Database\Seeders\PerfilSeeder;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function usuarioCiot(Emitente $emitente, Perfil $perfil = Perfil::Administrador): User
{
    $user = User::factory()->create(['tenant_id' => $emitente->tenant_id]);
    $user->emitentes()->attach($emitente);
    setPermissionsTeamId($emitente->id);
    $user->assignRole($perfil->value);

    return $user;
}

beforeEach(function () {
    $this->seed(PerfilSeeder::class);
    $this->emitente = transportadora();
    $this->actingAs(usuarioCiot($this->emitente));
});

it('lista as empresas de CIOT e o menu leva até a página', function () {
    $this->get(route('transporte.ciot'))
        ->assertOk()
        ->assertSee('Digitar o CIOT')
        ->assertSee('e-Frete')
        ->assertSee('Só homologação, até o teste nesta empresa')
        ->assertSee('href="'.route('transporte.ciot').'"', false);
});

it('salva as credenciais cifradas e nunca devolve o segredo para a tela', function () {
    Livewire::test(ConfiguracaoCiot::class)
        ->set('provedor', 'efrete')
        ->set('homologacao.usuario', 'usuario-ef')
        ->set('homologacao.senha', 'senha-secreta')
        ->set('homologacao.integrador', 'hash-secreto')
        ->call('salvar')
        ->assertHasNoErrors()
        ->assertSet('homologacao.senha', '')
        ->assertDontSee('senha-secreta')
        ->assertSee('Salvo. Em branco, continua o mesmo.');

    $config = $this->emitente->configuracaoCiot()->fresh();
    expect($config->provedor)->toBe('efrete')
        ->and($config->credenciais('efrete', Ambiente::Homologacao))->toMatchArray(['usuario' => 'usuario-ef', 'senha' => 'senha-secreta', 'integrador' => 'hash-secreto'])
        ->and($config->getRawOriginal('credenciais_homologacao'))->not->toContain('senha-secreta');

    Livewire::test(ConfiguracaoCiot::class)->set('homologacao.usuario', 'outro-usuario')->call('salvar');

    expect($this->emitente->configuracaoCiot()->fresh()->credenciais('efrete', Ambiente::Homologacao))
        ->toMatchArray(['usuario' => 'outro-usuario', 'senha' => 'senha-secreta']);
});

it('recebimento por transferência exige banco, agência e conta', function () {
    Livewire::test(ConfiguracaoCiot::class)
        ->set('recebimentoTipo', 'transferencia')
        ->call('salvar')
        ->assertHasErrors(['recebimentoBanco', 'recebimentoAgencia', 'recebimentoConta']);

    Livewire::test(ConfiguracaoCiot::class)
        ->set('recebimentoTipo', 'transferencia')
        ->set('recebimentoBanco', '001')
        ->set('recebimentoAgencia', '1234')
        ->set('recebimentoConta', '99999-0')
        ->call('salvar')
        ->assertHasNoErrors();

    expect($this->emitente->configuracaoCiot()->fresh()->recebimento_banco)->toBe('001');
});

it('testar conexão salva e mostra o que a empresa respondeu', function () {
    provedorCiotFake([], $this->emitente);

    Livewire::test(ConfiguracaoCiot::class)
        ->set('homologacao.token', 'tk')
        ->call('testarConexao')
        ->assertHasNoErrors()
        ->assertSee('Conexão aceita.');
});

it('quem só vê não salva a configuração', function () {
    $this->actingAs(usuarioCiot($this->emitente, Perfil::Faturamento));

    $this->get(route('transporte.ciot'))->assertOk();
    Livewire::test(ConfiguracaoCiot::class)->set('provedor', 'efrete')->call('salvar')->assertForbidden();
});

it('na viagem, os dados do CIOT salvam com a viagem e travam depois do CIOT registrado', function () {
    $viagem = viagemPronta($this->emitente);

    Livewire::test(ViagemDetalhe::class, ['viagem' => $viagem->id])
        ->assertSee('Dados do CIOT')
        ->set('ciotDistancia', '320')
        ->set('ciotTipoCarga', '1')
        ->set('ciotTipoOperacao', '1')
        ->call('salvarViagem')
        ->assertHasNoErrors()
        ->set('ciotInformarAberto', true)
        ->set('ciotInformadoNumero', '123456789012/4321')
        ->call('informarCiot')
        ->assertHasNoErrors()
        ->assertSee('123456789012/4321')
        ->set('ciotDistancia', '999')
        ->call('salvarViagem');

    $viagem->refresh();
    expect($viagem->distancia_km)->toBe(320)
        ->and($viagem->tipo_carga)->toBe(1)
        ->and($viagem->tipo_operacao)->toBe('1')
        ->and($viagem->ciotVigente->numero)->toBe('123456789012');
});

it('cancelar o CIOT pela tela pede o motivo', function () {
    $viagem = viagemPronta($this->emitente);
    comCiotInformado($viagem);

    Livewire::test(ViagemDetalhe::class, ['viagem' => $viagem->id])
        ->set('ciotMotivo', 'curto')
        ->call('cancelarCiot')
        ->assertHasErrors('ciot')
        ->set('ciotMotivo', 'Número digitado errado, vou informar de novo')
        ->call('cancelarCiot')
        ->assertHasNoErrors();

    expect(Ciot::sole()->situacao)->toBe('cancelado');
});

it('o PDF do CIOT baixa só da própria empresa', function () {
    Storage::fake('fiscal');
    $viagem = viagemPronta($this->emitente);
    $ciot = comCiotInformado($viagem);
    Storage::disk('fiscal')->put('transporte/ciot.pdf', '%PDF ciot');
    $ciot->forceFill(['pdf_path' => 'transporte/ciot.pdf'])->save();

    $this->get(route('transporte.ciot.pdf', $ciot))->assertOk();

    $outra = Emitente::factory()->create(['tenant_id' => $this->emitente->tenant_id]);
    $ciot->forceFill(['emitente_id' => $outra->id])->save();
    $this->get(route('transporte.ciot.pdf', $ciot))->assertNotFound();
});
