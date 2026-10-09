<?php

/**
 * CIOT para todos (Res. ANTT 6.078/2026, DF-026): toda viagem tem CIOT antes
 * do MDF-e, com caminhão próprio ou de terceiro, pela empresa escolhida em
 * Configurações, CIOT. Aqui a empresa é falsa (`provedorCiotFake`), para
 * provar o fluxo sem depender de nenhuma API de verdade.
 */

use App\Enums\Fiscal\Ambiente;
use App\Enums\Transporte\CteStatus;
use App\Enums\Transporte\MdfeStatus;
use App\Models\Ciot;
use App\Models\Emitente;
use App\Models\Viagem;
use App\Services\Transporte\Ciot\RespostaCiot;
use App\Services\Transporte\Ciot\ServicoCiot;
use App\Services\Transporte\EmissaoViagem;
use App\Services\Transporte\EventosCte;
use App\Services\Transporte\EventosMdfe;
use App\Services\Transporte\TransmissorCte;
use App\Services\Transporte\TransmissorMdfe;
use App\Services\Transporte\TransporteException;
use Illuminate\Support\Facades\Storage;

/** Caminhão próprio com tudo que o CIOT pede: distância, eixos e a chave Pix da empresa. */
function viagemParaCiot(?Emitente $emitente = null): Viagem
{
    $viagem = viagemPronta($emitente);
    $viagem->veiculo->update(['eixos' => 3, 'tipo_rodado' => '02']);
    $viagem->update(['distancia_km' => 180]);
    $viagem->emitente->update(['chave_pix' => 'financeiro@transportadora.com.br']);

    return $viagem->fresh();
}

function comCteAutorizado(Viagem $viagem): Viagem
{
    comGatewayCte(['enviar' => cteAutorizado(), 'cancelar' => eventoRegistrado()]);
    app(TransmissorCte::class)->transmitir($viagem->fresh()->ctes->sole());

    return $viagem->fresh();
}

function xmlDoMdfe(Viagem $viagem): string
{
    return (string) Storage::disk('fiscal')->get($viagem->fresh()->mdfe->xml_path);
}

it('Emitir com caminhão próprio faz CT-e, CIOT e MDF-e, e o CIOT vai no XML', function () {
    $viagem = viagemParaCiot();
    $empresa = provedorCiotFake([], $viagem->emitente);
    comGatewayCte(['enviar' => cteAutorizado()]);
    comGatewayMdfe(['enviar' => mdfeAutorizado()]);

    $resultado = app(EmissaoViagem::class)->emitir($viagem);

    expect($resultado['erros'])->toBe([])
        ->and($resultado['ok'])->toContain('CIOT 123456789012/1234 gerado via Empresa Teste.')
        ->and($empresa->metodos())->toBe(['declarar'])
        ->and($empresa->chamadas[0]['operacao']->frotaPropria)->toBeTrue()
        ->and($viagem->fresh()->mdfe->status)->toBe(MdfeStatus::Autorizado)
        ->and(xmlDoMdfe($viagem))->toContain("<infCIOT><CIOT>123456789012</CIOT><CNPJ>{$viagem->emitente->cnpj}</CNPJ></infCIOT>");
});

it('com a empresa Manual e sem CIOT digitado, autoriza o CT-e e para antes do MDF-e', function () {
    $viagem = viagemParaCiot();
    comGatewayCte(['enviar' => cteAutorizado()]);
    $mdfe = comGatewayMdfe(['enviar' => mdfeAutorizado()]);

    $resultado = app(EmissaoViagem::class)->emitir($viagem);

    expect(implode(' ', $resultado['erros']))->toContain('Informe o CIOT da viagem')
        ->and($viagem->fresh()->ctes->sole()->status)->toBe(CteStatus::Autorizado)
        ->and($mdfe->chamadas)->toBe([]);
});

it('pedido sem número fica processando e o Emitir seguinte consulta em vez de declarar de novo', function () {
    $viagem = viagemParaCiot();
    $empresa = provedorCiotFake([
        'declarar' => new RespostaCiot('processando', protocolo: 'P-1'),
        'consultar' => new RespostaCiot('registrado', numero: '210987654321'),
    ], $viagem->emitente);
    comGatewayCte(['enviar' => cteAutorizado()]);
    comGatewayMdfe(['enviar' => mdfeAutorizado()]);

    $primeiro = app(EmissaoViagem::class)->emitir($viagem);
    $segundo = app(EmissaoViagem::class)->emitir($viagem->fresh());

    expect(implode(' ', $primeiro['erros']))->toContain('ainda não devolveu o número')
        ->and($segundo['erros'])->toBe([])
        ->and($empresa->metodos())->toBe(['declarar', 'consultar'])
        ->and(Ciot::count())->toBe(1)
        ->and($viagem->fresh()->mdfe->ciot)->toBe('210987654321');
});

