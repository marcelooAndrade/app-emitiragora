<?php

use App\Enums\Perfil;
use App\Enums\Transporte\CteStatus;
use App\Enums\Transporte\MdfeStatus;
use App\Livewire\Transporte\Configuracao;
use App\Livewire\Transporte\Motoristas;
use App\Livewire\Transporte\Veiculos;
use App\Livewire\Transporte\ViagemDetalhe;
use App\Livewire\Transporte\Viagens;
use App\Models\Emitente;
use App\Models\Fatura;
use App\Models\Motorista;
use App\Models\RegraIcmsTransporte;
use App\Models\User;
use App\Models\Veiculo;
use App\Models\Viagem;
use App\Services\Transporte\TransmissorCte;
use Database\Seeders\PerfilSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PerfilSeeder::class);
    Storage::fake('fiscal');
    $this->emitente = transportadora();
    $this->user = User::factory()->create(['tenant_id' => $this->emitente->tenant_id]);
    $this->user->emitentes()->attach($this->emitente);
    setPermissionsTeamId($this->emitente->id);
    $this->user->assignRole(Perfil::Administrador->value);
    $this->actingAs($this->user);
});

function xmlDaFixture(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('nfe.xml', (string) file_get_contents(base_path('tests/Fixtures/xml/nfe-autorizada.xml')));
}

it('lista as viagens e o menu mostra o transporte', function () {
    $this->get('/viagens')
        ->assertOk()
        ->assertSee('Viagens')
        ->assertSee('Motoristas')
        ->assertSee('Nova viagem');
});

it('quem não tem permissão de transporte não entra', function () {
    $semPerfil = User::factory()->create(['tenant_id' => $this->emitente->tenant_id]);
    $semPerfil->emitentes()->attach($this->emitente);
    setPermissionsTeamId($this->emitente->id);

    $this->actingAs($semPerfil)->get('/viagens')->assertForbidden();
});

it('nova viagem abre a tela da viagem', function () {
    Livewire::test(Viagens::class)
        ->call('nova')
        ->assertRedirect(route('viagens.detalhe', Viagem::sole()));

    expect(Viagem::sole()->numero)->toBe(1);
});

it('do XML à fatura numa tela só', function () {
    comGatewayCte(['enviar' => cteAutorizado()]);
    comGatewayMdfe(['enviar' => mdfeAutorizado()]);
    $motorista = (new Motorista(['nome' => 'JOAO DA SILVA', 'cpf' => '52998224725']))->forceFill(['emitente_id' => $this->emitente->id]);
    $motorista->save();
    $viagem = app(App\Services\Transporte\Viagens::class)->criar($this->emitente);

    $tela = Livewire::test(ViagemDetalhe::class, ['viagem' => $viagem->id])
        ->set('arquivos', [xmlDaFixture()])
        ->assertHasNoErrors()
        ->assertSee('METALURGICA PIRACICABA LTDA');

    $nota = $viagem->fresh()->notas->sole();
    $tela->set("pesos.{$nota->id}", '500')
        ->call('salvarPeso', $nota->id)
        ->set('novoVeiculoAberto', true)
        ->set('novoVeiculoPlaca', 'abc-1d23')
        ->set('novoVeiculoTara', '8000')
        ->call('salvarNovoVeiculo')
        ->assertHasNoErrors()
        ->set('motoristaId', $motorista->id)
        ->set('freteValor', '150,00')
        // CIOT para todos (DF-026): sem empresa integrada, o CIOT é digitado.
        ->set('ciotInformarAberto', true)
        ->set('ciotInformadoNumero', '1234.5678.9012')
        ->call('informarCiot')
        ->assertHasNoErrors()
        ->call('emitir')
        ->assertSet('resultado.erros', [])
        ->assertSee('Tudo autorizado');

    $viagem = $viagem->fresh(['ctes', 'mdfe']);
    expect($viagem->veiculo->placa)->toBe('ABC1D23')
        ->and($viagem->ctes->sole()->status)->toBe(CteStatus::Autorizado)
        ->and($viagem->ctes->sole()->valor_total_centavos)->toBe(7500)
        ->and($viagem->mdfe->status)->toBe(MdfeStatus::Autorizado);

    $tela->call('faturar')->assertHasNoErrors();
    expect(Fatura::sole()->parcelas->sole()->valor_centavos)->toBe(7500);
});

it('mostra o que falta quando a emissão para', function () {
    comGatewayCte(['enviar' => cteAutorizado()]);
    $viagem = viagemPronta($this->emitente, ['frete_tonelada_centavos' => 0]);

    Livewire::test(ViagemDetalhe::class, ['viagem' => $viagem->id])
        ->set('freteValor', '')
        ->call('emitir')
        ->assertSee('Informe o frete da viagem');
});

