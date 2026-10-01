<?php

/*
|--------------------------------------------------------------------------
| Planos, comissões e pendências comerciais
|--------------------------------------------------------------------------
|
| Fonte única: a seção de Planos (home e página do contador), a página
| /contadores e o simulador de comissão leem tudo daqui. Mudar preço ou
| porcentagem aqui atualiza as três telas de uma vez.
|
| Itens ainda sem decisão ficam como string vazia ou array vazio aqui, nunca
| escritos direto numa view. A view mostra um aviso de pendência quando o
| campo estiver vazio.
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Planos
    |--------------------------------------------------------------------------
    |
    | `notasPorMes` é o limite mostrado ao cliente. `destaque` marca o plano
    | recomendado nos cartões.
    */
    'planos' => [
        [
            'slug' => 'gratis',
            'nome' => 'Grátis',
            'precoCentavos' => 0,
            'notasPorMes' => 10,
            'nfse' => false,
            'financeiro' => null,
            'dre' => false,
            'destaque' => false,
            'recursos' => [
                'Emissão de NF-e (nota de produto)',
                'Até 10 notas por mês',
            ],
        ],
        [
            'slug' => 'essencial',
            'nome' => 'Essencial',
            'precoCentavos' => 4_900,
            'notasPorMes' => 30,
            'nfse' => true,
            'financeiro' => 'basico',
            'dre' => false,
            'destaque' => true,
            'recursos' => [
                'NF-e e NFS-e',
                'Até 30 notas por mês',
                'Financeiro básico',
            ],
        ],
        [
            'slug' => 'completo',
            'nome' => 'Completo',
            'precoCentavos' => 9_900,
            'notasPorMes' => 90,
            'nfse' => true,
            'financeiro' => 'completo',
            'dre' => true,
            'destaque' => false,
            'recursos' => [
                'Tudo do Essencial',
                'Até 90 notas por mês',
                'DRE',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Acesso do contador
    |--------------------------------------------------------------------------
    |
    | true: aparece como benefício nos planos Essencial e Completo.
    | false: aparece só no Completo.
    */
    'acessoDoContadorEmTodosOsPlanosPagos' => true,

    /*
    |--------------------------------------------------------------------------
    | NFS-e por cidade
    |--------------------------------------------------------------------------
    |
    | NFS-e só funciona nas cidades já homologadas. Lista vazia faz a tela
    | mostrar "lista de cidades em breve" em vez do texto fixo.
    */
    'nfse' => [
        'cidades' => ['Araras'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Comissão do contador parceiro
    |--------------------------------------------------------------------------
    |
    | `ate` é o teto de clientes ativos do nível (null no último nível, sem
    | teto). O percentual vale para todos os clientes ativos do contador,
    | não só para quem passou do teto anterior.
    */
    'comissao' => [
        'niveis' => [
            ['nome' => 'Parceiro Inicial', 'de' => 1, 'ate' => 10, 'percentual' => 20],
            ['nome' => 'Parceiro Plus', 'de' => 11, 'ate' => 25, 'percentual' => 25],
            ['nome' => 'Parceiro Premium', 'de' => 26, 'ate' => null, 'percentual' => 30],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pendências comerciais
    |--------------------------------------------------------------------------
    |
    | Em branco até existir decisão ou canal real. A view mostra aviso de
    | pendência enquanto estiver vazio, nunca inventa o valor.
    */
    'pendencias' => [
        'diaPagamentoComissao' => '',
        'canalSuporteContador' => '',
        'linkAssinatura' => '',
        'linkCadastroParceiro' => '',
    ],

];
