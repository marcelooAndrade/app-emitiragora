<?php

namespace App\Support;

/**
 * Escreve um valor em reais por extenso, como pede o recibo do contrato de
 * frete ("Recebi a importância de R$ 1.000,00 (MIL REAIS)"). Portado do
 * app-transm, recebendo centavos como o resto do EmitirAgora.
 */
class ValorPorExtenso
{
    private const UNIDADES = [
        '', 'UM', 'DOIS', 'TRÊS', 'QUATRO', 'CINCO', 'SEIS', 'SETE', 'OITO', 'NOVE',
        'DEZ', 'ONZE', 'DOZE', 'TREZE', 'QUATORZE', 'QUINZE', 'DEZESSEIS', 'DEZESSETE',
        'DEZOITO', 'DEZENOVE',
    ];

    private const DEZENAS = [
        '', '', 'VINTE', 'TRINTA', 'QUARENTA', 'CINQUENTA', 'SESSENTA', 'SETENTA',
        'OITENTA', 'NOVENTA',
    ];

    private const CENTENAS = [
        '', 'CENTO', 'DUZENTOS', 'TREZENTOS', 'QUATROCENTOS', 'QUINHENTOS',
        'SEISCENTOS', 'SETECENTOS', 'OITOCENTOS', 'NOVECENTOS',
    ];

    public static function reais(int $centavos): string
    {
        $inteiros = intdiv(abs($centavos), 100);
        $resto = abs($centavos) % 100;

        $partes = [];
        if ($inteiros > 0) {
            $escrito = self::numero($inteiros);
            // "UM MILHÃO DE REAIS", mas "UM MILHÃO E QUINHENTOS MIL REAIS": o
            // "de" só entra quando o número termina na escala.
            $moeda = preg_match('/(MILHÃO|MILHÕES|BILHÃO|BILHÕES)$/u', $escrito)
                ? 'DE REAIS'
                : ($inteiros === 1 ? 'REAL' : 'REAIS');
            $partes[] = $escrito.' '.$moeda;
        }
        if ($resto > 0) {
            $partes[] = self::numero($resto).' '.($resto === 1 ? 'CENTAVO' : 'CENTAVOS');
        }

        return $partes === [] ? 'ZERO REAIS' : implode(' E ', $partes);
    }

    private static function numero(int $numero): string
    {
        if ($numero < 20) {
            return self::UNIDADES[$numero];
        }

        if ($numero < 100) {
            $unidade = $numero % 10;

            return self::DEZENAS[intdiv($numero, 10)].($unidade > 0 ? ' E '.self::UNIDADES[$unidade] : '');
        }

        if ($numero === 100) {
            return 'CEM';
        }

        if ($numero < 1000) {
            $resto = $numero % 100;

            return self::CENTENAS[intdiv($numero, 100)].($resto > 0 ? ' E '.self::numero($resto) : '');
        }

        foreach ([1_000_000_000 => ['BILHÃO', 'BILHÕES'], 1_000_000 => ['MILHÃO', 'MILHÕES'], 1000 => ['MIL', 'MIL']] as $escala => [$singular, $plural]) {
            if ($numero < $escala) {
                continue;
            }

            $quantidade = intdiv($numero, $escala);
            $resto = $numero % $escala;
            $prefixo = $escala === 1000 && $quantidade === 1
                ? 'MIL'
                : self::numero($quantidade).' '.($quantidade === 1 ? $singular : $plural);

            if ($resto === 0) {
                return $prefixo;
            }

            // "MIL E QUINHENTOS", mas "MIL DUZENTOS E CINQUENTA": o E só entra
            // quando o resto é uma centena redonda ou menor que cem.
            return $prefixo.(($resto < 100 || $resto % 100 === 0) ? ' E ' : ' ').self::numero($resto);
        }

        return (string) $numero;
    }
}
