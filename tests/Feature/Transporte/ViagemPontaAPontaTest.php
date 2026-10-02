<?php

use App\Enums\Transporte\CteStatus;
use App\Enums\Transporte\MdfeStatus;
use App\Enums\Transporte\ViagemStatus;
use App\Models\Fatura;
use App\Models\Pessoa;
use App\Services\Fiscal\RespostaSefaz;
use App\Services\Transporte\EmissaoViagem;
use App\Services\Transporte\EventosCte;
use App\Services\Transporte\EventosMdfe;
use App\Services\Transporte\FaturamentoViagem;
use App\Services\Transporte\MdfeXml;
use App\Services\Transporte\TransmissorCte;
use App\Services\Transporte\TransmissorMdfe;
use App\Services\Transporte\TransporteException;
use App\Services\Transporte\TransporteToolsFactory;
use NFePHP\Common\Validator;

it('emitir tudo autoriza o CT-e e o MDF-e e a viagem vai para em viagem', function () {
    $viagem = viagemPronta();
    comGatewayCte(['enviar' => cteAutorizado()]);
    comGatewayMdfe(['enviar' => mdfeAutorizado()]);

    $resultado = app(EmissaoViagem::class)->emitir($viagem);

    $viagem = $viagem->fresh(['ctes', 'mdfe']);
    expect($resultado['erros'])->toBe([])
        ->and($resultado['ok'])->toHaveCount(2)
        ->and($viagem->ctes->sole()->status)->toBe(CteStatus::Autorizado)
        ->and($viagem->mdfe->status)->toBe(MdfeStatus::Autorizado)
        ->and($viagem->mdfe->numero)->toBe(1)
        ->and(substr($viagem->mdfe->chave, 20, 2))->toBe('58')
        ->and($viagem->status)->toBe(ViagemStatus::Emitida);
});

it('com CT-e rejeitado não tenta o MDF-e e diz o motivo', function () {
    $viagem = viagemPronta();
    comGatewayCte(['enviar' => new RespostaSefaz('225', 'Rejeicao: Falha no Schema XML')]);
    $mdfe = comGatewayMdfe(['enviar' => mdfeAutorizado()]);

    $resultado = app(EmissaoViagem::class)->emitir($viagem);

    expect($resultado['erros'][0])->toContain('225')
        ->and($mdfe->chamadas)->toBe([])
        ->and($viagem->fresh()->status)->toBe(ViagemStatus::Pendente);
});

it('o MDF-e montado de um CT-e autorizado, assinado, passa no xsd oficial', function () {
    $viagem = viagemPronta();
    comGatewayCte(['enviar' => cteAutorizado()]);
    app(TransmissorCte::class)->transmitir($viagem->ctes->sole());

    $mdfe = app(TransmissorMdfe::class)->preparar($viagem->fresh());
    $mdfe->forceFill(['numero' => 1, 'seguro' => [...$mdfe->seguro, 'averbacoes' => ['AVERB0001']]])->save();
    $montado = app(MdfeXml::class)->montar($mdfe->fresh());
    $assinado = app(TransporteToolsFactory::class)->mdfe($viagem->emitente)->signMDFe($montado['xml']);

    expect(Validator::isValid($assinado, base_path('vendor/nfephp-org/sped-mdfe/schemes/PL_MDFe_300a/mdfe_v3.00.xsd')))->toBeTrue()
        ->and($assinado)->toContain('<placa>ABC1D23</placa>')
        ->and($assinado)->toContain('<CPF>52998224725</CPF>')
        ->and($assinado)->toContain('<nAver>AVERB0001</nAver>')
        ->and($assinado)->toContain('<infLotacao>');
});

it('com veículo de terceiro o MDF-e leva o proprietário e ainda passa no xsd', function () {
    $viagem = viagemPronta();
    $viagem->veiculo->update([
        'proprietario_tipo' => 'terceiro',
        'proprietario_documento' => '52998224725',
        'proprietario_nome' => 'JOAO DA SILVA',
        'proprietario_rntrc' => '87654321',
        'proprietario_tp' => '1',
    ]);
    comGatewayCte(['enviar' => cteAutorizado()]);
    app(TransmissorCte::class)->transmitir($viagem->ctes->sole());

    $mdfe = app(TransmissorMdfe::class)->preparar($viagem->fresh());
    $mdfe->forceFill(['numero' => 2])->save();
    $assinado = app(TransporteToolsFactory::class)->mdfe($viagem->emitente)->signMDFe(app(MdfeXml::class)->montar($mdfe->fresh())['xml']);

    expect(Validator::isValid($assinado, base_path('vendor/nfephp-org/sped-mdfe/schemes/PL_MDFe_300a/mdfe_v3.00.xsd')))->toBeTrue()
        ->and($assinado)->toContain('<tpTransp>2</tpTransp>')
        ->and($assinado)->toContain('<RNTRC>87654321</RNTRC>');
});

