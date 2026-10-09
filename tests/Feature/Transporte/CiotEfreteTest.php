<?php

use App\Enums\Fiscal\Ambiente;
use App\Enums\Perfil;
use App\Livewire\Transporte\ViagemDetalhe;
use App\Models\Ciot;
use App\Models\User;
use App\Models\Viagem;
use App\Services\Transporte\Ciot\ServicoCiot;
use App\Services\Transporte\ContratosFrete;
use App\Services\Transporte\Efrete\GatewaySoapEfrete;
use App\Services\Transporte\EmissaoViagem;
use App\Services\Transporte\TransmissorCte;
use App\Services\Transporte\TransmissorMdfe;
use App\Services\Transporte\TransporteException;
use Database\Seeders\PerfilSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/** O SOAP do e-Frete trocado por um roteiro; guarda o que foi enviado. */
function soapEfreteFake(array $roteiro): object
{
    $fake = new class($roteiro) implements GatewaySoapEfrete
    {
        public array $enviados = [];

        public function __construct(public array $roteiro) {}

        public function adicionarOperacao(array $payload, array $segredos): array
        {
            return $this->responder('adicionar', $payload);
        }

        public function obterCodigoOperacao(array $payload, array $segredos): array
        {
            return $this->responder('obter', $payload);
        }

        private function responder(string $metodo, array $payload): array
        {
            $this->enviados[$metodo][] = $payload;
            $r = $this->roteiro[$metodo] ?? ['Sucesso' => false, 'Mensagem' => "sem roteiro para {$metodo}"];
            if (is_array($r) && array_is_list($r) && $r !== []) {
                $r = count($this->roteiro[$metodo]) > 1 ? array_shift($this->roteiro[$metodo]) : $this->roteiro[$metodo][0];
            }
            if ($r instanceof Throwable) {
                throw $r;
            }

            return $r;
        }
    };
    app()->instance(GatewaySoapEfrete::class, $fake);

    return $fake;
}

function restEfreteFake(array $situacao = ['Sucesso' => true, 'RNTRCAtivo' => true]): void
{
    Http::fake([
        '*/services/Logon/Login' => Http::response(['Sucesso' => true, 'Token' => 'tk-123']),
        '*/services/motoristas/gravar' => Http::response(['Sucesso' => true]),
        '*/services/proprietarios/gravarV2' => Http::response(['Sucesso' => true]),
        '*/services/veiculos/gravar' => Http::response(['Sucesso' => true]),
        '*/services/Pef/ConsultaSituacaoTransportador' => Http::response($situacao),
        '*/services/Pef/ObterOperacaoTransportePdf' => Http::response(['Sucesso' => true, 'Pdf' => base64_encode('%PDF-1.4 ciot')]),
        '*/Services/Pef/EncerrarOperacaoTransporte' => Http::response(['Sucesso' => true]),
        '*/Services/Pef/ObterCodigoIdentificacaoOperacaoTransportePorIdOperacaoCliente' => Http::response(['Sucesso' => true]),
    ]);
}

/**
 * Viagem de terceiro (TAC), com tudo que o e-Frete pede e o CT-e autorizado.
 * Desde 09/10/2026 (CIOT para todos) o e-Frete é uma das empresas de CIOT:
 * credenciais em Configurações, CIOT, e a operação pedida pelo ServicoCiot.
 */
function viagemEfrete(bool $autorizarCte = true, bool $massaAntt = false): Viagem
{
    config(['fiscal.efrete.espera_consulta_ms' => 0]);
    $viagem = viagemPronta();
    $config = $viagem->emitente->configuracaoCiot();
    $config->update(['provedor' => 'efrete']);
    $config->guardarCredenciais('efrete', Ambiente::Homologacao, [
        'usuario' => 'usuario', 'senha' => 'senha', 'integrador' => 'hash-integrador', 'embalagem' => 'Pallet', 'massa_antt' => $massaAntt,
    ]);
    $viagem->update(['distancia_km' => 180, 'tipo_carga' => 5, 'previsao_entrega' => today()->addDays(2)->toDateString()]);
    $viagem->motorista->update([
        'cnh' => '12345678901', 'telefone' => '19999998888', 'nascimento' => '1980-05-10',
        'cep' => '13602200', 'municipio_codigo' => '3503307', 'logradouro' => 'RUA A', 'numero' => '10', 'bairro' => 'CENTRO',
    ]);
    // Caminhão toco (tipo de rodado 02): sem carreta, sem a exigência de
    // implemento que o cavalo mecânico tem no CIOT.
    $viagem->veiculo->update([
        'chassi' => '9BWZZZ377VT004251', 'eixos' => 3, 'tipo_rodado' => '02',
        'proprietario_tipo' => 'terceiro', 'proprietario_documento' => '52998224725', 'proprietario_nome' => 'JOSE AUTONOMO',
        'proprietario_rntrc' => '87654321', 'proprietario_tp' => '1',
        'proprietario_cep' => '13602200', 'proprietario_municipio_codigo' => '3503307', 'proprietario_logradouro' => 'RUA B',
        'proprietario_numero' => '20', 'proprietario_bairro' => 'CENTRO',
    ]);
    if ($autorizarCte) {
        comGatewayCte(['enviar' => cteAutorizado()]);
        app(TransmissorCte::class)->transmitir($viagem->fresh()->ctes->sole());
    }
    app(ContratosFrete::class)->salvar($viagem->fresh(), [
        'frete_centavos' => 100000, 'adiantamento_centavos' => 80000, 'vencimento_saldo' => today()->addDays(7)->toDateString(),
        'forma_pagamento' => 'pix', 'chave_pix' => 'jose@exemplo.com',
    ]);

    return $viagem->fresh();
}

