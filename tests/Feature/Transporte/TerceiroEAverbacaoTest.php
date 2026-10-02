<?php

use App\Enums\Fiscal\Ambiente;
use App\Enums\Perfil;
use App\Livewire\Transporte\Configuracao;
use App\Livewire\Transporte\ViagemDetalhe;
use App\Models\ContaPagar;
use App\Models\ContratoFrete;
use App\Models\Emitente;
use App\Models\User;
use App\Models\Viagem;
use App\Services\Transporte\AverbacaoAtm;
use App\Services\Transporte\ContratosFrete;
use App\Services\Transporte\EventosCte;
use App\Services\Transporte\MdfeXml;
use App\Services\Transporte\TransmissorCte;
use App\Services\Transporte\TransmissorMdfe;
use App\Services\Transporte\TransporteException;
use App\Services\Transporte\TransporteToolsFactory;
use App\Support\ValorPorExtenso;
use Database\Seeders\PerfilSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use NFePHP\Common\Validator;

/** A viagem pronta, com o cavalo passado para um TAC independente. */
function viagemDeTerceiro(): Viagem
{
    $viagem = viagemPronta();
    $viagem->veiculo->update([
        'proprietario_tipo' => 'terceiro',
        'proprietario_documento' => '52998224725',
        'proprietario_nome' => 'JOSE AUTONOMO',
        'proprietario_rntrc' => '87654321',
        'proprietario_tp' => '1',
    ]);

    return $viagem->fresh();
}

function dadosContrato(array $extra = []): array
{
    return [
        'frete_centavos' => 100000,
        'adiantamento_centavos' => 80000,
        'imposto_renda_centavos' => 1500,
        'vencimento_saldo' => '2026-10-20',
        'forma_pagamento' => 'pix',
        'chave_pix' => 'jose@exemplo.com',
        'ciot' => '123456789012',
        ...$extra,
    ];
}

function comAtm(Viagem $viagem): void
{
    $viagem->emitente->configuracaoTransporte()->update(['atm_usuario' => 'usuario', 'atm_senha' => 'senha', 'atm_codigo' => '12345']);
}

function respostaAtmAverbada(string $numero = '0650301234567890123456789'): array
{
    return ['Averbado' => ['Protocolo' => 'PROT-1', 'DadosSeguro' => [['NumeroAverbacao' => $numero, 'CNPJSeguradora' => '11444777000161']]]];
}

it('o contrato do frete vira adiantamento e saldo em contas a pagar', function () {
    $viagem = viagemDeTerceiro();

    $contrato = app(ContratosFrete::class)->salvar($viagem, dadosContrato());

    expect($contrato->saldo_centavos)->toBe(18500)
        ->and($contrato->contratado_documento)->toBe('52998224725')
        ->and($contrato->contaAdiantamento->valor_centavos)->toBe(80000)
        ->and($contrato->contaAdiantamento->vencimento->toDateString())->toBe($viagem->data_carregamento->toDateString())
        ->and($contrato->contaSaldo->valor_centavos)->toBe(18500)
        ->and($contrato->contaSaldo->vencimento->toDateString())->toBe('2026-10-20')
        ->and($contrato->contaSaldo->fornecedor)->toBe('JOSE AUTONOMO')
        ->and($viagem->fresh()->frete_motorista_centavos)->toBe(100000);
});

it('regravar o contrato não mexe na conta que já foi paga', function () {
    $viagem = viagemDeTerceiro();
    $contratos = app(ContratosFrete::class);
    $contrato = $contratos->salvar($viagem, dadosContrato());
    $contrato->contaAdiantamento->forceFill(['status' => 'pago', 'pago_em' => now()])->save();

    $contrato = $contratos->salvar($viagem->fresh(), dadosContrato(['adiantamento_centavos' => 50000]));

    expect($contrato->contaAdiantamento->valor_centavos)->toBe(80000)
        ->and($contrato->contaAdiantamento->status)->toBe('pago')
        ->and($contrato->contaSaldo->valor_centavos)->toBe(48500)
        ->and(ContaPagar::count())->toBe(2);
});

