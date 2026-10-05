<?php

use App\Enums\Perfil;
use App\Enums\PlanoTenant;
use App\Models\Emitente;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantAtual;
use Database\Seeders\PerfilSeeder;

/**
 * Cadastro é porta do produto, e só dela.
 *
 * Em domínio de cliente ele não pode existir: até 11/09/2026 a rota criava
 * conta dentro do tenant do cliente, o que foi medido e provado.
 */
beforeEach(function () {
    $this->seed(PerfilSeeder::class);
    Tenant::query()->delete();
    config(['produto.dominio' => 'vendaredonda.com.br']);
});

function dadosDeCadastro(array $extra = []): array
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

it('nao existe em dominio de cliente', function () {
    Tenant::create([
        'nome' => 'RCM', 'slug' => 'rcm',
        'plano' => PlanoTenant::Transporte, 'dominio' => 'app.rcmdobrasil.com.br',
    ]);

    $this->get('http://app.rcmdobrasil.com.br/register')->assertNotFound();

    $this->post('http://app.rcmdobrasil.com.br/register', dadosDeCadastro())->assertNotFound();

    expect(User::withoutGlobalScopes()->count())->toBe(0);
})->skip('Domínio próprio de cliente desativado em 14/09/2026, ver TenantAtual::resolverHost');

it('existe no dominio do produto', function () {
    $this->get('http://vendaredonda.com.br/register')->assertOk();
});

it('cria empresa, emitente, usuario e papel de uma vez', function () {
    $this->post('http://vendaredonda.com.br/register', dadosDeCadastro());

    $tenant = Tenant::firstWhere('nome', 'DISTRIBUIDORA RIO CLARO LTDA');

    expect($tenant)->not->toBeNull()
        ->and($tenant->plano)->toBe(PlanoTenant::Transporte)
        ->and($tenant->dominio)->toBeNull();

    app(TenantAtual::class)->definir($tenant);

    $emitente = Emitente::firstWhere('cnpj', '11222333000181');
    $user = User::firstWhere('email', 'marcelo@exemplo.com.br');

    expect($emitente)->not->toBeNull()
        ->and($emitente->tenant_id)->toBe($tenant->id)
        ->and($user->tenant_id)->toBe($tenant->id)
        ->and($user->emitentes()->pluck('emitentes.id')->all())->toBe([$emitente->id]);

    setPermissionsTeamId($emitente->id);

    expect($user->fresh()->hasRole(Perfil::Administrador->value))->toBeTrue();
});

