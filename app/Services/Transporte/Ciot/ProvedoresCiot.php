<?php

namespace App\Services\Transporte\Ciot;

use App\Services\Transporte\TransporteException;

/** As empresas de CIOT de `config/ciot.php`, lidas num lugar só. */
class ProvedoresCiot
{
    /** @return array<string, array{classe: string, nome: string, descricao: string, producao: bool}> */
    public function todos(): array
    {
        return (array) config('ciot.provedores', []);
    }

    public function existe(string $chave): bool
    {
        return isset($this->todos()[$chave]);
    }

    public function nome(string $chave): string
    {
        return (string) ($this->todos()[$chave]['nome'] ?? $chave);
    }

    /** O DCS só admite produção depois de teste em homologação. */
    public function liberadoEmProducao(string $chave): bool
    {
        return (bool) ($this->todos()[$chave]['producao'] ?? false);
    }

    public function gateway(string $chave): GatewayCiot
    {
        $classe = $this->todos()[$chave]['classe'] ?? null;
        if ($classe === null) {
            throw new TransporteException("A empresa de CIOT \"{$chave}\" não está disponível. Escolha outra em Configurações, CIOT.");
        }

        return app($classe);
    }
}
