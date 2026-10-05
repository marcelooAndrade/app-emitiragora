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
            'slug' => 'transporte',
            'nome' => 'Transporte',
            'precoCentavos' => 49_900,
            'descricao' => 'Pra transportadora que opera frota, própria ou de terceiros, e precisa dos documentos de transporte em dia.',
            'destaque' => true,
            'recursos' => [
                'CT-e e MDF-e',
                'CIOT',
                'Averbação de seguro',
                'Contrato do motorista',
                'Financeiro com DRE',
                'Pacote do mês pro contador',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Contato comercial
    |--------------------------------------------------------------------------
    |
    | O mesmo WhatsApp do site (site-emitiragora, src/lib/constantes.ts). É
    | para onde vai quem quer assinar, no aviso do teste grátis.
    */
    'whatsapp' => '5519971351777',

    /*
    |--------------------------------------------------------------------------
    | Cobrança da assinatura
    |--------------------------------------------------------------------------
    |
    | `cnpjEmissor`: o CNPJ da empresa que cobra o EmitirAgora dos clientes.
    | Só ela vê a opção "Mensalidade do EmitirAgora" nas faturas. Vazio
    | desliga a renovação automática inteira.
    | `diasDeTolerancia`: depois do "pago até", quantos dias a empresa ainda
    | emite antes de ficar só para consulta.
    */
    'cobranca' => [
        'cnpjEmissor' => env('EMITIRAGORA_CNPJ_COBRANCA', ''),
        'diasDeTolerancia' => 5,
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
