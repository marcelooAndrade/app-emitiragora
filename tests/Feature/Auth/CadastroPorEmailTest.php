<?php

use App\Mail\ContaJaExiste;
use App\Mail\ConviteDeCadastro;
use App\Models\CadastroIniciado;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PerfilSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * Cadastro com o e-mail no meio: o site (ou o /register) manda o contato, o
 * link vai para o e-mail, e só quem abre o link cria a conta. Ver
 * CadastroController e CreateNewUser.
 */
beforeEach(function () {
    $this->seed(PerfilSeeder::class);
    Tenant::query()->delete();
    config(['produto.dominio' => 'vendaredonda.com.br']);
    Mail::fake();
});

const SITE = 'https://emitiragora.com.br';
const INICIAR = 'http://vendaredonda.com.br/cadastro/iniciar';

function contatoDoSite(array $extra = []): array
{
    return array_merge([
        'nome' => 'Marcelo Andrade',
        'email' => 'Marcelo@Exemplo.com.br',
        'telefone' => '(19) 97135-1777',
        'frota' => '6-20',
        'volume' => '11-30',
        'origem' => 'https://emitiragora.com.br/teste-gratis?utm_source=meta&fbclid=abc',
        'fbp' => 'fb.1.111.222',
        'fbc' => 'fb.1.111.abc',
    ], $extra);
}

/** O link que o último e-mail de convite levou. */
function linkDoConvite(): string
{
    $link = null;
    Mail::assertSent(ConviteDeCadastro::class, function (ConviteDeCadastro $mail) use (&$link) {
        $link = $mail->link;

        return true;
    });

    return $link;
}

function conviteDoLink(string $link): string
{
    return basename(parse_url($link, PHP_URL_PATH));
}

it('o modal do site guarda o contato e manda o link pro e-mail', function () {
    $this->withHeaders(['Origin' => SITE])
        ->postJson(INICIAR, contatoDoSite())
        ->assertOk()
        ->assertJson(['email' => 'marcelo@exemplo.com.br']);

    $cadastro = CadastroIniciado::sole();
    expect($cadastro->email)->toBe('marcelo@exemplo.com.br')
        ->and($cadastro->perfil)->toBe(['frota' => '6-20', 'volume' => '11-30'])
        ->and($cadastro->origem)->toContain('utm_source=meta')
        ->and($cadastro->fbp)->toBe('fb.1.111.222')
        ->and($cadastro->convertido_em)->toBeNull()
        ->and(User::withoutGlobalScopes()->count())->toBe(0);

    Mail::assertSent(ConviteDeCadastro::class, fn ($mail) => $mail->hasTo('marcelo@exemplo.com.br'));
    expect(linkDoConvite())->toStartWith('http://vendaredonda.com.br/cadastro/concluir/');
});

it('recusa envio sem origem ou de outro site', function (?string $origem) {
    $this->withHeaders(array_filter(['Origin' => $origem]))
        ->postJson(INICIAR, contatoDoSite())
        ->assertForbidden();

    expect(CadastroIniciado::count())->toBe(0);
    Mail::assertNothingSent();
})->with([
    'sem origem' => [null],
    'outro site' => ['https://golpe.example.com'],
    'subdomínio parecido' => ['https://emitiragora.com.br.golpe.example.com'],
]);

it('libera o CORS só pro site', function () {
    $preflight = fn (string $origem) => $this->call('OPTIONS', INICIAR, server: [
        'HTTP_ORIGIN' => $origem,
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type',
    ]);

    $preflight(SITE)->assertHeader('Access-Control-Allow-Origin', SITE);
    $preflight('https://golpe.example.com')->assertHeaderMissing('Access-Control-Allow-Origin');
});

