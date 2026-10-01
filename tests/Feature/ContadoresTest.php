<?php

use App\Models\Tenant;

beforeEach(function () {
    Tenant::query()->delete();
});

it('carrega a pagina de contadores', function () {
    $this->get('/contadores')->assertOk();
});

it('mostra os tres niveis de comissao com o percentual do config', function () {
    $this->get('/contadores')
        ->assertOk()
        ->assertSee('Parceiro Inicial')
        ->assertSee('Parceiro Plus')
        ->assertSee('Parceiro Premium')
        ->assertSee('20%')
        ->assertSee('25%')
        ->assertSee('30%');
});

it('mostra os planos reais dentro da pagina', function () {
    $this->get('/contadores')
        ->assertOk()
        ->assertSee('R$49')
        ->assertSee('R$99');
});

it('avisa que o formulario de parceiro ainda nao tem backend, enquanto a config estiver vazia', function () {
    config(['planos.pendencias.linkCadastroParceiro' => '']);

    $this->get('/contadores')
        ->assertOk()
        ->assertSee('este formulário ainda não tem para onde enviar');
});

it('nao avisa pendencia do formulario quando o link de cadastro de parceiro estiver configurado', function () {
    config(['planos.pendencias.linkCadastroParceiro' => 'https://exemplo.test/parceiros']);

    $this->get('/contadores')
        ->assertOk()
        ->assertDontSee('este formulário ainda não tem para onde enviar');
});
