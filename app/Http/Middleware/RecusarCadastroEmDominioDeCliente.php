<?php

namespace App\Http\Middleware;

use App\Support\TenantAtual;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cadastro é porta do produto, e só dela.
 *
 * Até 11/09/2026 a rota existia em qualquer host, e no domínio próprio de um
 * cliente ela criava conta **dentro do tenant dele**: o `tenant_id` vinha do
 * host. Foi medido, não deduzido.
 *
 * Esconder o link não resolveria: a rota continuaria aberta a quem soubesse o
 * endereço. Aqui ela deixa de existir.
 */
class RecusarCadastroEmDominioDeCliente
{
    public function __construct(
        private readonly TenantAtual $tenantAtual,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('register', 'register.store', 'cadastro.*') && $this->tenantAtual->id() !== null) {
            abort(404);
        }

        return $next($request);
    }
}
