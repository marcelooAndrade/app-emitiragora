<?php

use App\Models\ApiCliente;
use App\Models\WhatsappInstancia;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config([
        'integracao.uazapi.url' => 'https://uazapi.test',
        'integracao.uazapi.admin_token' => 'admin-de-teste',
    ]);
});

function clienteComWhatsapp(array $extra = []): ApiCliente
{
    return ApiCliente::create(array_merge([
        'nome' => 'Marcelo', 'email' => 'marcelo@exemplo.com.br', 'modulos' => ['whatsapp'],
    ], $extra));
}

it('recusa sem token', function () {
    $this->postJson('http://api.vendaredonda.test/whatsapp/v1/instancia')->assertUnauthorized();
});

/**
 * `post()`/`get()` puros, sem o `Accept: application/json` que
 * `postJson()`/`getJson()` mandam sozinhos — é o que um cliente real, sem
 * essa preocupação, manda por padrão. Sem isto, a suíte inteira só testava
 * o caminho feliz do cabeçalho, e não pegou uma resposta 302 para o /login
 * do sistema fiscal (domínio errado) em vez de 401 em JSON, medida contra
 * o domínio real. Ver shouldRenderJsonWhen em bootstrap/app.php.
 */
it('devolve 401 em json sem token, mesmo sem o cabecalho Accept json', function () {
    $this->post('http://api.vendaredonda.test/whatsapp/v1/instancia')
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/json');
});

it('devolve 403 em json sem o modulo, mesmo sem o cabecalho Accept json', function () {
    $cliente = ApiCliente::create(['nome' => 'Sem acesso', 'email' => 'semacesso@exemplo.com.br']);
    Sanctum::actingAs($cliente);

    $this->post('http://api.vendaredonda.test/whatsapp/v1/instancia')
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/json');
});

it('devolve 422 em json quando falta numero e texto, mesmo sem o cabecalho Accept json', function () {
    $cliente = clienteComWhatsapp();
    Sanctum::actingAs($cliente);
    $cliente->whatsappInstancia()->create([
        'uazapi_instance_id' => 'inst-1', 'uazapi_token' => 'token-uazapi-1', 'nome' => 'x', 'status' => 'connected',
    ]);

    $this->post('http://api.vendaredonda.test/whatsapp/v1/mensagens', [])
        ->assertStatus(422)
        ->assertHeader('Content-Type', 'application/json');
});

it('recusa cliente sem o modulo whatsapp ativo', function () {
    $cliente = ApiCliente::create(['nome' => 'Sem acesso', 'email' => 'semacesso@exemplo.com.br']);
    Sanctum::actingAs($cliente);

    $this->postJson('http://api.vendaredonda.test/whatsapp/v1/instancia')->assertForbidden();
});

it('cria a instancia do cliente autenticado, e configura o webhook na uazapi', function () {
    $cliente = clienteComWhatsapp();
    Sanctum::actingAs($cliente);

    Http::fake([
        'uazapi.test/instance/init' => Http::response([
            'token' => 'token-uazapi-1', 'instance' => ['id' => 'inst-1'],
        ], 200),
        'uazapi.test/webhook' => Http::response(['enabled' => true], 200),
    ]);

    $this->postJson('http://api.vendaredonda.test/whatsapp/v1/instancia')
        ->assertCreated()
        ->assertJson(['status' => 'disconnected']);

    $instancia = WhatsappInstancia::firstWhere('api_cliente_id', $cliente->id);

    expect($instancia)->not->toBeNull()
        ->and($instancia->uazapi_instance_id)->toBe('inst-1')
        ->and($instancia->uazapi_token)->toBe('token-uazapi-1')
        ->and($instancia->webhook_secret)->not->toBeEmpty();

    Http::assertSent(fn ($request) => $request->url() === 'https://uazapi.test/webhook'
        && $request->hasHeader('token', 'token-uazapi-1')
        && $request['enabled'] === true
        && $request['events'] === ['messages']
        && str_contains($request['url'], "uazapi-webhook/{$instancia->webhook_secret}"));
});

it('instancia continua criada mesmo se configurar o webhook falhar', function () {
    $cliente = clienteComWhatsapp();
    Sanctum::actingAs($cliente);

    Http::fake([
        'uazapi.test/instance/init' => Http::response([
            'token' => 'token-uazapi-1', 'instance' => ['id' => 'inst-1'],
        ], 200),
        'uazapi.test/webhook' => Http::response(['erro' => 'fora do ar'], 500),
    ]);

    $this->postJson('http://api.vendaredonda.test/whatsapp/v1/instancia')->assertCreated();

    expect(WhatsappInstancia::firstWhere('api_cliente_id', $cliente->id))->not->toBeNull();
});

it('nao cria uma segunda instancia para quem ja tem uma', function () {
    $cliente = clienteComWhatsapp();
    Sanctum::actingAs($cliente);
    $cliente->whatsappInstancia()->create([
        'uazapi_instance_id' => 'inst-existente', 'uazapi_token' => 'token-existente', 'nome' => 'x',
    ]);

    // Sem Http::fake: se tentar criar de novo, o preventStrayRequests
    // global derruba o teste. É essa a prova de que não chamou a uazapi.
    $this->postJson('http://api.vendaredonda.test/whatsapp/v1/instancia')->assertCreated();

    expect(WhatsappInstancia::where('api_cliente_id', $cliente->id)->count())->toBe(1);
});