it('sem adiantamento informado sugere o percentual da empresa', function () {
    $viagem = viagemDeTerceiro();

    expect(app(ContratosFrete::class)->adiantamentoPadrao($viagem, 100005))->toBe(80004);
});

it('recusa contrato para frota própria', function () {
    app(ContratosFrete::class)->salvar(viagemPronta(), dadosContrato());
})->throws(TransporteException::class, 'veículo próprio');

it('recusa contrato com saldo negativo', function () {
    app(ContratosFrete::class)->salvar(viagemDeTerceiro(), dadosContrato(['imposto_renda_centavos' => 30000]));
})->throws(TransporteException::class, 'saldo ficaria negativo');

it('MDF-e de terceiro pede o contrato e, para TAC, o CIOT', function () {
    $viagem = viagemDeTerceiro();
    comGatewayCte(['enviar' => cteAutorizado()]);
    app(TransmissorCte::class)->transmitir($viagem->ctes->sole());

    expect(collect(app(TransmissorMdfe::class)->pendencias($viagem->fresh()))->join(' '))->toContain('contrato do frete');

    app(ContratosFrete::class)->salvar($viagem->fresh(), dadosContrato(['ciot' => '']));
    expect(collect(app(TransmissorMdfe::class)->pendencias($viagem->fresh()))->join(' '))->toContain('CIOT');
});

it('com contrato o MDF-e leva CIOT e pagamento a prazo, e passa no xsd', function () {
    $viagem = viagemDeTerceiro();
    comGatewayCte(['enviar' => cteAutorizado()]);
    app(TransmissorCte::class)->transmitir($viagem->ctes->sole());
    app(ContratosFrete::class)->salvar($viagem->fresh(), dadosContrato());

    $mdfe = app(TransmissorMdfe::class)->preparar($viagem->fresh());
    $mdfe->forceFill(['numero' => 3])->save();
    $assinado = app(TransporteToolsFactory::class)->mdfe($viagem->emitente)->signMDFe(app(MdfeXml::class)->montar($mdfe->fresh())['xml']);

    expect(Validator::isValid($assinado, base_path('vendor/nfephp-org/sped-mdfe/schemes/PL_MDFe_300a/mdfe_v3.00.xsd')))->toBeTrue()
        ->and($assinado)->toContain('<CIOT>123456789012</CIOT>')
        ->and($assinado)->toContain('<vContrato>1000.00</vContrato>')
        ->and($assinado)->toContain('<indPag>1</indPag>')
        ->and($assinado)->toContain('<vAdiant>800.00</vAdiant>')
        ->and($assinado)->toContain('<vParcela>185.00</vParcela>')
        ->and($assinado)->toContain('<PIX>jose@exemplo.com</PIX>');
});

it('pagamento por transferência à vista também passa no xsd', function () {
    $viagem = viagemDeTerceiro();
    comGatewayCte(['enviar' => cteAutorizado()]);
    app(TransmissorCte::class)->transmitir($viagem->ctes->sole());
    app(ContratosFrete::class)->salvar($viagem->fresh(), dadosContrato([
        'adiantamento_centavos' => 98500, 'forma_pagamento' => 'transferencia', 'banco_codigo' => '001', 'agencia' => '1234', 'conta' => '99999-0',
    ]));

    $mdfe = app(TransmissorMdfe::class)->preparar($viagem->fresh());
    $mdfe->forceFill(['numero' => 4])->save();
    $assinado = app(TransporteToolsFactory::class)->mdfe($viagem->emitente)->signMDFe(app(MdfeXml::class)->montar($mdfe->fresh())['xml']);

    expect(Validator::isValid($assinado, base_path('vendor/nfephp-org/sped-mdfe/schemes/PL_MDFe_300a/mdfe_v3.00.xsd')))->toBeTrue()
        ->and($assinado)->toContain('<indPag>0</indPag>')
        ->and($assinado)->toContain('<codBanco>001</codBanco>')
        ->and($assinado)->not->toContain('<infPrazo>');
});

