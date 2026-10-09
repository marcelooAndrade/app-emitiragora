<?php

use App\Services\Transporte\Ciot\CiotEfreteProvedor;
use App\Services\Transporte\Ciot\CiotManual;

return [

    /*
    |--------------------------------------------------------------------------
    | Empresas que geram o CIOT
    |--------------------------------------------------------------------------
    |
    | Cada empresa é um adaptador de `GatewayCiot`. A página Configurações,
    | CIOT lista estas, e o emitente escolhe uma. Empresa nova entra aqui
    | depois que o adaptador dela existe.
    |
    | `producao`: o DCS da ANTT só admite produção depois de teste em
    | homologação. Quem testa o adaptador somos nós, uma vez, para todos os
    | clientes; até lá a produção fica bloqueada para aquela empresa.
    |
    */

    'provedores' => [
        'manual' => [
            'classe' => CiotManual::class,
            'nome' => 'Digitar o CIOT',
            'descricao' => 'Você gera o CIOT no programa gratuito da ANTT ou no portal da sua empresa de pagamento de frete, e digita o número na viagem.',
            'producao' => true,
        ],
        'efrete' => [
            'classe' => CiotEfreteProvedor::class,
            'nome' => 'e-Frete',
            'descricao' => 'O CIOT do motorista terceiro (TAC) sai sozinho ao emitir, pelo e-Frete.',
            'producao' => false,
        ],
    ],

    /** Previsão de entrega quando ninguém informa: carregamento mais estes dias. */
    'previsao_entrega_dias' => 3,

];