it('dois pedidos seguidos com CIOT registrado declaram uma vez só', function () {
    $viagem = comCteAutorizado(viagemParaCiot());
    $empresa = provedorCiotFake([], $viagem->emitente);
    $servico = app(ServicoCiot::class);

    $primeiro = $servico->garantir($viagem);
    $segundo = $servico->garantir($viagem->fresh());

    expect($segundo->is($primeiro))->toBeTrue()
        ->and($empresa->metodos())->toBe(['declarar']);
});

it('frete abaixo do piso mínimo vira mensagem com o valor do frete', function () {
    $viagem = comCteAutorizado(viagemParaCiot());
    provedorCiotFake(['declarar' => new RespostaCiot('recusado', codigo: '291', mensagem: 'O valor do frete informado é menor do que o valor mínimo')], $viagem->emitente);

    expect(fn () => app(ServicoCiot::class)->garantir($viagem))
        ->toThrow(TransporteException::class, 'o frete de R$ 75,00 ficou abaixo do piso mínimo');
    expect(Ciot::sole()->situacao)->toBe('recusado')
        ->and($viagem->fresh()->ciotVigente)->toBeNull();
});

it('distância incompatível com a rota aponta para a distância', function () {
    $viagem = comCteAutorizado(viagemParaCiot());
    provedorCiotFake(['declarar' => new RespostaCiot('recusado', codigo: '292', mensagem: 'Distância não compatível')], $viagem->emitente);

    expect(fn () => app(ServicoCiot::class)->garantir($viagem))->toThrow(TransporteException::class, 'a distância de 180 km não bate');
});

it('trocar de empresa com CIOT processando consulta na empresa antiga, com as credenciais dela', function () {
    $viagem = comCteAutorizado(viagemParaCiot());
    $antiga = provedorCiotFake([
        'declarar' => new RespostaCiot('processando'),
        'consultar' => new RespostaCiot('registrado', numero: '111122223333'),
    ], $viagem->emitente);
    $config = $viagem->emitente->configuracaoCiot();
    $config->guardarCredenciais('fake', Ambiente::Homologacao, ['token' => 'da-antiga']);
    app(ServicoCiot::class)->garantir($viagem);

    $nova = provedorCiotFake([], $viagem->emitente, 'outra');
    $ciot = app(ServicoCiot::class)->garantir($viagem->fresh());

    expect($ciot->numero)->toBe('111122223333')
        ->and($ciot->provedor)->toBe('fake')
        ->and($antiga->metodos())->toBe(['declarar', 'consultar'])
        ->and($antiga->chamadas[1]['credenciais'])->toBe(['token' => 'da-antiga'])
        ->and($nova->chamadas)->toBe([]);
});

it('CIOT digitado aceita pontuação e o verificador, e recusa número curto', function () {
    $viagem = viagemParaCiot();

    expect(fn () => app(ServicoCiot::class)->informar($viagem, '12345678901'))->toThrow(TransporteException::class, '12 dígitos');

    $ciot = app(ServicoCiot::class)->informar($viagem->fresh(), '1234.5678.9012/1234');

    expect($ciot->numero)->toBe('123456789012')
        ->and($ciot->verificador)->toBe('1234')
        ->and($ciot->digitado())->toBeTrue()
        ->and($ciot->responsavel_documento)->toBe($viagem->emitente->cnpj);
});

it('não aceita outro CIOT digitado quando a viagem já tem um registrado', function () {
    $viagem = viagemParaCiot();
    comCiotInformado($viagem);

    expect(fn () => app(ServicoCiot::class)->informar($viagem->fresh(), '999988887777'))
        ->toThrow(TransporteException::class, 'já tem o CIOT 123456789012');
});

it('cancelar o único CT-e cancela o CIOT na empresa', function () {
    $viagem = comCteAutorizado(viagemParaCiot());
    $empresa = provedorCiotFake([], $viagem->emitente);
    app(ServicoCiot::class)->garantir($viagem);

    app(EventosCte::class)->cancelar($viagem->fresh()->ctes->sole(), 'Frete cancelado pelo cliente antes da saida');

    expect($empresa->metodos())->toBe(['declarar', 'cancelar'])
        ->and(Ciot::sole()->situacao)->toBe('cancelado');
});

