<?php

use App\Jobs\EnviarConversaoMeta;
use App\Models\CadastroIniciado;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Integrations\MetaConversoesGateway;
use App\Services\Integrations\MontadorDeConversoesMeta;
use Database\Seeders\PerfilSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->travelTo('2026-09-15 10:00:00');

    config([
        'integracao.meta.pixel_id' => '123456789',
        'integracao.meta.access_token' => 'segredo-de-teste',
    ]);
});

it('monta o evento de cadastro com os dados hasheados', function () {
    $emitente = emitenteCompleto(['telefone' => '19999998888']);
    $user = User::factory()->create(['name' => 'Marcelo Andrade', 'email' => 'marcelo@exemplo.com.br']);

    $evento = app(MontadorDeConversoesMeta::class)->paraCadastro(
        $user, $emitente, 'cadastro-42', 'https://emitiragora.com.br/register',
    );

    expect($evento['event_name'])->toBe('CompleteRegistration')
        ->and($evento['event_id'])->toBe('cadastro-42')
        ->and($evento['event_source_url'])->toBe('https://emitiragora.com.br/register')
        ->and($evento['action_source'])->toBe('website')
        ->and($evento['event_time'])->toBe(now()->timestamp)
        ->and($evento['user_data']['em'])->toBe([hash('sha256', 'marcelo@exemplo.com.br')])
        // DDD + número, sem DDI: o montador prefixa 55.
        ->and($evento['user_data']['ph'])->toBe([hash('sha256', '5519999998888')])
        ->and($evento['user_data']['external_id'])->toBe([hash('sha256', 'cadastro-42')])
        ->and($evento['user_data']['fn'])->toBe([hash('sha256', 'marcelo')])
        ->and($evento['user_data']['ln'])->toBe([hash('sha256', 'andrade')])
        ->and($evento['user_data'])->not->toHaveKeys(['fbp', 'fbc', 'client_ip_address', 'client_user_agent']);
});

it('inclui os sinais do navegador quando presentes', function () {
    $emitente = emitenteCompleto();
    $user = User::factory()->create();

    $evento = app(MontadorDeConversoesMeta::class)->paraCadastro(
        $user, $emitente, 'cadastro-7', 'https://emitiragora.com.br/register',
        ['fbp' => 'fb.1.111.222', 'fbc' => 'fb.1.333.444', 'ip' => '203.0.113.9', 'user_agent' => 'TesteAgent/1.0'],
    );

    expect($evento['user_data']['fbp'])->toBe('fb.1.111.222')
        ->and($evento['user_data']['fbc'])->toBe('fb.1.333.444')
        ->and($evento['user_data']['client_ip_address'])->toBe('203.0.113.9')
        ->and($evento['user_data']['client_user_agent'])->toBe('TesteAgent/1.0');
});

/**
 * Telefone sem DDI e sem DDD (ou qualquer coisa fora de 10/11 dígitos) segue
 * cru: o montador só sabe completar o padrão brasileiro mais comum.
 */
it('nao mexe em telefone fora do padrao de 10 ou 11 digitos', function () {
    $emitente = emitenteCompleto(['telefone' => '5519999998888']);
    $user = User::factory()->create();

    $evento = app(MontadorDeConversoesMeta::class)->paraCadastro($user, $emitente, 'cadastro-9', 'https://emitiragora.com.br/register');

    expect($evento['user_data']['ph'])->toBe([hash('sha256', '5519999998888')]);
});

it('manda o evento com o token no corpo', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1], 200)]);

    app(MetaConversoesGateway::class)->enviar(['event_name' => 'CompleteRegistration', 'event_id' => 'cadastro-1']);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://graph.facebook.com/v21.0/123456789/events'
            && $request['access_token'] === 'segredo-de-teste'
            && $request['data'] === [['event_name' => 'CompleteRegistration', 'event_id' => 'cadastro-1']];
    });
});

/** A fila conta com essa exceção para tentar de novo, mesma regra do EnviarLeads. */
it('lanca quando o meta recusa o evento', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => 'motivo qualquer'], 400)]);

    app(MetaConversoesGateway::class)->enviar(['event_name' => 'CompleteRegistration']);
})->throws(RuntimeException::class);

it('nao chama a rede sem configuracao', function () {
    config(['integracao.meta.pixel_id' => null, 'integracao.meta.access_token' => null]);

    // Sem Http::fake: se sair qualquer chamada, o preventStrayRequests global
    // da suíte derruba o teste. É essa a asserção.
    app(MetaConversoesGateway::class)->enviar(['event_name' => 'CompleteRegistration']);
})->throwsNoExceptions();

it('registra erro quando o job esgota as tentativas', function () {
    Log::spy();

    $job = new EnviarConversaoMeta(['event_id' => 'cadastro-1']);
    $job->failed(new RuntimeException('token inválido'));

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(fn (string $mensagem, array $contexto) => $contexto['event_id'] === 'cadastro-1'
            && $contexto['erro'] === 'token inválido');
});

