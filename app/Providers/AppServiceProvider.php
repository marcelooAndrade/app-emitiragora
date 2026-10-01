<?php

namespace App\Providers;

use App\Services\Fiscal\ConversorLegado;
use App\Services\Fiscal\ConversorLegadoOpenssl;
use App\Services\Fiscal\NfephpSefazGateway;
use App\Services\Fiscal\SefazGateway;
use App\Services\Integrations\AdminPessoalGateway;
use App\Services\Integrations\GatewayDeConversoes;
use App\Services\Integrations\GatewayDeLeads;
use App\Services\Integrations\GatewayDeWhatsapp;
use App\Services\Integrations\MetaConversoesGateway;
use App\Services\Integrations\UazapiGateway;
use App\Services\Nfse\GatewayNfse;
use App\Services\Nfse\SigissGateway;
use App\Support\ApresentacaoTokens;
use App\Support\TenantAtual;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ConversorLegado::class, ConversorLegadoOpenssl::class);
        $this->app->bind(SefazGateway::class, NfephpSefazGateway::class);
        $this->app->bind(GatewayDeLeads::class, AdminPessoalGateway::class);
        $this->app->bind(GatewayDeConversoes::class, MetaConversoesGateway::class);
        $this->app->bind(GatewayDeWhatsapp::class, UazapiGateway::class);
        $this->app->bind(GatewayNfse::class, SigissGateway::class);
        $this->app->singleton(TenantAtual::class);

        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Por cliente, não por IP: é o token que identifica quem está
        // usando, e a cota real (a conta uazapi) é compartilhada por todos
        // os clientes deste módulo. 30/min é ponto de partida, revisável
        // quando houver uso de verdade.
        RateLimiter::for('whatsapp', fn (Request $request): Limit => Limit::perMinute(30)->by($request->user()?->id));

        // `@include` do Blade não devolve variável pra quem inclui, então o
        // bloco de ícones e classes do template Aura precisa chegar por
        // composer nas views que o reaproveitam fora da apresentação.
        View::composer(
            ['apresentacao', 'contadores', 'components.planos'],
            fn ($view) => $view->with(ApresentacaoTokens::compartilhados()),
        );
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // Piso de 8, não de 12: o de 12 barrava cadastro legítimo na porta de
        // entrada. O que segura a senha fraca aqui é o `uncompromised`, que
        // recusa senha já vista em vazamento, e não o comprimento.
        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(8)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