it('gera o CIOT, guarda o PDF e o número vai para a viagem e o MDF-e', function () {
    $viagem = viagemEfrete();
    restEfreteFake();
    $soap = soapEfreteFake(['adicionar' => ['Sucesso' => true, 'CodigoIdentificacaoOperacao' => '123456789012/4321', 'ProtocoloServico' => 'PROT-9']]);

    $ciot = app(ServicoCiot::class)->garantir($viagem);

    $operacao = $soap->enviados['adicionar'][0];
    expect($ciot->numero)->toBe('123456789012')
        ->and($ciot->verificador)->toBe('4321')
        ->and($ciot->situacao)->toBe('registrado')
        ->and($ciot->provedor)->toBe('efrete')
        ->and($ciot->protocolo)->toBe('PROT-9')
        ->and(Storage::disk('fiscal')->get($ciot->pdf_path))->toBe('%PDF-1.4 ciot')
        ->and($operacao['IdOperacaoCliente'])->toBe("EA-{$viagem->emitente_id}-{$viagem->contrato->id}")
        ->and($operacao['Contratado'])->toBe(['CpfOuCnpj' => '52998224725', 'RNTRC' => '087654321'])
        ->and($operacao['CodigoNCMNaturezaCarga'])->toBe('7222')
        ->and($operacao['TipoEmbalagem'])->toBe('Pallet')
        ->and($operacao['Viagens']['DistanciaPercorrida'])->toBe(180)
        ->and($operacao['Pagamentos'])->toHaveCount(2)
        ->and($operacao['Pagamentos'][0]['TipoChavePix'])->toBe('Email')
        ->and($operacao['Viagens']['Valores']['TotalDeQuitacao'])->toEqual(200.0)
        ->and($operacao['Viagens']['NotasFiscais']['NotaFiscal'])->toHaveCount(1)
        ->and(json_encode($ciot->resposta))->not->toContain('tk-123');
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/services/veiculos/gravar') && $r['Veiculo']['Chassi'] === '9BWZZZ377VT004251');

    $mdfe = app(TransmissorMdfe::class)->preparar($viagem->fresh());
    expect($mdfe->ciot)->toBe('123456789012');
});

it('diz tudo que falta antes de chamar o e-Frete', function () {
    $viagem = viagemEfrete();
    $viagem->motorista->update(['cnh' => null, 'nascimento' => null]);
    $viagem->veiculo->update(['chassi' => null]);
    Http::fake();
    soapEfreteFake([]);

    expect(fn () => app(ServicoCiot::class)->garantir($viagem->fresh()))
        ->toThrow(TransporteException::class, 'CNH com 11 dígitos, data de nascimento');
    Http::assertNothingSent();
    expect(Ciot::sole()->situacao)->toBe('recusado');
});

it('placa fora da frota na ANTT para antes de abrir a operação', function () {
    $viagem = viagemEfrete();
    restEfreteFake(['Sucesso' => true, 'RNTRCAtivo' => true, 'Veiculos' => [['Placa' => 'ABC1D23', 'FazParteDaFrota' => false]]]);
    $soap = soapEfreteFake([]);

    expect(fn () => app(ServicoCiot::class)->garantir($viagem))->toThrow(TransporteException::class, 'ABC1D23');
    expect($soap->enviados)->toBe([]);
});

it('sem número na hora fica processando e a consulta seguinte registra', function () {
    $viagem = viagemEfrete();
    restEfreteFake();
    $soap = soapEfreteFake([
        'adicionar' => ['Sucesso' => true, 'Mensagem' => 'Em processamento'],
        'obter' => [['Sucesso' => true], ['Sucesso' => true], ['Sucesso' => true], ['Sucesso' => true, 'CodigoIdentificacaoOperacao' => '210987654321']],
    ]);
    $servico = app(ServicoCiot::class);

    expect($servico->garantir($viagem)->situacao)->toBe('processando');
    $ciot = $servico->garantir($viagem->fresh());

    expect($ciot->numero)->toBe('210987654321')
        ->and($ciot->situacao)->toBe('registrado')
        ->and($soap->enviados['adicionar'])->toHaveCount(1)
        ->and(Ciot::count())->toBe(1);
});

