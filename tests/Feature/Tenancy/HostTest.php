<?php

use App\Enums\PlanoTenant;
use App\Models\Tenant;
use App\Support\TenantAtual;

/**
 * O host responde duas perguntas: qual tenant, e qual superfície.
 *
 * Antes ele só respondia a primeira, e o primeiro rótulo virava slug sem
 * ressalva. Com isso um tenant de slug `app` capturava o host do próprio
 * produto, que foi medido em 11/09/2026 e é o motivo destes testes.
 */
function resolver(string $host): ?Tenant
{
    $atual = app(TenantAtual::class);
    $atual->limpar();
    $atual->definirPorHost($host);

    return $atual->obter();
}

it('recusa slug que capturaria host do produto', function (string $slug) {
    expect(fn () => Tenant::create(['nome' => 'Invasor', 'slug' => $slug]))
        ->toThrow(InvalidArgumentException::class);
})->with(['app', 'www', 'admin', 'api', 'mail']);

it('trata app como prefixo do produto, nao como slug', function () {
    // Sem o prefixo reservado, este host resolveria para um tenant de slug `app`.
    expect(resolver('app.vendaredonda.com.br'))->toBeNull();
});

it('o dominio nu do produto nao tem tenant, porque e a apresentacao', function () {
    expect(resolver('vendaredonda.com.br'))->toBeNull();
});

it('casa o dominio proprio do cliente antes de mexer no prefixo', function () {
    $rcm = Tenant::create(['nome' => 'RCM', 'slug' => 'rcm', 'dominio' => 'app.rcmdobrasil.com.br', 'plano' => PlanoTenant::Transporte]);

    expect(resolver('app.rcmdobrasil.com.br')?->getKey())->toBe($rcm->getKey());
})->skip('Domínio próprio de cliente desativado em 14/09/2026, ver TenantAtual::resolverHost');

it('aceita o dominio do cliente cadastrado sem o prefixo', function () {
    $rcm = Tenant::create(['nome' => 'RCM', 'slug' => 'rcm', 'dominio' => 'rcmdobrasil.com.br', 'plano' => PlanoTenant::Transporte]);

    expect(resolver('app.rcmdobrasil.com.br')?->getKey())->toBe($rcm->getKey());
})->skip('Domínio próprio de cliente desativado em 14/09/2026, ver TenantAtual::resolverHost');

it('continua resolvendo tenant por subdominio', function () {
    $rcm = Tenant::create(['nome' => 'RCM', 'slug' => 'rcm']);

    expect(resolver('rcm.vendaredonda.com.br')?->getKey())->toBe($rcm->getKey())
        ->and(resolver('app.rcm.vendaredonda.com.br')?->getKey())->toBe($rcm->getKey());
})->skip('Subdomínio por slug desativado em 14/09/2026, ver TenantAtual::resolverHost');

it('a raiz mostra a apresentacao no dominio do produto', function () {
    $this->get('http://vendaredonda.com.br/')->assertOk()->assertSee('EmitirAgora');
});

it('a apresentacao nao depende de APP_NAME para dizer o nome do produto', function () {
    // Página pública: erro de configuração não pode mostrar a marca errada.
    config(['app.name' => 'Nome Errado']);

    $this->get('http://vendaredonda.com.br/')
        ->assertOk()
        ->assertSee('<title>', false)
        ->assertDontSee('Nome Errado')
        ->assertDontSee('EmitirAgora - EmitirAgora');
});

it('a apresentacao manda para o login do mesmo dominio, nao para um subdominio app', function () {
    // O sistema mora no mesmo host, por caminho: /login e /dashboard. O `app.`
    // existe só para cliente com domínio próprio, e nunca para o produto.
    $html = $this->get('http://vendaredonda.com.br/')->assertOk()->getContent();

    expect($html)->toContain('href="http://vendaredonda.com.br/login"')
        ->and($html)->not->toContain('app.vendaredonda.com.br');
});

it('a apresentacao publica descricao e dados estruturados', function () {
    $html = $this->get('http://vendaredonda.com.br/')->assertOk()->getContent();

    expect($html)->toContain('name="description"')
        ->and($html)->toContain('property="og:title"')
        ->and($html)->toContain('application/ld+json');

    preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $html, $m);

    expect(json_decode($m[1], true))
        ->toHaveKey('@type', 'SoftwareApplication')
        ->toHaveKey('name', 'Venda Redonda');
});

it('a raiz manda para o login em host de aplicacao', function () {
    $this->get('http://app.vendaredonda.com.br/')->assertRedirect('/login');
});