it('o formulario do proprio app leva pra tela de confira seu e-mail', function () {
    $this->withHeaders(['Origin' => 'http://vendaredonda.com.br'])
        ->post(INICIAR, contatoDoSite())
        ->assertRedirect('http://vendaredonda.com.br/cadastro/enviado');

    $this->get('http://vendaredonda.com.br/cadastro/enviado')
        ->assertOk()
        ->assertSee('Foi enviado o link de acesso para o seu e-mail.')
        ->assertSee('marcelo@exemplo.com.br');
});

it('mostra erro do contato no tom do site', function () {
    $this->withHeaders(['Origin' => SITE])
        ->postJson(INICIAR, contatoDoSite(['email' => 'nao-e-email', 'telefone' => '123']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email' => 'Informe um e-mail válido.', 'telefone' => 'Informe o WhatsApp com DDD.']);

    Mail::assertNothingSent();
});

it('e-mail que ja tem conta recebe aviso, e a tela responde igual', function () {
    $this->post('http://vendaredonda.com.br/register', comConvite([
        'name' => 'Marcelo Andrade', 'email' => 'marcelo@exemplo.com.br',
        'password' => 'senha-muito-longa-123', 'password_confirmation' => 'senha-muito-longa-123',
        'razao_social' => 'DISTRIBUIDORA RIO CLARO LTDA', 'cnpj' => '11222333000181',
        'inscricao_estadual' => '123456789012', 'crt' => '3', 'telefone' => '1930960072',
    ]));
    auth()->logout();
    Mail::fake();

    $this->withHeaders(['Origin' => SITE])
        ->postJson(INICIAR, contatoDoSite())
        ->assertOk()
        ->assertJson(['email' => 'marcelo@exemplo.com.br']);

    Mail::assertSent(ContaJaExiste::class, fn ($mail) => $mail->hasTo('marcelo@exemplo.com.br'));
    Mail::assertNotSent(ConviteDeCadastro::class);
});

it('nao manda mais de tres e-mails por hora pro mesmo endereco', function () {
    foreach (range(1, 4) as $_) {
        $this->withHeaders(['Origin' => SITE])->postJson(INICIAR, contatoDoSite())->assertOk();
    }

    Mail::assertSentCount(3);
});

it('o link abre o resto do cadastro com o e-mail travado', function () {
    $this->withHeaders(['Origin' => SITE])->postJson(INICIAR, contatoDoSite());

    $this->get(linkDoConvite())
        ->assertOk()
        ->assertSee('E-mail confirmado')
        ->assertSee('marcelo@exemplo.com.br')
        ->assertSee('name="convite"', false)
        ->assertDontSee('name="email"', false);
});

it('link inventado, vencido ou trocado por um mais novo nao abre nada', function () {
    $this->get('http://vendaredonda.com.br/cadastro/concluir/inventado')->assertSee('Este link não vale mais');

    $this->withHeaders(['Origin' => SITE])->postJson(INICIAR, contatoDoSite());
    $primeiro = linkDoConvite();
    Mail::fake();
    $this->withHeaders(['Origin' => SITE])->postJson(INICIAR, contatoDoSite());
    $segundo = linkDoConvite();

    $this->get($primeiro)->assertSee('Este link não vale mais');
    $this->get($segundo)->assertSee('E-mail confirmado');

    $this->travel(CadastroIniciado::DIAS_DO_CONVITE)->days();
    $this->travel(1)->minutes();
    $this->get($segundo)->assertSee('Este link não vale mais');
});

it('so com o link a conta nasce, com o e-mail do convite e ja verificado', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1], 200)]);
    config(['integracao.meta.pixel_id' => '123456789', 'integracao.meta.access_token' => 'segredo']);

    $this->withHeaders(['Origin' => SITE])->postJson(INICIAR, contatoDoSite());
    $convite = conviteDoLink(linkDoConvite());

    $this->post('http://vendaredonda.com.br/register', [
        'convite' => $convite,
        // Tentativa de trocar o e-mail no formulário: vale o do convite.
        'email' => 'outro@exemplo.com.br',
        'name' => 'Marcelo Andrade',
        'password' => 'senha-muito-longa-123',
        'password_confirmation' => 'senha-muito-longa-123',
        'razao_social' => 'TRANSPORTADORA RIO CLARO LTDA',
        'cnpj' => '11222333000181',
        'inscricao_estadual' => '123456789012',
        'crt' => '3',
        'telefone' => '(19) 97135-1777',
    ])->assertRedirect('http://vendaredonda.com.br/dashboard');

    $user = User::withoutGlobalScopes()->sole();
    expect($user->email)->toBe('marcelo@exemplo.com.br')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Tenant::sole()->perfil_cadastro)->toBe(['frota' => '6-20', 'volume' => '11-30']);
    $this->assertAuthenticatedAs($user);

    $cadastro = CadastroIniciado::sole();
    expect($cadastro->convertido_em)->not->toBeNull()
        ->and($cadastro->user_id)->toBe($user->id);

    // Sem os cookies do anúncio neste navegador, a conversão leva os que o
    // site mandou no começo.
    Http::assertSent(fn ($request) => ($request['data'][0]['user_data']['fbp'] ?? null) === 'fb.1.111.222');

    // O mesmo link não cria segunda conta.
    auth()->logout();
    $this->get("http://vendaredonda.com.br/cadastro/concluir/{$convite}")->assertSee('Este link não vale mais');
});

