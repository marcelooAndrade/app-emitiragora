<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Valores literais, não o enum: os casos `gratuito` e `avancado` saíram
     * do PlanoTenant em 03/10/2026, quando os planos passaram a ser os do
     * site. Ver 2026_10_03_100000_planos_do_site.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('plano', 20)->default('gratuito')->after('dominio');
        });

        // Quem já tem domínio próprio apontado está usufruindo do benefício, e
        // rebaixar em silêncio quebraria o acesso de um cliente em produção.
        DB::table('tenants')->whereNotNull('dominio')->update([
            'plano' => 'avancado',
        ]);
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('plano');
        });
    }
};
