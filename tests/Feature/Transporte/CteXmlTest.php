<?php

use App\Models\RegraIcmsTransporte;
use App\Models\User;
use App\Services\Fiscal\CertificateService;
use App\Services\Transporte\CteXml;
use App\Services\Transporte\RegraIcms;
use App\Services\Transporte\TransporteToolsFactory;
use App\Services\Transporte\Viagens;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use NFePHP\Common\Validator;

it('monta o CT-e de uma NF-e real e, assinado, passa no xsd oficial', function () {
    Storage::fake('fiscal');
    $emitente = emitenteCompleto();
    $emitente->configuracaoTransporte()->update(['rntrc' => '12345678']);
    (new RegraIcmsTransporte(['nome' => 'SP interno', 'uf_origem' => 'SP', 'uf_destino' => 'SP', 'cst' => '00', 'aliquota' => 12]))
        ->forceFill(['emitente_id' => $emitente->id])->save();
    app(CertificateService::class)->enviar(
        $emitente,
        UploadedFile::fake()->createWithContent('valido.pfx', (string) file_get_contents(base_path('tests/Fixtures/certificados/valido.pfx'))),
        'teste123',
        User::factory()->create(),
    );

    $viagens = app(Viagens::class);
    $viagem = $viagens->criar($emitente, ['frete_tonelada_centavos' => 15000]);
    $nota = $viagens->adicionarNota($viagem, file_get_contents(base_path('tests/Fixtures/xml/nfe-autorizada.xml')));
    $viagens->definirPeso($viagem, $nota, 500);

    $cte = $viagem->fresh()->ctes->sole();
    $regra = app(RegraIcms::class)->resolver($emitente, 'SP', 'SP');
    $cte->forceFill([...app(RegraIcms::class)->calcular($regra, $cte->valor_total_centavos), 'regra_icms_id' => $regra->id, 'numero' => 1])->save();

    $montado = app(CteXml::class)->montar($cte->fresh());
    $assinado = app(TransporteToolsFactory::class)->cte($emitente->fresh())->signCTe($montado['xml']);

    expect($montado['chave'])->toHaveLength(44)
        ->and(Validator::isValid($assinado, base_path('vendor/nfephp-org/sped-cte/schemes/PL_CTe_400/cte_v4.00.xsd')))->toBeTrue()
        ->and($assinado)->toContain('<CNPJ>11444777000161</CNPJ>')
        ->and($assinado)->toContain('<chave>35260911444777000161550010000088211234567897</chave>')
        ->and($assinado)->toContain('<vTPrest>75.00</vTPrest>')
        ->and($assinado)->toContain('<tpAmb>2</tpAmb>');
});
