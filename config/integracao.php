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
