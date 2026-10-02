<?php

namespace App\Enums\Transporte;

/**
 * Ciclo de vida do CT-e. Mesma linguagem visual da NF-e (ver NFeStatus):
 * em curso usa tonalidade, terminal usa preenchimento sólido.
 */
enum CteStatus: string
{
    case Rascunho = 'rascunho';
    case EmProcessamento = 'em_processamento';
    case Autorizado = 'autorizado';
    case Rejeitado = 'rejeitado';
    case Denegado = 'denegado';
    case Cancelado = 'cancelado';

    public function rotulo(): string
    {
        return match ($this) {
            self::Rascunho => 'Rascunho',
            self::EmProcessamento => 'Em processamento',
            self::Autorizado => 'Autorizado',
            self::Rejeitado => 'Rejeitado',
            self::Denegado => 'Denegado',
            self::Cancelado => 'Cancelado',
        };
    }

    public function classesBadge(): string
    {
        return match ($this) {
            self::Rascunho => 'bg-graphite-100 text-graphite-800',
            self::EmProcessamento => 'bg-steel-100 text-steel-800',
            self::Autorizado => 'bg-success-100 text-success-800',
            self::Rejeitado => 'bg-danger-100 text-danger-800',
            self::Denegado => 'bg-danger-800 text-white',
            self::Cancelado => 'bg-graphite-800 text-white',
        };
    }

    /** Pode ir (ou voltar) para a SEFAZ: o operador corrige e retransmite. */
    public function transmissivel(): bool
    {
        return in_array($this, [self::Rascunho, self::Rejeitado], true);
    }

    public function terminal(): bool
    {
        return in_array($this, [self::Denegado, self::Cancelado], true);
    }
}
