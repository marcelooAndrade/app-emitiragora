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
    | Os dois planos do site (site-emitiragora): o `slug` é o valor do caso
    | em App\Enums\PlanoTenant, que é quem decide o que cada plano libera.
    | Preço fixo por plano, decidido em 03/10/2026. `destaque` marca o plano
    | recomendado nos cartões. `recursos` é só o texto do cartão, na ordem em
    | que a tabela de comparação lista as linhas.
    */
    'planos' => [
        [
            'slug' => 'pequena_empresa',
            'nome' => 'Pequena Empresa',
            'precoCentavos' => 9_900,
            'descricao' => 'Pra empresa que compra, vende e emite nota, sem operar frota.',
            'destaque' => false,
            'recursos' => [
                'Nota fiscal de produto (NF-e)',
                'Nota fiscal de serviço (NFS-e)',
                'Financeiro com DRE',
            ],
        ],
        [
            'slug' => 'transporte',
            'nome' => 'Transporte',
            'precoCentavos' => 49_900,
            'descricao' => 'Pra transportadora que opera frota e precisa dos documentos de transporte, não só de nota fiscal.',
            'destaque' => true,
            'recursos' => [
                'Nota fiscal de produto (NF-e)',
                'Nota fiscal de serviço (NFS-e)',
                'Financeiro com DRE',
                'CT-e e MDF-e',
                'CIOT',
                'Averbação de seguro',
                'Contrato do motorista',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Acesso do contador
    |--------------------------------------------------------------------------
    |
    | true: aparece como benefício nos dois planos.
    | false: aparece só no Transporte.
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
        'linkCadastroParceiro' => '',
    ],

];
