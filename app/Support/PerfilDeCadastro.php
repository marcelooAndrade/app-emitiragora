<?php

namespace App\Support;

/**
 * As duas perguntas que o modal do site faz antes de mandar a pessoa pro
 * cadastro: tamanho da frota e volume de CT-e e MDF-e por mês. A pergunta do
 * tipo de empresa saiu em 05/10/2026, quando o EmitirAgora passou a ser só
 * de transportadora; resposta antiga com `tipo` é descartada pelo filtro.
 *
 * O site (site-emitiragora, src/lib/cadastro.ts) usa exatamente estas
 * chaves. Mudar uma aqui sem mudar lá faz a resposta ser descartada em
 * silêncio, porque o filtro abaixo só guarda o que está na lista.
 *
 * Resposta é qualificação do lead, nunca condição do cadastro: valor fora
 * da lista é ignorado em vez de virar erro de validação, porque chega por
 * campo escondido e a pessoa não teria como corrigir.
 */
class PerfilDeCadastro
{
    /** @var array<string, array<string, string>> */
    public const OPCOES = [
        'frota' => [
            'sem-frota' => 'Só com agregados e terceiros',
            '1-5' => '1 a 5 veículos',
            '6-20' => '6 a 20 veículos',
            '21-50' => '21 a 50 veículos',
            'mais-de-50' => 'Mais de 50 veículos',
        ],
        'volume' => [
            'ate-10' => 'Até 10 por mês',
            '11-30' => '11 a 30 por mês',
            '31-90' => '31 a 90 por mês',
            'mais-de-90' => 'Mais de 90 por mês',
        ],
    ];

    /**
     * Respostas válidas vindas do formulário de cadastro, nos campos
     * `perfil_frota` e `perfil_volume`. Nulo quando nenhuma
     * veio, pra quem se cadastra direto pelo app não ganhar um `{}` vazio.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string>|null
     */
    public static function doCadastro(array $input): ?array
    {
        $respostas = [];

        foreach (self::OPCOES as $pergunta => $opcoes) {
            $valor = $input["perfil_{$pergunta}"] ?? null;

            if (is_string($valor) && array_key_exists($valor, $opcoes)) {
                $respostas[$pergunta] = $valor;
            }
        }

        return $respostas === [] ? null : $respostas;
    }
}