it('MDF-e diz o que falta em vez de ir para a SEFAZ', function () {
    $viagem = viagemPronta();
    $viagem->update(['motorista_id' => null]);
    $viagem->emitente->configuracaoTransporte()->update(['apolice' => null]);
    comGatewayCte(['enviar' => cteAutorizado()]);
    app(TransmissorCte::class)->transmitir($viagem->ctes->sole());

    $pendencias = app(TransmissorMdfe::class)->pendencias($viagem->fresh());

    expect($pendencias)->toContain('Escolha o motorista.')
        ->and(collect($pendencias)->join(' '))->toContain('apólice');
});

it('encerra o MDF-e no destino e a viagem fica encerrada', function () {
    $viagem = viagemPronta();
    comGatewayCte(['enviar' => cteAutorizado()]);
    comGatewayMdfe(['enviar' => mdfeAutorizado(), 'encerrar' => eventoRegistrado('958260000777001')]);
    app(EmissaoViagem::class)->emitir($viagem);

    $mdfe = app(EventosMdfe::class)->encerrar($viagem->fresh()->mdfe);

    expect($mdfe->status)->toBe(MdfeStatus::Encerrado)
        ->and($mdfe->protocolo_encerramento)->toBe('958260000777001')
        ->and($viagem->fresh()->status)->toBe(ViagemStatus::Encerrada);
});

it('não cancela CT-e que está num MDF-e em viagem', function () {
    $viagem = viagemPronta();
    comGatewayCte(['enviar' => cteAutorizado(), 'cancelar' => eventoRegistrado()]);
    comGatewayMdfe(['enviar' => mdfeAutorizado()]);
    app(EmissaoViagem::class)->emitir($viagem);

    app(EventosCte::class)->cancelar($viagem->fresh()->ctes->sole(), 'Frete cancelado pelo cliente antes da saida');
})->throws(TransporteException::class, 'Cancele ou encerre o MDF-e');

it('cancela o CT-e depois de cancelar o MDF-e', function () {
    $viagem = viagemPronta();
    comGatewayCte(['enviar' => cteAutorizado(), 'cancelar' => eventoRegistrado('135260000555001')]);
    comGatewayMdfe(['enviar' => mdfeAutorizado(), 'cancelar' => eventoRegistrado()]);
    app(EmissaoViagem::class)->emitir($viagem);

    app(EventosMdfe::class)->cancelar($viagem->fresh()->mdfe, 'Viagem cancelada antes da saida do veiculo');
    $cte = app(EventosCte::class)->cancelar($viagem->fresh()->ctes->sole(), 'Frete cancelado pelo cliente antes da saida');

    expect($cte->status)->toBe(CteStatus::Cancelado)
        ->and($cte->protocolo_cancelamento)->toBe('135260000555001')
        ->and($cte->eventos->sole()->tipo)->toBe('cancelamento')
        ->and($viagem->fresh()->status)->toBe(ViagemStatus::Cancelada);
});

it('carta de correção numera a sequência e recusa campo que a lei não deixa corrigir', function () {
    $viagem = viagemPronta();
    comGatewayCte(['enviar' => cteAutorizado(), 'cartaCorrecao' => eventoRegistrado()]);
    $cte = app(TransmissorCte::class)->transmitir($viagem->ctes->sole());
    $eventos = app(EventosCte::class);

    expect($eventos->cartaCorrecao($cte, 'xObs', 'Entrega no portao 2')->sequencia)->toBe(1)
        ->and($eventos->cartaCorrecao($cte, 'proPred', 'BARRAS DE ACO')->sequencia)->toBe(2)
        ->and(fn () => $eventos->cartaCorrecao($cte, 'vTPrest', '10.00'))->toThrow(TransporteException::class);
});

it('fatura a viagem para o tomador e cadastra o cliente sozinho', function () {
    $viagem = viagemPronta();
    comGatewayCte(['enviar' => cteAutorizado()]);
    app(TransmissorCte::class)->transmitir($viagem->ctes->sole());

    $faturas = app(FaturamentoViagem::class)->faturar($viagem->fresh());

    // modFrete 0 (CIF) na NF-e da fixture: quem paga o frete é o remetente.
    $fatura = $faturas->sole();
    $cliente = Pessoa::where('documento', '11444777000161')->sole();
    expect($fatura->pessoa_id)->toBe($cliente->id)
        ->and($cliente->e_cliente)->toBeTrue()
        ->and($fatura->parcelas->sole()->valor_centavos)->toBe(7500)
        ->and($viagem->fresh()->ctes->sole()->fatura_id)->toBe($fatura->id)
        ->and(fn () => app(FaturamentoViagem::class)->faturar($viagem->fresh()))->toThrow(TransporteException::class);
    expect(Fatura::count())->toBe(1);
});