it('cancelar o único CT-e cancela as contas do contrato ainda não pagas', function () {
    $viagem = viagemDeTerceiro();
    comGatewayCte(['enviar' => cteAutorizado(), 'cancelar' => eventoRegistrado()]);
    $cte = app(TransmissorCte::class)->transmitir($viagem->ctes->sole());
    $contrato = app(ContratosFrete::class)->salvar($viagem->fresh(), dadosContrato());

    app(EventosCte::class)->cancelar($cte, 'Frete cancelado pelo cliente antes da saida');

    expect($contrato->fresh()->status)->toBe('cancelado')
        ->and(ContaPagar::pluck('status')->unique()->all())->toBe(['cancelado']);
});

it('averba na AT&M ao autorizar e o número entra no MDF-e', function () {
    Http::fake([
        '*/Auth' => Http::response(['token' => 'tok-1']),
        '*/CTe' => Http::response(respostaAtmAverbada()),
    ]);
    $viagem = viagemPronta();
    comAtm($viagem);
    comGatewayCte(['enviar' => cteAutorizado()]);

    $cte = app(TransmissorCte::class)->transmitir($viagem->ctes->sole())->fresh();
    $mdfe = app(TransmissorMdfe::class)->preparar($viagem->fresh());

    expect($cte->averbacao_status)->toBe('aprovada')
        ->and($cte->averbacao_numero)->toBe('0650301234567890123456789')
        ->and($cte->averbacao_protocolo)->toBe('PROT-1')
        ->and($mdfe->seguro['averbacoes'])->toBe(['0650301234567890123456789']);
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/CTe') && $r->hasHeader('Authorization', 'Bearer tok-1') && $r->body() === '<cteProc/>');
});

it('AT&M fora do ar não desfaz a autorização do CT-e', function () {
    Http::fake(['*' => Http::response(['Erros' => ['Erro' => ['Codigo' => '500', 'Descricao' => 'Indisponivel']]], 500)]);
    $viagem = viagemPronta();
    comAtm($viagem);
    comGatewayCte(['enviar' => cteAutorizado()]);

    $cte = app(TransmissorCte::class)->transmitir($viagem->ctes->sole())->fresh();

    expect($cte->status->value)->toBe('autorizado')
        ->and($cte->averbacao_status)->toBe('recusada')
        ->and($cte->averbacao_mensagem)->toContain('Indisponivel');
});

it('token vencido na AT&M é renovado uma vez', function () {
    Http::fake([
        '*/Auth' => Http::sequence()->push(['token' => 'velho'])->push(['token' => 'novo']),
        '*/CTe' => Http::sequence()->push([], 401)->push(respostaAtmAverbada('AV-2')),
    ]);
    $viagem = viagemPronta();
    comAtm($viagem);
    comGatewayCte(['enviar' => cteAutorizado()]);

    $cte = app(TransmissorCte::class)->transmitir($viagem->ctes->sole())->fresh();

    expect($cte->averbacao_numero)->toBe('AV-2');
    Http::assertSentCount(4);
});

it('em produção a averbação automática fica travada, como no Transm', function () {
    Http::fake();
    $viagem = viagemPronta();
    comAtm($viagem);
    comGatewayCte(['enviar' => cteAutorizado()]);
    $viagem->ctes->sole()->forceFill(['ambiente' => Ambiente::Producao])->save();

    // Na autorização a trava só pula a averbação; à mão, ela explica por quê.
    $cte = app(TransmissorCte::class)->transmitir($viagem->ctes->sole()->fresh());
    expect($cte->fresh()->averbacao_status)->toBeNull()
        ->and(fn () => app(AverbacaoAtm::class)->averbar($cte->fresh()))
        ->toThrow(TransporteException::class, 'só em homologação');
    Http::assertNothingSent();
});