it('tomador pode trocar enquanto o CT-e é rascunho', function () {
    $viagem = viagemPronta($this->emitente);
    $cte = $viagem->ctes->sole();

    Livewire::test(ViagemDetalhe::class, ['viagem' => $viagem->id])->call('definirTomador', $cte->id, '3');

    expect($cte->fresh()->tomador_tipo)->toBe('3');
});

it('não abre viagem de outra empresa do mesmo cliente', function () {
    $filial = Emitente::factory()->create(['tenant_id' => $this->emitente->tenant_id, 'cnpj' => '44555666000199']);
    $alheia = (new Viagem(['data_carregamento' => today()]))->forceFill(['emitente_id' => $filial->id, 'numero' => 1]);
    $alheia->save();

    Livewire::test(ViagemDetalhe::class, ['viagem' => $alheia->id])->assertNotFound();
});

it('cancela o CT-e pela tela com justificativa', function () {
    comGatewayCte(['enviar' => cteAutorizado(), 'cancelar' => eventoRegistrado()]);
    $viagem = viagemPronta($this->emitente);
    $cte = app(TransmissorCte::class)->transmitir($viagem->ctes->sole());

    Livewire::test(ViagemDetalhe::class, ['viagem' => $viagem->id])
        ->call('abrirCancelamento', $cte->id)
        ->set('justificativa', 'curta')
        ->call('cancelarCte')
        ->assertHasErrors('justificativa')
        ->set('justificativa', 'Frete cancelado pelo cliente antes da saida')
        ->call('cancelarCte')
        ->assertHasNoErrors();

    expect($cte->fresh()->status)->toBe(CteStatus::Cancelado);
});

it('cadastra veículo de terceiro exigindo o proprietário', function () {
    Livewire::test(Veiculos::class)
        ->set('placa', 'XYZ9A87')
        ->set('tara', '7000')
        ->set('proprietarioTipo', 'terceiro')
        ->call('salvar')
        ->assertHasErrors(['proprietarioDocumento', 'proprietarioNome', 'proprietarioRntrc'])
        ->set('proprietarioDocumento', '529.982.247-25')
        ->set('proprietarioNome', 'Jose Autonomo')
        ->set('proprietarioRntrc', '87654321')
        ->call('salvar')
        ->assertHasNoErrors();

    expect(Veiculo::sole())
        ->placa->toBe('XYZ9A87')
        ->proprietario_documento->toBe('52998224725')
        ->proprietario_nome->toBe('JOSE AUTONOMO');
});

it('recusa motorista com CPF inválido', function () {
    Livewire::test(Motoristas::class)
        ->set('nome', 'Fulano')
        ->set('cpf', '111.111.111-11')
        ->call('salvar')
        ->assertHasErrors('cpf');

    expect(Motorista::count())->toBe(0);
});

it('configura RNTRC, seguro e regra de ICMS', function () {
    Livewire::test(Configuracao::class)
        ->set('rntrc', '99887766')
        ->set('cfop', '6352')
        ->call('salvar')
        ->assertHasNoErrors()
        ->set('regraNome', 'SP para MG')
        ->set('regraUfOrigem', 'sp')
        ->set('regraUfDestino', 'mg')
        ->set('regraCst', '00')
        ->set('regraAliquota', '12,00')
        ->set('regraPercurso', 'rj')
        ->call('salvarRegra')
        ->assertHasNoErrors();

    $config = $this->emitente->fresh()->configuracaoTransporte();
    expect($config->rntrc)->toBe('99887766')
        ->and($config->cfop)->toBe('5352')
        ->and(RegraIcmsTransporte::where('nome', 'SP para MG')->sole())
        ->uf_destino->toBe('MG')
        ->percurso_ufs->toBe(['RJ']);
});

it('perfil de faturamento opera viagem mas não mexe na configuração', function () {
    $faturamento = User::factory()->create(['tenant_id' => $this->emitente->tenant_id]);
    $faturamento->emitentes()->attach($this->emitente);
    setPermissionsTeamId($this->emitente->id);
    $faturamento->assignRole(Perfil::Faturamento->value);

    $this->actingAs($faturamento);
    Livewire::test(Viagens::class)->call('nova')->assertRedirect();
    Livewire::test(Configuracao::class)->call('salvar')->assertForbidden();
});

it('baixa o XML autorizado só da própria empresa', function () {
    comGatewayCte(['enviar' => cteAutorizado()]);
    $viagem = viagemPronta($this->emitente);
    $cte = app(TransmissorCte::class)->transmitir($viagem->ctes->sole());

    $this->get(route('transporte.cte.xml', $cte))->assertOk();

    $filial = Emitente::factory()->create(['tenant_id' => $this->emitente->tenant_id, 'cnpj' => '44555666000199']);
    $this->user->emitentes()->attach($filial);
    session(['emitente_atual_id' => $filial->id]);
    $this->get(route('transporte.cte.xml', $cte))->assertNotFound();
});
