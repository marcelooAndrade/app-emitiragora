<?php

use App\Enums\PlanoTenant;
use App\Models\Emitente;
use App\Models\Pessoa;
use App\Models\Tenant;
use App\Models\User;
use App\Support\EmitenteAtual;
use App\Support\TenantAtual;

function tenantCom(string $slug): Tenant
{
    return Tenant::create(['nome' => ucfirst($slug), 'slug' => $slug]);
}

function usuarioDo(Tenant $tenant): User
{
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $emitente = Emitente::factory()->create(['tenant_id' => $tenant->id]);
    $user->emitentes()->attach($emitente);

    return $user;
}

beforeEach(function () {
    $this->rcm = tenantCom('rcm');
    $this->transm = tenantCom('transportes-leme');
});

it('resolve o tenant pelo subdominio', function () {
    app(TenantAtual::class)->definirPorHost('rcm.emissor.test');

    expect(app(TenantAtual::class)->obter()->id)->toBe($this->rcm->id);
})->skip('Subdomínio por slug desativado em 14/09/2026, ver TenantAtual::resolverHost');

it('resolve o tenant por dominio proprio', function () {
    // Domínio próprio é benefício de plano, então vem com o plano junto.
    $this->rcm->update(['plano' => PlanoTenant::Transporte, 'dominio' => 'sistema.rcmdobrasil.com.br']);

    app(TenantAtual::class)->definirPorHost('sistema.rcmdobrasil.com.br');

    expect(app(TenantAtual::class)->obter()->id)->toBe($this->rcm->id);
})->skip('Domínio próprio de cliente desativado em 14/09/2026, ver TenantAtual::resolverHost');

it('devolve nulo para host desconhecido', function () {
    app(TenantAtual::class)->definirPorHost('inexistente.emissor.test');

    expect(app(TenantAtual::class)->obter())->toBeNull();
});

it('ignora tenant inativo', function () {
    $this->rcm->update(['ativo' => false]);

    app(TenantAtual::class)->definirPorHost('rcm.emissor.test');

    expect(app(TenantAtual::class)->obter())->toBeNull();
})->skip('Dependia da resolução por subdomínio, desativada em 14/09/2026, ver TenantAtual::resolverHost');

it('esconde emitente de outro tenant', function () {
    Emitente::factory()->create(['tenant_id' => $this->rcm->id, 'razao_social' => 'RCM do Brasil']);
    Emitente::factory()->create(['tenant_id' => $this->transm->id, 'razao_social' => 'Transportes Leme']);

    app(TenantAtual::class)->definir($this->rcm);

    expect(Emitente::query()->pluck('razao_social')->all())->toBe(['RCM do Brasil']);
});

it('esconde pessoa de outro tenant', function () {
    $eRcm = Emitente::factory()->create(['tenant_id' => $this->rcm->id]);
    $eOutro = Emitente::factory()->create(['tenant_id' => $this->transm->id]);

    $dados = fn (Emitente $e, string $doc, string $nome) => Pessoa::withoutGlobalScopes()->create([
        'emitente_id' => $e->id, 'tipo_pessoa' => 'J', 'documento' => $doc, 'razao_social' => $nome,
        'ind_ie_dest' => '2', 'logradouro' => 'R', 'numero' => '1', 'bairro' => 'C',
        'codigo_municipio' => '3503307', 'municipio' => 'Araras', 'uf' => 'SP', 'cep' => '13602200',
        'e_cliente' => true,
    ]);
    $dados($eRcm, '11222333000181', 'Cliente da RCM');
    $dados($eOutro, '12ABC34501DE35', 'Cliente da Leme');

    app(TenantAtual::class)->definir($this->rcm);

    expect(Pessoa::query()->pluck('razao_social')->all())->toBe(['Cliente da RCM']);
});

it('com tenant fixado pelo host, sessao nao escapa para emitente de outro tenant', function () {
    $user = usuarioDo($this->rcm);
    // Login com acesso legítimo a uma segunda empresa: desde 14/09/2026 isto
    // é um caso comum, não mais um erro operacional. A garantia que
    // continua valendo é outra: com o host já tendo fixado um tenant
    // (domínio próprio de um cliente), a sessão não pode usar essa segunda
    // empresa ali, mesmo tendo acesso a ela em algum lugar.
    $outraEmpresa = Emitente::factory()->create(['tenant_id' => $this->transm->id]);
    $user->emitentes()->attach($outraEmpresa);

    app(TenantAtual::class)->definir($this->rcm);
    $this->actingAs($user);
    session()->put('emitente_atual_id', $outraEmpresa->id);

    expect(app(EmitenteAtual::class)->resolver()->tenant_id)->toBe($this->rcm->id);
});

it('sem tenant fixado pelo host, a sessao atravessa as empresas do login', function () {
    $user = usuarioDo($this->rcm);
    $outraEmpresa = Emitente::factory()->create(['tenant_id' => $this->transm->id, 'razao_social' => 'Outra empresa']);
    $user->emitentes()->attach($outraEmpresa);

    app(TenantAtual::class)->limpar();
    $this->actingAs($user);
    session()->put('emitente_atual_id', $outraEmpresa->id);

    expect(app(EmitenteAtual::class)->resolver()->id)->toBe($outraEmpresa->id);
});

it('sem tenant definido nao vaza dado de ninguem', function () {
    Emitente::factory()->create(['tenant_id' => $this->rcm->id]);
    Emitente::factory()->create(['tenant_id' => $this->transm->id]);

    app(TenantAtual::class)->limpar();

    expect(Emitente::query()->count())->toBe(0);
});