it('devolve 404 ao consultar status sem instancia criada', function () {
    $cliente = clienteComWhatsapp();
    Sanctum::actingAs($cliente);

    $this->getJson('http://api.vendaredonda.test/whatsapp/v1/instancia')->assertNotFound();
});

it('conecta e devolve o qrcode, atualizando o status guardado', function () {
    $cliente = clienteComWhatsapp();
    Sanctum::actingAs($cliente);
    $cliente->whatsappInstancia()->create([
        'uazapi_instance_id' => 'inst-1', 'uazapi_token' => 'token-uazapi-1', 'nome' => 'x', 'status' => 'disconnected',
    ]);

    Http::fake(['uazapi.test/instance/connect' => Http::response([
        'instance' => ['qrcode' => 'data:image/png;base64,xyz', 'status' => 'connecting'],
    ], 200)]);

    $this->postJson('http://api.vendaredonda.test/whatsapp/v1/instancia/conectar')
        ->assertOk()
        ->assertJson(['qrcode' => 'data:image/png;base64,xyz', 'status' => 'connecting']);

    expect($cliente->whatsappInstancia->fresh()->status)->toBe('connecting');
});

it('recusa mandar mensagem sem instancia conectada', function () {
    $cliente = clienteComWhatsapp();
    Sanctum::actingAs($cliente);
    $cliente->whatsappInstancia()->create([
        'uazapi_instance_id' => 'inst-1', 'uazapi_token' => 'token-uazapi-1', 'nome' => 'x', 'status' => 'disconnected',
    ]);

    // Sem Http::fake: instância desconectada nem deveria tentar mandar.
    $this->postJson('http://api.vendaredonda.test/whatsapp/v1/mensagens', [
        'numero' => '5519999998888', 'texto' => 'oi',
    ])->assertStatus(422);
});

it('manda mensagem quando a instancia esta conectada', function () {
    $cliente = clienteComWhatsapp();
    Sanctum::actingAs($cliente);
    $cliente->whatsappInstancia()->create([
        'uazapi_instance_id' => 'inst-1', 'uazapi_token' => 'token-uazapi-1', 'nome' => 'x', 'status' => 'connected',
    ]);

    Http::fake(['uazapi.test/send/text' => Http::response(['messageid' => 'msg-1', 'status' => 'Pending'], 200)]);

    $this->postJson('http://api.vendaredonda.test/whatsapp/v1/mensagens', [
        'numero' => '5519999998888', 'texto' => 'oi',
    ])->assertCreated()->assertJson(['enviado' => true, 'id' => 'msg-1']);

    Http::assertSent(fn ($request) => $request->hasHeader('token', 'token-uazapi-1'));
});

it('manda mensagem para grupo, pelo id do grupo com @g.us', function () {
    $cliente = clienteComWhatsapp();
    Sanctum::actingAs($cliente);
    $cliente->whatsappInstancia()->create([
        'uazapi_instance_id' => 'inst-1', 'uazapi_token' => 'token-uazapi-1', 'nome' => 'x', 'status' => 'connected',
    ]);

    Http::fake(['uazapi.test/send/text' => Http::response(['messageid' => 'msg-2', 'status' => 'Pending'], 200)]);

    // Id de grupo tem 23 caracteres; o limite de 20 servia só a telefone.
    $this->postJson('http://api.vendaredonda.test/whatsapp/v1/mensagens', [
        'numero' => '120363312257537196@g.us', 'texto' => 'Relatório do dia',
    ])->assertCreated()->assertJson(['enviado' => true, 'id' => 'msg-2']);

    Http::assertSent(fn ($request) => $request['number'] === '120363312257537196@g.us');
});

it('recusa listar grupos sem instancia conectada', function () {
    $cliente = clienteComWhatsapp();
    Sanctum::actingAs($cliente);
    $cliente->whatsappInstancia()->create([
        'uazapi_instance_id' => 'inst-1', 'uazapi_token' => 'token-uazapi-1', 'nome' => 'x', 'status' => 'disconnected',
    ]);

    // Sem Http::fake: instância desconectada nem deveria tentar listar.
    $this->getJson('http://api.vendaredonda.test/whatsapp/v1/grupos')->assertStatus(422);
});

it('lista os grupos quando a instancia esta conectada', function () {
    $cliente = clienteComWhatsapp();
    Sanctum::actingAs($cliente);
    $cliente->whatsappInstancia()->create([
        'uazapi_instance_id' => 'inst-1', 'uazapi_token' => 'token-uazapi-1', 'nome' => 'x', 'status' => 'connected',
    ]);

    Http::fake(['uazapi.test/group/list' => Http::response([
        'groups' => [['id' => '123-456@g.us', 'name' => 'Grupo de teste']],
    ], 200)]);

    $this->getJson('http://api.vendaredonda.test/whatsapp/v1/grupos')
        ->assertOk()
        ->assertJson(['grupos' => [['id' => '123-456@g.us', 'name' => 'Grupo de teste']]]);

    Http::assertSent(fn ($request) => $request->hasHeader('token', 'token-uazapi-1'));
});

it('exige numero e texto', function () {
    $cliente = clienteComWhatsapp();
    Sanctum::actingAs($cliente);
    $cliente->whatsappInstancia()->create([
        'uazapi_instance_id' => 'inst-1', 'uazapi_token' => 'token-uazapi-1', 'nome' => 'x', 'status' => 'connected',
    ]);

    $this->postJson('http://api.vendaredonda.test/whatsapp/v1/mensagens', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['numero', 'texto']);
});
