<?php

namespace App\Services\Transporte\Efrete;

/**
 * Leitura das respostas do e-Frete, que mudam de forma conforme o canal
 * (SOAP ou REST) e a versão: o número do CIOT pode vir em campos de nomes
 * diferentes, aninhado ou até dentro de um JSON em texto. Portado do
 * `FiscalCiotTransmissionService` do app-transm.
 */
class Ciot
{
    private const CAMPOS_NUMERO = [
        'codigoidentificacaooperacao',
        'codigoidentificacaooperacaotransporte',
        'codigooperacaotransporte',
        'numerociot',
        'ciot',
    ];

    /** Os 12 dígitos do CIOT, em qualquer canto da resposta. */
    public static function numeroEm(array $resposta): ?string
    {
        [$numero] = self::partes(self::valor($resposta, self::CAMPOS_NUMERO));
        if ($numero !== null) {
            return $numero;
        }

        foreach ($resposta as $chave => $candidato) {
            if (is_array($candidato)) {
                if (($numero = self::numeroEm($candidato)) !== null) {
                    return $numero;
                }

                continue;
            }
            if (is_string($candidato) && is_array($json = json_decode($candidato, true))) {
                if (($numero = self::numeroEm($json)) !== null) {
                    return $numero;
                }
            }
            $normalizada = self::chave($chave);
            if (str_contains($normalizada, 'codigoidentificacaooperacao') || in_array($normalizada, ['numerociot', 'ciot'], true)) {
                [$numero] = self::partes($candidato);
                if ($numero !== null) {
                    return $numero;
                }
            }
        }

        return null;
    }

    /** Os 4 dígitos do verificador ("123456789012/1234"). */
    public static function verificadorEm(array $resposta): ?string
    {
        $verificador = preg_replace('/\D/', '', (string) self::valor($resposta, ['codigoverificador', 'codigoverificadoroperacao']));
        if (strlen($verificador) === 4) {
            return $verificador;
        }
        [, $verificador] = self::partes(self::valor($resposta, self::CAMPOS_NUMERO));

        return $verificador;
    }

    public static function protocoloEm(array $resposta): ?string
    {
        $protocolo = trim((string) self::valor($resposta, ['protocoloservico', 'protocolo']));

        return $protocolo !== '' ? mb_substr($protocolo, 0, 60) : null;
    }

    /** Nunca guardar token, senha, usuário nem integrador. */
    public static function semSegredos(array $resposta): array
    {
        foreach ($resposta as $chave => $valor) {
            if (in_array(mb_strtolower((string) $chave), ['token', 'senha', 'password', 'integrador', 'usuario'], true)) {
                unset($resposta[$chave]);

                continue;
            }
            if (is_array($valor)) {
                $resposta[$chave] = self::semSegredos($valor);
            }
        }

        return $resposta;
    }

    private static function valor(array $resposta, array $chaves): mixed
    {
        foreach ($resposta as $chave => $valor) {
            if (in_array(self::chave($chave), $chaves, true) && ! is_array($valor)) {
                return $valor;
            }
        }
        foreach ($resposta as $valor) {
            if (is_array($valor) && ($achado = self::valor($valor, $chaves)) !== null && $achado !== '') {
                return $achado;
            }
        }

        return null;
    }

    /** @return array{0: ?string, 1: ?string} */
    private static function partes(mixed $valor): array
    {
        if (! is_scalar($valor) || preg_match('/(?<!\d)(\d{12})(?:\s*\/\s*(\d{4}))?(?!\d)/', trim((string) $valor), $m) !== 1) {
            return [null, null];
        }

        return [$m[1], $m[2] ?? null];
    }

    private static function chave(int|string $chave): string
    {
        return mb_strtolower((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $chave));
    }
}
