<?php

namespace Database\Seeders;

use App\Enums\Perfil;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PerfilSeeder extends Seeder
{
    /**
     * Permissões do sistema. O sufixo indica o nível:
     * ".ver" é leitura, os demais são escrita ou ação.
     */
    public const PERMISSOES = [
        'emitente.ver', 'emitente.gerenciar', 'emitente.ativar-producao',
        'certificado.ver', 'certificado.gerenciar',
        'usuario.gerenciar', 'auditoria.ver',
        'pessoa.ver', 'pessoa.gerenciar',
        'relatorio.ver', 'contador.exportar',
        'financeiro.ver', 'financeiro.gerenciar',
        'transporte.ver', 'transporte.operar', 'transporte.cancelar', 'transporte.configurar',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSOES as $nome) {
            Permission::findOrCreate($nome, 'web');
        }

        $somenteLeitura = array_values(array_filter(
            self::PERMISSOES,
            fn (string $p): bool => str_ends_with($p, '.ver'),
        ));

        $mapa = [
            Perfil::Administrador->value => self::PERMISSOES,

            // Quem opera a viagem: lança a carga, emite CT-e e MDF-e,
            // cancela e cuida dos clientes. Vê o financeiro, não mexe nele.
            Perfil::Faturamento->value => [
                'emitente.ver', 'certificado.ver',
                'pessoa.ver', 'pessoa.gerenciar',
                'relatorio.ver', 'contador.exportar',
                'financeiro.ver',
                'transporte.ver', 'transporte.operar', 'transporte.cancelar',
            ],

            // O contador costuma ser externo à empresa. Leva os arquivos e
            // cuida do financeiro, mas não opera a viagem.
            Perfil::Contador->value => [
                'emitente.ver', 'pessoa.ver',
                'relatorio.ver', 'contador.exportar',
                'financeiro.ver', 'financeiro.gerenciar',
                'auditoria.ver',
                'transporte.ver',
            ],

            Perfil::Consulta->value => $somenteLeitura,
        ];

        foreach ($mapa as $perfil => $permissoes) {
            Role::findOrCreate($perfil, 'web')->syncPermissions($permissoes);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