/**
 * De ponta a ponta: o cadastro dispara a Conversions API e guarda na sessão
 * o mesmo event_id, para o navegador disparar o equivalente na primeira
 * tela do painel (ver partials/pixel-meta e components/layouts/fiscal).
 */
it('cadastro dispara a conversao com o mesmo event_id que fica na sessao', function () {
    $this->seed(PerfilSeeder::class);
    Tenant::query()->delete();
    config(['produto.dominio' => 'vendaredonda.com.br']);
    Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1], 200)]);

    $resposta = $this->post('http://vendaredonda.com.br/register', comConvite([
        'name' => 'Marcelo Andrade',
        'email' => 'marcelo@exemplo.com.br',
        'password' => 'senha-muito-longa-123',
        'password_confirmation' => 'senha-muito-longa-123',
        'razao_social' => 'DISTRIBUIDORA RIO CLARO LTDA',
        'cnpj' => '11222333000181',
        'inscricao_estadual' => '123456789012',
        'crt' => '3',
        'telefone' => '1930960072',
    ]));
    $resposta->assertSessionHasNoErrors();

    $user = User::firstWhere('email', 'marcelo@exemplo.com.br');
    $eventId = "cadastro-{$user->id}";

    Http::assertSent(function ($request) use ($eventId) {
        $evento = $request['data'][0] ?? [];

        return $evento['event_name'] === 'CompleteRegistration' && $evento['event_id'] === $eventId;
    });

    $resposta->assertSessionHas('meta_pixel_evento', ['nome' => 'CompleteRegistration', 'id' => $eventId]);
});

/**
 * A metade do navegador: a primeira tela depois do cadastro (o painel) é a
 * confirmação, e é nela que o pixel dispara o CompleteRegistration com o
 * mesmo event_id da Conversions API. Só uma vez: a visita seguinte ao
 * painel não repete a conversão.
 */
it('primeira tela depois do cadastro dispara o pixel uma vez so', function () {
    $this->seed(PerfilSeeder::class);
    Tenant::query()->delete();
    config(['produto.dominio' => 'vendaredonda.com.br']);
    Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1], 200)]);

    $this->followingRedirects()->post('http://vendaredonda.com.br/register', comConvite([
        'name' => 'Marcelo Andrade',
        'email' => 'marcelo@exemplo.com.br',
        'password' => 'senha-muito-longa-123',
        'password_confirmation' => 'senha-muito-longa-123',
        'razao_social' => 'DISTRIBUIDORA RIO CLARO LTDA',
        'cnpj' => '11222333000181',
        'inscricao_estadual' => '123456789012',
        'crt' => '3',
        'telefone' => '1930960072',
    ]))
        ->assertOk()
        ->assertSee('connect.facebook.net', false)
        ->assertSee('CompleteRegistration', false)
        ->assertSee('cadastro-'.User::firstWhere('email', 'marcelo@exemplo.com.br')->id, false);

    $this->get('http://vendaredonda.com.br/dashboard')
        ->assertOk()
        ->assertDontSee('CompleteRegistration', false);
});

it('monta o Lead do cadastro iniciado com os dados hasheados', function () {
    $cadastro = CadastroIniciado::create([
        'email' => 'marcelo@exemplo.com.br', 'nome' => 'Marcelo Andrade', 'telefone' => '(19) 97135-1777',
        'fbp' => 'fb.1.111.222',
    ]);

    $evento = app(MontadorDeConversoesMeta::class)->paraLead(
        $cadastro, 'https://emitiragora.com.br/teste-gratis', ['ip' => '203.0.113.9'],
    );

    expect($evento['event_name'])->toBe('Lead')
        // O navegador calcula o mesmo id a partir do e-mail (ModalCadastro do
        // site): é o que deixa o Meta juntar os dois em vez de contar dois.
        ->and($evento['event_id'])->toBe('lead-'.substr(hash('sha256', 'marcelo@exemplo.com.br'), 0, 32))
        ->and($evento['event_source_url'])->toBe('https://emitiragora.com.br/teste-gratis')
        ->and($evento['user_data']['em'])->toBe([hash('sha256', 'marcelo@exemplo.com.br')])
        ->and($evento['user_data']['ph'])->toBe([hash('sha256', '5519971351777')])
        ->and($evento['user_data']['fn'])->toBe([hash('sha256', 'marcelo')])
        ->and($evento['user_data']['fbp'])->toBe('fb.1.111.222')
        ->and($evento['user_data']['client_ip_address'])->toBe('203.0.113.9');
});

it('manda o codigo de teste de eventos quando configurado', function () {
    config(['integracao.meta.test_event_code' => 'TEST12345']);
    Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1], 200)]);

    app(MetaConversoesGateway::class)->enviar(['event_name' => 'Lead']);

    Http::assertSent(fn ($request) => $request['test_event_code'] === 'TEST12345');
});

it('sem codigo de teste o evento vai como evento de verdade', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1], 200)]);

    app(MetaConversoesGateway::class)->enviar(['event_name' => 'Lead']);

    Http::assertSent(fn ($request) => ! isset($request['test_event_code']));
});
