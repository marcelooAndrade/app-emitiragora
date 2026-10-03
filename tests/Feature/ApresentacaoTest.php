<?php

use App\Models\Tenant;

/**
 * A apresentação pública, redesenhada em 12/09/2026: estrutura de seções
 * inspirada em referência externa de mercado (ver spec do redesenho), cores
 * e conteúdo próprios da Venda Redonda. `HostTest.php` já cobre host, título
 * e dados estruturados; este arquivo cobre o conteúdo do redesenho.
 */
beforeEach(function () {
    Tenant::query()->delete();
    config(['produto.dominio' => 'vendaredonda.com.br']);
});

it('tem um link para criar conta gratis e outro para entrar', function () {
    $html = $this->get('http://vendaredonda.com.br/')->assertOk()->getContent();

    expect($html)->toContain('href="http://vendaredonda.com.br/register"')
        ->and($html)->toContain('Criar conta grátis')
        ->and($html)->toContain('href="http://vendaredonda.com.br/login"')
        ->and($html)->toContain('Entrar no sistema');
});

it('a assinatura do rodape inclui financeiro', function () {
    $this->get('http://vendaredonda.com.br/')
        ->assertOk()
        ->assertSee('Fiscal · Estoque · Financeiro');
});

it('a descricao cita financeiro e nfs-e', function () {
    $html = $this->get('http://vendaredonda.com.br/')->assertOk()->getContent();

    expect($html)->toContain('NFS-e')
        ->and($html)->toContain('contas a pagar e a receber');
});

it('as secoes existentes ganham o atributo de revelar ao rolar', function () {
    $html = $this->get('http://vendaredonda.com.br/')->assertOk()->getContent();

    expect(substr_count($html, 'data-revelar'))->toBeGreaterThanOrEqual(4);
});

it('mostra a secao de dores antes dos recursos', function () {
    $html = $this->get('http://vendaredonda.com.br/')->assertOk()->getContent();

    expect($html)->toContain('Isso não chega organizado sozinho')
        ->and(strpos($html, 'Isso não chega organizado sozinho'))
        ->toBeLessThan(strpos($html, 'Sete coisas, e elas dependem uma da outra'));
});

it('recursos tem sete cartoes, incluindo nfse e contas a pagar e a receber', function () {
    $this->get('http://vendaredonda.com.br/')
        ->assertOk()
        ->assertSee('Sete coisas, e elas dependem uma da outra')
        ->assertSee('NFS-e de Araras, pelo SIGISS')
        ->assertSee('Contas a pagar e a receber, com baixa')
        ->assertSee('A compra entra pelo XML')
        ->assertSee('Um painel que abre com o que trava');
});

it('o link ver o que ele faz aponta para a secao de recursos', function () {
    $html = $this->get('http://vendaredonda.com.br/')->assertOk()->getContent();

    expect($html)->toContain('href="#recursos"')
        ->and($html)->toContain('id="recursos"')
        ->and($html)->not->toContain('id="o-que-faz"');
});

it('mostra os dois planos do site, com preco de config/planos.php', function () {
    $html = $this->get('http://vendaredonda.com.br/')->assertOk()->getContent();

    expect($html)->toContain('>Transporte<')
        ->and($html)->toContain('>Pequena Empresa<')
        ->and($html)->toContain('R$499')
        ->and($html)->toContain('R$99')
        ->and($html)->toContain('Criar conta e testar grátis')
        ->and($html)->not->toContain('>Essencial<')
        ->and($html)->not->toContain('notas por mês');
});

it('a lista do que falta cita tesouraria em vez de fluxo de caixa projetado', function () {
    $this->get('http://vendaredonda.com.br/')
        ->assertOk()
        ->assertSee('Tesouraria: caixa livre, reserva, meses de sobrevivência')
        ->assertDontSee('Fluxo de caixa projetado');
});

it('o paragrafo final de o que falta reconhece que o financeiro ja existe', function () {
    $this->get('http://vendaredonda.com.br/')
        ->assertOk()
        ->assertSee('O núcleo do financeiro já funciona, por isso a assinatura já diz Financeiro.');
});

it('mostra seis perguntas frequentes em details, sem javascript de acordeao', function () {
    $html = $this->get('http://vendaredonda.com.br/')->assertOk()->getContent();

    expect(substr_count($html, '<details'))->toBe(6)
        ->and($html)->toContain('Meu contador consegue mexer na regra fiscal sozinho?')
        ->and($html)->toContain('Onde ficam os meus dados?');
});

it('mostra a secao de transformacao entre dores e recursos', function () {
    $html = $this->get('http://vendaredonda.com.br/')->assertOk()->getContent();

    expect($html)->toContain('Da divergência ao fechamento redondo');

    $posicaoDores = strpos($html, 'Isso não chega organizado sozinho');
    $posicaoTransformacao = strpos($html, 'Da divergência ao fechamento redondo');
    $posicaoRecursos = strpos($html, 'Sete coisas, e elas dependem uma da outra');

    expect($posicaoDores)->toBeLessThan($posicaoTransformacao)
        ->and($posicaoTransformacao)->toBeLessThan($posicaoRecursos);
});

it('mostra a secao de papeis com faturamento, estoque e contador', function () {
    $this->get('http://vendaredonda.com.br/')
        ->assertOk()
        ->assertSee('Cada papel vê só o que precisa')
        ->assertSee('Faturamento')
        ->assertSee('Estoque')
        ->assertSee('Contador');
});

it('mostra a secao de fechamento do mes, com o exemplo rotulado como exemplo', function () {
    $this->get('http://vendaredonda.com.br/')
        ->assertOk()
        ->assertSee('O fechamento sai pronto, não é montado')
        ->assertSee('Fechamento do mês, exemplo');
});

/**
 * Contagem por piso, e não exata: o número de chamadas muda a cada
 * rearranjo de seção, e o teste exato já precisou ser corrigido três vezes
 * sem nunca ter pego um defeito. O que importa é que as duas ações
 * continuem alcançáveis, e que o CTA final tenha as duas.
 */
it('o cta final tem os dois links, e a pagina toda mantem as duas acoes alcancaveis', function () {
    $html = $this->get('http://vendaredonda.com.br/')->assertOk()->getContent();

    $inicioFinal = strpos($html, 'ÚLTIMO PASSO');
    $ctaFinal = substr($html, $inicioFinal, strpos($html, '</main>', $inicioFinal) - $inicioFinal);

    expect($ctaFinal)->toContain('href="http://vendaredonda.com.br/register"')
        ->and($ctaFinal)->toContain('href="http://vendaredonda.com.br/login"')
        ->and(substr_count($html, 'href="http://vendaredonda.com.br/register"'))->toBeGreaterThanOrEqual(5)
        ->and(substr_count($html, 'href="http://vendaredonda.com.br/login"'))->toBeGreaterThanOrEqual(3);
});
