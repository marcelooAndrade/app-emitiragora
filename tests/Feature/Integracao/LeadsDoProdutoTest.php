<?php

use App\Jobs\EnviarLeads;
use App\Models\User;
use App\Services\Integrations\AdminPessoalGateway;
use App\Services\Integrations\MontadorDeLeads;
use App\Support\TenantAtual;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->travelTo('2026-09-11 10:00:00');

    config([
        'integracao.admin_pessoal.url' => 'https://exemplo.test',
        'integracao.admin_pessoal.token' => 'segredo-de-teste',
    ]);
});

/**
 * O tenant vem do contêiner, e não do emitente: `Emitente` não tem relação
 * `tenant()`, e a `TestCase` já cria e resolve um tenant que a fábrica de
 * emitente reaproveita.
 */
it('monta o lead a partir do tenant', function () {
    $emitente = emitenteCompleto();
    $tenant = app(TenantAtual::class)->obter();
    $tenant->update(['nome' => 'DISTRIBUIDORA RIO CLARO LTDA']);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Marcelo Andrade',
        'email' => 'marcelo@exemplo.com.br',
        'ultimo_acesso_em' => '2026-09-10 18:30:00',
    ]);

    $lead = app(MontadorDeLeads::class)->paraTenant($tenant->fresh());

    expect($lead['tenant_id'])->toBe($tenant->id)
        ->and($lead['cnpj'])->toBe('11222333000181')
        ->and($lead['empresa'])->toBe('DISTRIBUIDORA RIO CLARO LTDA')
        ->and($lead['nome'])->toBe('Marcelo Andrade')
        ->and($lead['email'])->toBe('marcelo@exemplo.com.br')
        ->and($lead['telefone'])->toBe('1930960072')
        // O "Tenant de teste" da TestCase tem domínio (localhost), e a regra
        // do model exige plano avançado para quem tem domínio próprio: não dá
        // para ser "gratuito" aqui sem violar essa regra.
        ->and($lead['plano'])->toBe('avancado')
        ->and($lead['ultimo_acesso_em'])->toStartWith('2026-09-10T18:30:00');
});

it('leva as respostas do modal do site no lead', function () {
    emitenteCompleto();
    $tenant = app(TenantAtual::class)->obter();
    $tenant->update(['perfil_cadastro' => ['tipo' => 'transportadora', 'frota' => '6-20']]);
    User::factory()->create(['tenant_id' => $tenant->id]);

    $lead = app(MontadorDeLeads::class)->paraTenant($tenant->fresh());

    expect($lead['perfil_cadastro'])->toBe(['tipo' => 'transportadora', 'frota' => '6-20']);
});

/** O último acesso do tenant é o mais recente entre as pessoas dele. */
it('usa o acesso mais recente entre os usuarios do tenant', function () {
    emitenteCompleto();
    $tenant = app(TenantAtual::class)->obter();

    User::factory()->create(['tenant_id' => $tenant->id, 'ultimo_acesso_em' => '2026-09-01 08:00:00']);
    User::factory()->create(['tenant_id' => $tenant->id, 'ultimo_acesso_em' => '2026-09-09 21:15:00']);

    $lead = app(MontadorDeLeads::class)->paraTenant($tenant->fresh());

    expect($lead['ultimo_acesso_em'])->toStartWith('2026-09-09T21:15:00');
});

it('manda o lead com o token no cabecalho', function () {
    Http::fake(['exemplo.test/*' => Http::response(['processados' => 1], 200)]);

    app(AdminPessoalGateway::class)->enviar([['tenant_id' => 7]]);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://exemplo.test/api/integrations/emitir-agora/leads'
            && $request->hasHeader('Authorization', 'Bearer segredo-de-teste')
            && $request['leads'] === [['tenant_id' => 7]];
    });
});

/**
 * A fila que chama o gateway conta com essa exceção para tentar de novo. Sem
 * ela, uma recusa do outro lado faria o lead sumir em silêncio.
 */
it('lança quando o admin pessoal recusa os leads', function () {
    Http::fake(['exemplo.test/*' => Http::response(['erro' => 'motivo qualquer'], 500)]);

    app(AdminPessoalGateway::class)->enviar([['tenant_id' => 7]]);
})->throws(RuntimeException::class);

/**
 * A resposta pode ter sucesso HTTP e ainda recusar leads por dado ruim. Sem
 * logar isso, a contagem de recusados morre na resposta e ninguém do lado
 * Laravel fica sabendo que leads estão sendo perdidos.
 */
it('registra aviso quando o admin pessoal recusa parte dos leads', function () {
    Log::spy();
    Http::fake(['exemplo.test/*' => Http::response(['processados' => 1, 'recusados' => 2], 200)]);

    app(AdminPessoalGateway::class)->enviar([['tenant_id' => 7]]);

    Log::shouldHaveReceived('warning')->once();
});

/**
 * Esgotadas as tentativas, o job some para `failed_jobs` sem mais nenhum
 * aviso. Um token errado (401 em toda tentativa) fica indistinguível de "não
 * tem cadastro novo" sem este log de último aviso.
 */
it('registra erro quando o job esgota as tentativas', function () {
    Log::spy();

    $job = new EnviarLeads([['tenant_id' => 7], ['tenant_id' => 8]]);
    $job->failed(new RuntimeException('token inválido'));

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(fn (string $mensagem, array $contexto) => $contexto['quantidade'] === 2
            && $contexto['erro'] === 'token inválido');
});

it('nao chama a rede sem configuracao', function () {
    config(['integracao.admin_pessoal.url' => null, 'integracao.admin_pessoal.token' => null]);

    // Sem Http::fake: se sair qualquer chamada, o preventStrayRequests global
    // da suíte derruba o teste. É essa a asserção.
    app(AdminPessoalGateway::class)->enviar([['tenant_id' => 7]]);
})->throwsNoExceptions();
