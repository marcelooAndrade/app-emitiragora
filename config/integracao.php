<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin pessoal
    |--------------------------------------------------------------------------
    |
    | Para onde vão os leads de quem se cadastra no produto. Fica vazio em
    | desenvolvimento e em teste: sem URL nem token, nada sai pela rede.
    |
    */
    'admin_pessoal' => [
        'url' => env('ADMIN_PESSOAL_URL'),
        'token' => env('ADMIN_PESSOAL_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Meta (pixel e Conversions API)
    |--------------------------------------------------------------------------
    |
    | Mesmo pixel e mesma conta de anúncios do projeto Marcelo Andrade. Fica
    | vazio em desenvolvimento e em teste: sem pixel nem token, nada sai pela
    | rede, e o pixel do navegador não é carregado.
    |
    */
    'meta' => [
        'pixel_id' => env('META_PIXEL_ID'),
        'access_token' => env('META_CAPI_ACCESS_TOKEN'),
        // Código da aba "Testar eventos" do Gerenciador de Eventos. Com ele,
        // o servidor manda tudo como teste, para conferir antes de anunciar.
        // Vazio em produção de verdade.
        'test_event_code' => env('META_TEST_EVENT_CODE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | uazapi
    |--------------------------------------------------------------------------
    |
    | Gateway de WhatsApp por trás do módulo WhatsApp do Nodo. O
    | AdminToken é da conta inteira, cria e lista instância; cada instância
    | de cliente tem o próprio token, guardado em whatsapp_instancias, não
    | aqui. Fica vazio em desenvolvimento e em teste: sem URL nem token,
    | nada sai pela rede.
    |
    */
    'uazapi' => [
        'url' => env('UAZAPI_URL'),
        'admin_token' => env('UAZAPI_ADMIN_TOKEN'),
    ],

];
