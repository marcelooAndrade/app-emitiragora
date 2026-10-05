<?php

use App\Jobs\EnviarLeads;
use App\Models\Tenant;
use Database\Seeders\PerfilSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(PerfilSeeder::class);
    Tenant::query()->delete();
    config([
        'produto.dominio' => 'vendaredonda.com.br',
        'integracao.admin_pessoal.url' => 'https://exemplo.test',
        'integracao.admin_pessoal.token' => 'segredo-de-teste',
    ]);
});

function cadastroValido(array $extra = []): array
{
    return comConvite(array_merge([
        'name' => 'Marcelo Andrade',
        'email' => 'marcelo@exemplo.com.br',
        'password' => 'senha-muito-longa-123',
        'password_confirmation' => 'senha-muito-longa-123',
        'razao_social' => 'DISTRIBUIDORA RIO CLARO LTDA',
        'cnpj' => '11222333000181',
        'inscricao_estadual' => '123456789012',
        'crt' => '3',
        'telefone' => '1930960072',
    ], $extra));
}

it('o cadastro despacha o envio do lead', function () {
    Queue::fake();

    $this->post('http://vendaredonda.com.br/register', cadastroValido());

    $tenant = Tenant::firstWhere('nome', 'DISTRIBUIDORA RIO CLARO LTDA');

    Queue::assertPushed(EnviarLeads::class, function (EnviarLeads $job) use ($tenant) {
        return $job->leads[0]['tenant_id'] === $tenant->id
            && $job->leads[0]['telefone'] === '1930960072'
            && $job->leads[0]['email'] === 'marcelo@exemplo.com.br';
    });
});

/**
 * O admin pessoal fora do ar não pode impedir alguém de criar a conta. O envio
 * é consequência do cadastro, e nunca condição dele.
 */
it('o cadastro sobrevive a falha no envio', function () {
    Http::fake(['exemplo.test/*' => Http::response('fora do ar', 500)]);

    $resposta = $this->post('http://vendaredonda.com.br/register', cadastroValido());

    // Com fila `sync` (a da suíte), o dispatch() roda o job na mesma
    // requisição. Sem o try/catch em CreateNewUser, a exceção do gateway
    // subiria e a pessoa veria uma tela de erro 500, mesmo com o tenant já
    // criado. "/dashboard" é o redirecionamento real que o Fortify devolve
    // num cadastro bem-sucedido (config('fortify.home')), confirmado por
    // inspeção da resposta antes de escrever esta asserção: prova que a
    // resposta chegou até o fim do fluxo de sucesso, e não parou no meio por
    // causa da integração.
    $resposta->assertRedirect('/dashboard');

    expect(Tenant::where('nome', 'DISTRIBUIDORA RIO CLARO LTDA')->exists())->toBeTrue();
});

/**
 * O catch em CreateNewUser é largo de propósito (o cadastro não pode
 * depender do outro sistema, nem do driver de fila). Por isso a exceção
 * também precisa ser reportada e logada com a própria classe: sem isso,
 * "o admin pessoal está fora do ar" (normal, a fila tenta de novo) e "alguém
 * quebrou o montador" (bug de programação) ficariam indistinguíveis no log.
 */
it('reporta a excecao quando o envio falha', function () {
    Log::spy();
    Http::fake(['exemplo.test/*' => Http::response('fora do ar', 500)]);

    $this->post('http://vendaredonda.com.br/register', cadastroValido());

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $mensagem, array $contexto) => $contexto['excecao'] === RuntimeException::class);
});
