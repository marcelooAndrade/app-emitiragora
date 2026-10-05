<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    // `cadastro/iniciar` recebe o modal do site (outro domínio), por fetch.
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'cadastro/iniciar'],

    'allowed_methods' => ['*'],

    // O site do produto, e só ele. É também a lista que o
    // ExigirOrigemDoSite usa para aceitar o envio sem token de CSRF: uma
    // origem só, para o navegador e o servidor nunca discordarem.
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('ORIGENS_DO_SITE', 'https://emitiragora.com.br,https://www.emitiragora.com.br'))))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