it('falha da empresa ao cancelar o CIOT não impede o cancelamento do CT-e', function () {
    $viagem = comCteAutorizado(viagemParaCiot());
    provedorCiotFake(['cancelar' => new TransporteException('Empresa fora do ar')], $viagem->emitente);
    app(ServicoCiot::class)->garantir($viagem);

    $cte = app(EventosCte::class)->cancelar($viagem->fresh()->ctes->sole(), 'Frete cancelado pelo cliente antes da saida');

    expect($cte->status)->toBe(CteStatus::Cancelado)
        ->and(Ciot::sole()->situacao)->toBe('registrado')
        ->and($viagem->fresh()->eventos->pluck('tipo'))->toContain('ciot_cancelamento_falhou');
});

it('encerrar o MDF-e encerra o CIOT na empresa', function () {
    $viagem = viagemParaCiot();
    $empresa = provedorCiotFake([], $viagem->emitente);
    comGatewayCte(['enviar' => cteAutorizado()]);
    comGatewayMdfe(['enviar' => mdfeAutorizado(), 'encerrar' => eventoRegistrado()]);
    app(EmissaoViagem::class)->emitir($viagem);

    app(EventosMdfe::class)->encerrar($viagem->fresh()->mdfe);

    expect($empresa->metodos())->toBe(['declarar', 'encerrar'])
        ->and(Ciot::sole()->situacao)->toBe('encerrado');
});

it('falha da empresa ao encerrar o CIOT não desfaz o encerramento do MDF-e', function () {
    $viagem = viagemParaCiot();
    provedorCiotFake(['encerrar' => new TransporteException('Empresa fora do ar')], $viagem->emitente);
    comGatewayCte(['enviar' => cteAutorizado()]);
    comGatewayMdfe(['enviar' => mdfeAutorizado(), 'encerrar' => eventoRegistrado()]);
    app(EmissaoViagem::class)->emitir($viagem);

    $mdfe = app(EventosMdfe::class)->encerrar($viagem->fresh()->mdfe);

    expect($mdfe->status)->toBe(MdfeStatus::Encerrado)
        ->and($viagem->fresh()->ciotVigente->situacao)->toBe('registrado')
        ->and($viagem->fresh()->eventos->pluck('tipo'))->toContain('ciot_encerramento_falhou');
});

it('em produção, empresa ainda não testada fica bloqueada e o CIOT é digitado', function () {
    $viagem = comCteAutorizado(viagemParaCiot());
    $empresa = provedorCiotFake([], $viagem->emitente);
    config(['ciot.provedores.fake.producao' => false]);
    $viagem->emitente->forceFill(['ambiente' => Ambiente::Producao])->save();

    $pendencias = implode(' ', app(TransmissorMdfe::class)->pendencias($viagem->fresh()));

    expect($pendencias)->toContain('produção fica bloqueada')
        ->and(fn () => app(ServicoCiot::class)->garantir($viagem->fresh()))->toThrow(TransporteException::class, 'produção fica bloqueada')
        ->and($empresa->chamadas)->toBe([]);
});

it('o que impede o CIOT aparece nas pendências do MDF-e', function () {
    $viagem = comCteAutorizado(viagemParaCiot());
    provedorCiotFake([], $viagem->emitente);
    $viagem->update(['distancia_km' => null]);

    expect(implode(' ', app(TransmissorMdfe::class)->pendencias($viagem->fresh())))->toContain('Informe a distância da viagem');
});

it('o aviso da ANTT ao transportador sai nas informações complementares do MDF-e', function () {
    $viagem = viagemParaCiot();
    provedorCiotFake(['declarar' => new RespostaCiot('registrado', numero: '123456789012', aviso: 'Transportador com vistoria vencida.')], $viagem->emitente);
    comGatewayCte(['enviar' => cteAutorizado()]);
    comGatewayMdfe(['enviar' => mdfeAutorizado()]);

    app(EmissaoViagem::class)->emitir($viagem);

    expect(xmlDoMdfe($viagem))->toContain('<infAdic><infCpl>Aviso da ANTT ao transportador: Transportador com vistoria vencida.</infCpl></infAdic>');
});

it('veículo de outra transportadora: o CIOT é dela, informado com o CNPJ dela', function () {
    $viagem = comCteAutorizado(viagemParaCiot());
    $viagem->veiculo->update(['proprietario_tipo' => 'terceiro', 'proprietario_documento' => '11444777000161',
        'proprietario_nome' => 'OUTRA TRANSPORTADORA', 'proprietario_rntrc' => '87654321', 'proprietario_tp' => '2']);

    expect(implode(' ', app(ServicoCiot::class)->pendencias($viagem->fresh())))->toContain('o CIOT é dela');

    $ciot = app(ServicoCiot::class)->informar($viagem->fresh(), '555566667777');
    expect($ciot->responsavel_documento)->toBe('11444777000161');
});