it('sem convite nao nasce conta', function () {
    $this->post('http://vendaredonda.com.br/register', [
        'name' => 'Marcelo Andrade', 'email' => 'marcelo@exemplo.com.br',
        'password' => 'senha-muito-longa-123', 'password_confirmation' => 'senha-muito-longa-123',
        'razao_social' => 'DISTRIBUIDORA RIO CLARO LTDA', 'cnpj' => '11222333000181',
        'inscricao_estadual' => '123456789012', 'crt' => '3', 'telefone' => '1930960072',
    ])->assertSessionHasErrors('convite');

    expect(User::withoutGlobalScopes()->count())->toBe(0);
});

it('o e-mail guardado vira Lead no Meta, com o mesmo id que o navegador usa', function () {
    config(['integracao.meta.pixel_id' => '4043930915910691', 'integracao.meta.access_token' => 'segredo']);
    Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1], 200)]);

    $this->withHeaders(['Origin' => SITE, 'User-Agent' => 'Navegador/1.0'])->postJson(INICIAR, contatoDoSite())->assertOk();

    Http::assertSent(function ($request) {
        $evento = $request['data'][0] ?? [];

        return $request->url() === 'https://graph.facebook.com/v21.0/4043930915910691/events'
            && $evento['event_name'] === 'Lead'
            && $evento['event_id'] === 'lead-'.substr(hash('sha256', 'marcelo@exemplo.com.br'), 0, 32)
            && $evento['event_source_url'] === 'https://emitiragora.com.br/teste-gratis?utm_source=meta&fbclid=abc'
            && $evento['user_data']['fbp'] === 'fb.1.111.222'
            && $evento['user_data']['client_user_agent'] === 'Navegador/1.0';
    });
});

it('e-mail que ja tem conta nao vira Lead', function () {
    $this->post('http://vendaredonda.com.br/register', comConvite([
        'name' => 'Marcelo Andrade', 'email' => 'marcelo@exemplo.com.br',
        'password' => 'senha-muito-longa-123', 'password_confirmation' => 'senha-muito-longa-123',
        'razao_social' => 'DISTRIBUIDORA RIO CLARO LTDA', 'cnpj' => '11222333000181',
        'inscricao_estadual' => '123456789012', 'crt' => '3', 'telefone' => '1930960072',
    ]));
    auth()->logout();
    config(['integracao.meta.pixel_id' => '4043930915910691', 'integracao.meta.access_token' => 'segredo']);
    Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1], 200)]);

    $this->withHeaders(['Origin' => SITE])->postJson(INICIAR, contatoDoSite())->assertOk();

    Http::assertNotSent(fn ($request) => ($request['data'][0]['event_name'] ?? null) === 'Lead');
});
