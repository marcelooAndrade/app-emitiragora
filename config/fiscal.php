<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Responsável técnico (infRespTec)
    |--------------------------------------------------------------------------
    |
    | Identifica a software house perante a SEFAZ. Fica em configuração global
    | porque é o mesmo em todos os emitentes atendidos por esta instalação.
    |
    | Obrigatório antes de virar qualquer emitente para produção: a validação
    | da virada confere estes campos.
    |
    */
    'responsavel_tecnico' => [
        'cnpj' => env('FISCAL_RESP_TEC_CNPJ'),
        'contato' => env('FISCAL_RESP_TEC_CONTATO'),
        'email' => env('FISCAL_RESP_TEC_EMAIL'),
        'telefone' => env('FISCAL_RESP_TEC_TELEFONE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Layout
    |--------------------------------------------------------------------------
    */
    'versao_nfe' => '4.00',
    'modelo' => 55,

    /*
    |--------------------------------------------------------------------------
    | Pacote de leiaute (schema)
    |--------------------------------------------------------------------------
    |
    | O `Make` da sped-nfe assume PL_009 quando não recebe schema, e PL_009 é
    | anterior à Reforma Tributária: os grupos IBS, CBS, IS e DFeReferenciado
    | simplesmente não são renderizados, sem erro nenhum.
    |
    | Confirmar contra a NT vigente antes de emitir em produção. Ver DF-003.
    |
    */
    'schema' => env('FISCAL_SCHEMA', 'PL_010_V1.30'),

    /*
    |--------------------------------------------------------------------------
    | Averbação do seguro da carga (AT&M)
    |--------------------------------------------------------------------------
    |
    | Portado do app-transm, que só liberava a integração em homologação. A
    | mesma trava fica aqui: CT-e de produção só é averbado automaticamente
    | quando ATM_PRODUCAO=true, depois de validado com a seguradora.
    |
    */
    'atm' => [
        'url' => env('ATM_URL', 'https://webserver.averba.com.br/rest'),
        'producao' => (bool) env('ATM_PRODUCAO', false),
        'connect_timeout' => 10,
        'timeout' => 45,
        'token_ttl_seconds' => 3300,
    ],

    /*
    |--------------------------------------------------------------------------
    | CIOT pelo e-Frete
    |--------------------------------------------------------------------------
    |
    | Portado do app-transm, com a mesma trava: só o endereço oficial de
    | homologação e só para emitente em homologação. A massa de teste da ANTT
    | troca contratado, RNTRC e placas pelos dados que a ANTT aceita em teste.
    |
    */
    'efrete' => [
        'url' => 'https://dev.efrete.com.br',
        'connect_timeout' => 10,
        'timeout' => 45,
        'consultas' => 3,
        'espera_consulta_ms' => 1500,
        'massa_antt' => [
            'contratado' => '48384601000171',
            'rntrc' => '55887888',
            'placa_cavalo' => 'BWP6E54',
            'placa_carreta' => 'AFY2E46',
        ],
    ],

];
