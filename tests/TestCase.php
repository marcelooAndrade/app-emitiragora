<?php

namespace Tests;

use App\Enums\PlanoTenant;
use App\Models\Tenant;
use App\Support\TenantAtual;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * O preparo fica aqui, e não no Pest.php, porque parte da suíte são
     * classes PHPUnit do starter kit. O `beforeEach` do Pest não alcança
     * essas classes, e elas rodavam sem tenant, o que quebrava a autenticação
     * com "credenciais não conferem".
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Nenhum teste fala com a internet. Requisição não fingida vira erro
        // em vez de sair para a rede e tornar a suíte dependente dela.
        Http::preventStrayRequests();

        if (! Schema::hasTable('tenants')) {
            return;
        }

        // Requisição real sempre tem um tenant resolvido pelo host. O domínio
        // é o host que o cliente HTTP de teste usa, para que o middleware
        // resolva o mesmo tenant que os testes de unidade usam.
        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'teste'],
            // Tem domínio, então precisa do plano que permite domínio próprio.
            ['nome' => 'Tenant de teste', 'dominio' => 'localhost', 'plano' => PlanoTenant::Transporte],
        );

        app(TenantAtual::class)->definir($tenant);
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
