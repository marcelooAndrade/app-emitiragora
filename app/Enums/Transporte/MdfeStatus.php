<?php

namespace App\Enums\Transporte;

/**
 * Ciclo de vida do MDF-e. Diferente do CT-e, o MDF-e autorizado ainda tem
 * um passo: ser encerrado quando a carga chega. MDF-e aberto há mais de 30
 * dias trava a emissão de outro para o mesmo veículo na SEFAZ.
 */
enum MdfeStatus: string
{
    case Rascunho = 'rascunho';
    case EmProcessamento = 'em_processamento';
    case Autorizado = 'autorizado';
    case Rejeitado = 'rejeitado';
    case Encerrado = 'encerrado';
    case Cancelado = 'cancelado';

    public function rotulo(): string
    {
        return match ($this) {
            self::Rascunho => 'Rascunho',
            self::EmProcessamento => 'Em processamento',
            self::Autorizado => 'Em viagem',
            self::Rejeitado => 'Rejeitado',
            self::Encerrado => 'Encerrado',
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
            self::Encerrado => 'bg-success-700 text-white',
            self::Cancelado => 'bg-graphite-800 text-white',
        };
    }

    public function transmissivel(): bool
    {
        return in_array($this, [self::Rascunho, self::Rejeitado], true);
    }
}