it('entra direto depois de se cadastrar e cai no painel', function () {
    $this->post('http://vendaredonda.com.br/register', dadosDeCadastro())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

it('recusa cnpj invalido sem criar nada', function () {
    $this->post('http://vendaredonda.com.br/register', dadosDeCadastro(['cnpj' => '11222333000100']))
        ->assertSessionHasErrors('cnpj');

    expect(Tenant::count())->toBe(0)
        ->and(User::withoutGlobalScopes()->count())->toBe(0);
});

it('recusa cnpj ja cadastrado em outra empresa', function () {
    $this->post('http://vendaredonda.com.br/register', dadosDeCadastro());
    $this->post('http://vendaredonda.com.br/logout');

    $this->post('http://vendaredonda.com.br/register', dadosDeCadastro([
        'email' => 'outro@exemplo.com.br',
    ]))->assertSessionHasErrors('cnpj');

    expect(Tenant::count())->toBe(1);
});

it('gera slug do nome da empresa, sem colidir com reservado', function () {
    $this->post('http://vendaredonda.com.br/register', dadosDeCadastro([
        'razao_social' => 'APP',
    ]));

    $tenant = Tenant::firstWhere('nome', 'APP');

    // `app` é reservado: capturaria o host do próprio produto.
    expect($tenant->slug)->not->toBe('app');
});

it('exige telefone no cadastro', function () {
    $dados = dadosDeCadastro();
    unset($dados['telefone']);

    $this->post('http://vendaredonda.com.br/register', $dados)
        ->assertSessionHasErrors('telefone');

    expect(Tenant::count())->toBe(0);
});

it('grava o telefone no emitente', function () {
    $this->post('http://vendaredonda.com.br/register', dadosDeCadastro(['telefone' => '(19) 99999-8888']));

    expect(Emitente::first()->telefone)->toBe('(19) 99999-8888');
});

/**
 * O admin pessoal aceita razao_social até 200 caracteres. Sem espelhar esse
 * limite aqui, o cadastro seria aceito neste lado e o lead descartado em
 * silêncio do lado de lá.
 */
it('recusa razao social maior que o destino aceita', function () {
    $this->post('http://vendaredonda.com.br/register', dadosDeCadastro([
        'razao_social' => str_repeat('A', 201),
    ]))->assertSessionHasErrors('razao_social');

    expect(Tenant::count())->toBe(0);
});

/**
 * O modal do site (site-emitiragora) pergunta nome, e-mail e WhatsApp antes
 * de mandar a pessoa pra cá. Ela não deveria ter que digitar tudo de novo.
 */
it('preenche nome, e-mail e telefone vindos do site', function () {
    $this->get('http://vendaredonda.com.br/register?nome=Marcelo%20Andrade&email=marcelo%40exemplo.com.br&telefone=19971351777')
        ->assertOk()
        ->assertSee('value="Marcelo Andrade"', false)
        ->assertSee('value="marcelo@exemplo.com.br"', false)
        ->assertSee('value="19971351777"', false);
});

it('nao quebra quando o site manda parametro em formato de lista', function () {
    $this->get('http://vendaredonda.com.br/register?nome[]=x&tipo[]=transportadora')->assertOk();
});

it('repassa as respostas do site em campos escondidos', function () {
    $this->get('http://vendaredonda.com.br/register?tipo=transportadora&frota=6-20&volume=11-30')
        ->assertOk()
        ->assertSee('name="tipo" value="transportadora"', false)
        ->assertSee('name="frota" value="6-20"', false)
        ->assertSee('name="volume" value="11-30"', false);
});

it('guarda as respostas do site na empresa', function () {
    $this->post('http://vendaredonda.com.br/register', dadosDeCadastro([
        'perfil_tipo' => 'transportadora',
        'perfil_frota' => '6-20',
        'perfil_volume' => '11-30',
    ]));

    expect(Tenant::first()->perfil_cadastro)
        ->toBe(['tipo' => 'transportadora', 'frota' => '6-20', 'volume' => '11-30']);
});

/**
 * A resposta chega por campo escondido: se virasse erro de validação, a
 * pessoa não teria como corrigir e o cadastro travaria.
 */
it('ignora resposta fora da lista sem barrar o cadastro', function () {
    $this->post('http://vendaredonda.com.br/register', dadosDeCadastro([
        'perfil_tipo' => 'transportadora',
        'perfil_frota' => 'mil-caminhoes',
    ]))->assertSessionHasNoErrors();

    expect(Tenant::first()->perfil_cadastro)->toBe(['tipo' => 'transportadora']);
});

it('deixa o perfil vazio pra quem se cadastra direto pelo app', function () {
    $this->post('http://vendaredonda.com.br/register', dadosDeCadastro());

    expect(Tenant::first()->perfil_cadastro)->toBeNull();
});

it('transportadora entra no plano Transporte', function () {
    $this->post('http://vendaredonda.com.br/register', dadosDeCadastro(['perfil_tipo' => 'transportadora']));

    expect(Tenant::first()->plano)->toBe(PlanoTenant::Transporte);
});

it('empresa sem frota entra no plano Pequena Empresa', function () {
    $this->post('http://vendaredonda.com.br/register', dadosDeCadastro(['perfil_tipo' => 'empresa']));

    expect(Tenant::first()->plano)->toBe(PlanoTenant::PequenaEmpresa);
});