it('escreve o valor do recibo por extenso', function () {
    expect(ValorPorExtenso::reais(125050))->toBe('MIL DUZENTOS E CINQUENTA REAIS E CINQUENTA CENTAVOS')
        ->and(ValorPorExtenso::reais(100))->toBe('UM REAL')
        ->and(ValorPorExtenso::reais(150000000))->toBe('UM MILHÃO E QUINHENTOS MIL REAIS');
});

describe('telas', function () {
    beforeEach(function () {
        $this->seed(PerfilSeeder::class);
        $this->viagem = viagemDeTerceiro();
        $this->emitente = $this->viagem->emitente;
        $this->user = User::factory()->create(['tenant_id' => $this->emitente->tenant_id]);
        $this->user->emitentes()->attach($this->emitente);
        setPermissionsTeamId($this->emitente->id);
        $this->user->assignRole(Perfil::Administrador->value);
        $this->actingAs($this->user);
    });

    it('salvar a viagem com cavalo de terceiro grava o contrato junto', function () {
        Livewire::test(ViagemDetalhe::class, ['viagem' => $this->viagem->id])
            ->assertSee('Pagamento ao terceiro')
            ->set('contratoFrete', '1.000,00')
            ->assertSee('200,00')
            ->set('contratoPix', 'jose@exemplo.com')
            ->set('contratoCiot', '123456789012')
            ->call('salvarViagem')
            ->assertHasNoErrors()
            ->assertSee('Contrato em PDF');

        $contrato = ContratoFrete::sole();
        expect($contrato->adiantamento_centavos)->toBe(80000)
            ->and($contrato->saldo_centavos)->toBe(20000)
            ->and(ContaPagar::count())->toBe(2);
    });

    it('mostra o erro do contrato no lugar dele', function () {
        Livewire::test(ViagemDetalhe::class, ['viagem' => $this->viagem->id])
            ->set('contratoFrete', '1.000,00')
            ->set('contratoPix', '')
            ->call('salvarViagem')
            ->assertHasErrors('contrato')
            ->assertSee('chave Pix');

        expect(ContratoFrete::count())->toBe(0);
    });

    it('baixa o contrato em PDF só da própria empresa', function () {
        $contrato = app(ContratosFrete::class)->salvar($this->viagem, dadosContrato());

        $resposta = $this->get(route('transporte.contrato', $contrato))->assertOk();
        expect($resposta->headers->get('Content-Type'))->toBe('application/pdf')
            ->and(substr((string) $resposta->getContent(), 0, 4))->toBe('%PDF');

        $filial = Emitente::factory()->create(['tenant_id' => $this->emitente->tenant_id, 'cnpj' => '44555666000199']);
        $this->user->emitentes()->attach($filial);
        session(['emitente_atual_id' => $filial->id]);
        $this->get(route('transporte.contrato', $contrato))->assertNotFound();
    });

    it('guarda as credenciais da AT&M cifradas e mantém a senha em branco', function () {
        Livewire::test(Configuracao::class)
            ->set('atmUsuario', 'usuario.atm')
            ->set('atmCodigo', '54321')
            ->call('salvar')
            ->assertHasErrors('atmSenha')
            ->set('atmSenha', 'segredo')
            ->call('salvar')
            ->assertHasNoErrors()
            ->assertSet('atmSenha', '')
            ->set('adiantamentoPercentual', '70')
            ->call('salvar')
            ->assertHasNoErrors();

        $config = $this->emitente->fresh()->configuracaoTransporte();
        $bruto = DB::table('emitente_transporte')->where('emitente_id', $this->emitente->id)->value('atm_senha');
        expect($config->atm_senha)->toBe('segredo')
            ->and($bruto)->not->toContain('segredo')
            ->and($config->adiantamento_percentual)->toBe(70)
            ->and($config->temAtm())->toBeTrue();
    });
});
