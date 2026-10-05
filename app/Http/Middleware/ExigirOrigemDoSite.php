<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Proteção contra formulário forjado para rota que recebe o site.
 *
 * O modal do site (outro domínio) não tem como pegar o token de CSRF desta
 * aplicação, então a rota sai da checagem de token e entra nesta, por
 * origem: o navegador sempre manda `Origin` num POST e nenhuma página
 * consegue falsificá-lo. Aceita o próprio app (o formulário de /register) e
 * as origens do site em `cors.allowed_origins`; qualquer outra, ou nenhuma,
 * leva 403.
 */
class ExigirOrigemDoSite
{
    public function handle(Request $request, Closure $next): Response
    {
        $origem = rtrim((string) $request->headers->get('Origin'), '/');

        $aceitas = [
            $request->getSchemeAndHttpHost(),
            ...array_map(fn (string $o) => rtrim($o, '/'), (array) config('cors.allowed_origins')),
        ];

        abort_unless($origem !== '' && in_array($origem, $aceitas, true), 403);

        return $next($request);
    }
}
