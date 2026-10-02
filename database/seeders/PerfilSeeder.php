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
        'produto.ver', 'produto.gerenciar', 'tributacao.gerenciar',
        'estoque.ver', 'estoque.movimentar', 'estoque.inventariar',
        'nota.ver', 'nota.criar', 'nota.emitir', 'nota.cancelar',
        'nota.inutilizar', 'nota.carta-correcao',
        'importacao.ver', 'importacao.processar',
        'relatorio.ver', 'contador.exportar',
        'financeiro.ver', 'financeiro.gerenciar',
        'nfse.ver', 'nfse.emitir', 'nfse.cancelar', 'nfse.configurar',
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

            Perfil::Faturamento->value => [
                'emitente.ver', 'certificado.ver',
                'pessoa.ver', 'pessoa.gerenciar',
                'produto.ver', 'estoque.ver',
                'nota.ver', 'nota.criar', 'nota.emitir', 'nota.cancelar',
                'nota.inutilizar', 'nota.carta-correcao',
                'importacao.ver', 'relatorio.ver', 'contador.exportar',
                'financeiro.ver',
                'nfse.ver', 'nfse.emitir', 'nfse.cancelar',
                'transporte.ver', 'transporte.operar', 'transporte.cancelar',
            ],

            Perfil::Estoque->value => [
                'emitente.ver',
                'produto.ver', 'produto.gerenciar',
                'estoque.ver', 'estoque.movimentar', 'estoque.inventariar',
                'importacao.ver', 'importacao.processar',
                'nota.ver', 'relatorio.ver',
            ],

            // O contador costuma ser externo à empresa. Escreve a regra
            // fiscal e leva os arquivos, mas não opera o faturamento.
            Perfil::Contador->value => [
                'emitente.ver', 'pessoa.ver', 'produto.ver', 'estoque.ver',
                'tributacao.gerenciar',
                'nota.ver', 'importacao.ver',
                'relatorio.ver', 'contador.exportar',
                'financeiro.ver', 'financeiro.gerenciar',
                'nfse.ver',
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