it('operação já cadastrada não gera outro CIOT: acha a que existe', function () {
    $viagem = viagemEfrete();
    restEfreteFake();
    soapEfreteFake([
        'adicionar' => new TransporteException('O e-Frete recusou: Operação de transporte já cadastrada'),
        'obter' => ['Sucesso' => true, 'CodigoIdentificacaoOperacao' => '111122223333'],
    ]);

    expect(app(ServicoCiot::class)->garantir($viagem)->numero)->toBe('111122223333');
});

it('em produção o e-Frete fica bloqueado até o teste em homologação', function () {
    $viagem = viagemEfrete();
    $viagem->emitente->forceFill(['ambiente' => Ambiente::Producao])->save();
    Http::fake();
    soapEfreteFake([]);

    expect(fn () => app(ServicoCiot::class)->garantir($viagem->fresh()))->toThrow(TransporteException::class, 'produção fica bloqueada');
    Http::assertNothingSent();
});

it('massa de teste da ANTT troca contratado e placas e não regrava cadastros', function () {
    $viagem = viagemEfrete(massaAntt: true);
    restEfreteFake();
    $soap = soapEfreteFake(['adicionar' => ['Sucesso' => true, 'CodigoIdentificacaoOperacao' => '123456789012']]);

    app(ServicoCiot::class)->garantir($viagem);

    $operacao = $soap->enviados['adicionar'][0];
    expect($operacao['Contratado']['CpfOuCnpj'])->toBe('48384601000171')
        ->and($operacao['Veiculos'][0]['Placa'])->toBe('BWP6E54');
    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'veiculos/gravar') || str_contains($r->url(), 'proprietarios'));
});

it('Emitir faz CT-e, CIOT e MDF-e de uma vez', function () {
    $viagem = viagemEfrete(autorizarCte: false);
    restEfreteFake();
    soapEfreteFake(['adicionar' => ['Sucesso' => true, 'CodigoIdentificacaoOperacao' => '123456789012']]);
    comGatewayCte(['enviar' => cteAutorizado()]);
    comGatewayMdfe(['enviar' => mdfeAutorizado()]);

    $resultado = app(EmissaoViagem::class)->emitir($viagem);

    expect($resultado['erros'])->toBe([])
        ->and($resultado['ok'])->toContain('CIOT 123456789012 gerado via e-Frete.')
        ->and($viagem->fresh()->mdfe->ciot)->toBe('123456789012');
});

it('depois do CIOT gerado o contrato não muda mais', function () {
    $viagem = viagemEfrete();
    restEfreteFake();
    soapEfreteFake(['adicionar' => ['Sucesso' => true, 'CodigoIdentificacaoOperacao' => '123456789012']]);
    app(ServicoCiot::class)->garantir($viagem);

    expect(fn () => app(ContratosFrete::class)->salvar($viagem->fresh(), [
        'frete_centavos' => 150000, 'adiantamento_centavos' => 0, 'vencimento_saldo' => today()->toDateString(), 'chave_pix' => 'x@y.com',
    ]))->toThrow(TransporteException::class, 'O CIOT desta viagem já foi gerado');
});

it('encerra o CIOT no e-Frete', function () {
    $viagem = viagemEfrete();
    restEfreteFake();
    soapEfreteFake(['adicionar' => ['Sucesso' => true, 'CodigoIdentificacaoOperacao' => '123456789012']]);
    $servico = app(ServicoCiot::class);
    $ciot = $servico->garantir($viagem);

    $ciot = $servico->encerrar($ciot);

    expect($ciot->situacao)->toBe('encerrado');
    Http::assertSent(fn ($r) => str_ends_with($r->url(), 'EncerrarOperacaoTransporte') && $r['CodigoIdentificacaoOperacao'] === '123456789012');
});

it('pela tela: gera o CIOT e baixa o PDF', function () {
    $this->seed(PerfilSeeder::class);
    $viagem = viagemEfrete();
    $user = User::factory()->create(['tenant_id' => $viagem->emitente->tenant_id]);
    $user->emitentes()->attach($viagem->emitente);
    setPermissionsTeamId($viagem->emitente_id);
    $user->assignRole(Perfil::Administrador->value);
    $this->actingAs($user);
    restEfreteFake();
    soapEfreteFake(['adicionar' => ['Sucesso' => true, 'CodigoIdentificacaoOperacao' => '123456789012']]);

    Livewire::test(ViagemDetalhe::class, ['viagem' => $viagem->id])
        ->assertSee('Gerar CIOT')
        ->call('gerarCiot')
        ->assertHasNoErrors()
        ->assertSee('123456789012')
        ->assertSee('PDF do CIOT');

    $this->get(route('transporte.ciot.pdf', Ciot::sole()))->assertOk();
});
