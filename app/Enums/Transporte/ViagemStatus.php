<?php

namespace App\Enums\Transporte;

/**
 * Onde a viagem está, do ponto de vista de quem opera. É derivado dos
 * documentos (ver Viagem::recalcularStatus), nunca escolhido à mão: assim a
 * lista nunca diz "emitida" para uma viagem com CT-e rejeitado.
 */
enum ViagemStatus: string
{
    case Rascunho = 'rascunho';
    case Pendente = 'pendente';
    case Emitida = 'emitida';
    case Encerrada = 'encerrada';
    case Cancelada = 'cancelada';

    public function rotulo(): string
    {
        return match ($this) {
            self::Rascunho => 'Montando',
            self::Pendente => 'Com pendência',
            self::Emitida => 'Em viagem',
            self::Encerrada => 'Encerrada',
            self::Cancelada => 'Cancelada',
        };
    }

    public function classesBadge(): string
    {
        return match ($this) {
            self::Rascunho => 'bg-graphite-100 text-graphite-800',
            self::Pendente => 'bg-danger-100 text-danger-800',
            self::Emitida => 'bg-success-100 text-success-800',
            self::Encerrada => 'bg-success-700 text-white',
            self::Cancelada => 'bg-graphite-800 text-white',
        };
    }
}
