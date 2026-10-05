<?php

namespace App\Enums;

/**
 * Perfis de acesso. O vínculo é por emitente, via teams do spatie/laravel-permission.
 */
enum Perfil: string
{
    case Administrador = 'Administrador';
    case Faturamento = 'Faturamento';
    case Contador = 'Contador';
    case Consulta = 'Consulta';

    public function descricao(): string
    {
        return match ($this) {
            self::Administrador => 'Acesso total, incluindo certificado, usuários e virada para produção.',
            self::Faturamento => 'Lança viagens, emite e cancela CT-e e MDF-e. Gerencia os clientes.',
            self::Contador => 'Exporta o pacote da contabilidade e cuida do financeiro. Não emite nem cancela documento.',
            self::Consulta => 'Somente leitura.',
        };
    }
}
