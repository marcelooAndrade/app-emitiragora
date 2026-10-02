<?php

use App\Models\Cte;
use App\Services\Transporte\LeitorNfe;
use App\Services\Transporte\Rateio;
use App\Services\Transporte\TransporteException;
use App\Services\Transporte\Viagens;

function nfeFixture(array $troca = []): string
{
    return strtr((string) file_get_contents(base_path('tests/Fixtures/xml/nfe-autorizada.xml')), $troca);
}

it('lê participantes, valor, produto e modalidade do frete da NF-e', function () {
    $nfe = app(LeitorNfe::class)->ler(nfeFixture());

    expect($nfe['chave'])->toBe('35260911444777000161550010000088211234567897')
        ->and($nfe['numero'])->toBe('8821')
        ->and($nfe['remetente']['documento'])->toBe('11444777000161')
        ->and($nfe['destinatario']['municipio_codigo'])->toBe('3503307')
        ->and($nfe['valor_centavos'])->toBe(2037000)
        ->and($nfe['mod_frete'])->toBe('0')
        ->and($nfe['ncm_predominante'])->toBe('72222000');
});

it('recusa XML com DOCTYPE, que abre porta para XXE', function () {
    app(LeitorNfe::class)->ler((string) file_get_contents(base_path('tests/Fixtures/xml/xxe.xml')));
})->throws(TransporteException::class, 'estrutura insegura');

it('recusa NF-e sem protocolo de autorização', function () {
    app(LeitorNfe::class)->ler((string) file_get_contents(base_path('tests/Fixtures/xml/nfe-sem-protocolo.xml')));
})->throws(TransporteException::class, 'protocolo de autorização');

it('recusa CT-e no lugar de NF-e', function () {
    app(LeitorNfe::class)->ler((string) file_get_contents(base_path('tests/Fixtures/xml/cte.xml')));
})->throws(TransporteException::class, 'CT-e');

it('não deixa a mesma NF-e entrar duas vezes na viagem', function () {
    $viagem = viagemPronta();

    app(Viagens::class)->adicionarNota($viagem, nfeFixture());
})->throws(TransporteException::class, 'já está nesta viagem');

it('junta NF-e do mesmo remetente e destinatário num CT-e só e separa as outras', function () {
    $viagem = viagemPronta();
    $viagens = app(Viagens::class);
    // Mesma rota, outra nota: entra no mesmo CT-e.
    $mesma = $viagens->adicionarNota($viagem, nfeFixture(['0000088211234567897' => '0000088221234567893', '<nNF>8821</nNF>' => '<nNF>8822</nNF>', 'NFe35260911444777000161550010000088211234567897' => 'NFe35260911444777000161550010000088221234567893']));
    // Outro destinatário: CT-e próprio.
    $viagens->adicionarNota($viagem, nfeFixture(['0000088211234567897' => '0000088231234567890', 'NFe35260911444777000161550010000088211234567897' => 'NFe35260911444777000161550010000088231234567890', '<CNPJ>11222333000181</CNPJ>' => '<CNPJ>44555666000199</CNPJ>']));

    $ctes = $viagem->fresh()->ctes;
    expect($ctes)->toHaveCount(2)
        ->and($ctes->first()->notas->pluck('id'))->toContain($mesma->id)
        ->and($ctes->first()->notas)->toHaveCount(2);
});

it('frete fechado é rateado pelo peso e fecha no centavo', function () {
    expect(Rateio::dividir(10000, [1, 1, 1]))->toBe([3334, 3333, 3333])
        ->and(array_sum(Rateio::dividir(99999, [500, 1250.5, 3])))->toBe(99999)
        ->and(Rateio::dividir(100, [0, 0]))->toBe([50, 50]);
});

it('peso informado à mão recalcula o frete por tonelada', function () {
    $viagem = viagemPronta();
    $nota = $viagem->notas->sole();

    app(Viagens::class)->definirPeso($viagem, $nota, 2000);

    expect(Cte::where('viagem_id', $viagem->id)->sole()->valor_frete_centavos)->toBe(30000);
});
