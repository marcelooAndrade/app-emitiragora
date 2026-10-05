<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/*
 | Em 05/10/2026 o EmitirAgora passou a ser só de transportadora. Sai o que
 | era de empresa sem frota: emissão de NF-e e NFS-e, estoque, produtos,
 | regras fiscais da NF-e, importação de nota de compra, as tabelas de
 | referência que só a NF-e usava (NCM, CFOP, CEST, CST, unidades) e o plano
 | Pequena Empresa. A NF-e da carga, lida do XML para o CT-e, continua: ela
 | mora em `viagem_notas`, que é do transporte.
 |
 | Produção ainda não tinha cliente quando isto rodou. As migrations que
 | criaram essas tabelas ficam como história, como as da área administrativa
 | removida em 15/09.
 */
return new class extends Migration
{
    private const TABELAS = [
        'nota_arquivos', 'nota_duplicatas', 'nota_eventos', 'nota_itens',
        'nota_pagamentos', 'nota_referencias', 'nota_volumes', 'notas',
        'nota_entrada_itens', 'notas_entrada', 'inutilizacoes',
        'notas_servico', 'servicos_nfse', 'emitente_nfse', 'emitente_series',
        'estoque_movimentos', 'estoque_saldos', 'produto_fornecedor', 'produtos',
        'naturezas_operacao', 'perfil_fiscal_regras', 'perfis_fiscais',
        'ncms', 'cfops', 'cests', 'cfop_entrada_saida', 'classificacoes_tributarias',
        'codigos_situacao_tributaria', 'unidades_medida', 'meios_pagamento',
    ];

    private const PERMISSOES = [
        'produto.ver', 'produto.gerenciar', 'tributacao.gerenciar',
        'estoque.ver', 'estoque.movimentar', 'estoque.inventariar',
        'nota.ver', 'nota.criar', 'nota.emitir', 'nota.cancelar',
        'nota.inutilizar', 'nota.carta-correcao',
        'importacao.ver', 'importacao.processar',
        'nfse.ver', 'nfse.emitir', 'nfse.cancelar', 'nfse.configurar',
    ];

    public function up(): void
    {
        // O log da SEFAZ fica (CT-e e MDF-e gravam nele); só perde o vínculo
        // com a nota, antes de a tabela de notas sumir.
        Schema::table('sefaz_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('nota_id');
        });

        Schema::withoutForeignKeyConstraints(function () {
            foreach (self::TABELAS as $tabela) {
                Schema::dropIfExists($tabela);
            }
        });

        // Padrões da NF-e no emitente (série, crédito do Simples, informação
        // complementar, autXML) e o saldo negativo do estoque.
        Schema::table('emitentes', function (Blueprint $table) {
            $table->dropColumn([
                'serie_padrao', 'aliquota_credito_simples', 'info_complementares_padrao',
                'autxml_documento', 'permite_saldo_negativo',
            ]);
        });

        DB::table('tenants')->where('plano', 'pequena_empresa')->update(['plano' => 'transporte']);

        $permissoes = DB::table('permissions')->whereIn('name', self::PERMISSOES)->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $permissoes)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $permissoes)->delete();
        DB::table('permissions')->whereIn('id', $permissoes)->delete();

        // Quem tinha o perfil Estoque fica sem ele; o administrador da
        // empresa escolhe outro em Usuários.
        $estoque = DB::table('roles')->where('name', 'Estoque')->pluck('id');
        DB::table('role_has_permissions')->whereIn('role_id', $estoque)->delete();
        DB::table('model_has_roles')->whereIn('role_id', $estoque)->delete();
        DB::table('roles')->whereIn('id', $estoque)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Não volta: NF-e, estoque e produtos saíram do código junto com as
     * tabelas, e recriar tabela vazia sem o código que a usa não serve a
     * ninguém.
     */
    public function down(): void {}
};
