<?php

namespace App\Services\Transporte\Ciot;

/**
 * O que a empresa do CIOT respondeu, já sem o formato dela.
 *
 * `situacao`: registrado, processando, recusado, cancelado, encerrado, ou
 * ok (teste de conexão). `resposta` é a resposta bruta já sem token, senha
 * e usuário; `pdf` é o comprovante em bytes, quando a empresa devolve.
 */
final class RespostaCiot
{
    /** @param  array<mixed>  $resposta */
    public function __construct(
        public readonly string $situacao,
        public readonly ?string $numero = null,
        public readonly ?string $verificador = null,
        public readonly ?string $protocolo = null,
        public readonly ?string $codigo = null,
        public readonly ?string $mensagem = null,
        public readonly ?string $aviso = null,
        public readonly array $resposta = [],
        public readonly ?string $pdf = null,
        public readonly ?string $responsavel = null,
    ) {}
}
