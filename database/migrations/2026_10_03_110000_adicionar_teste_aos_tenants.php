<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Fim do teste grátis de 14 dias de quem se cadastra.
 |
 | Nulo para toda empresa que já existia antes do teste: elas não ganham um
 | prazo que nunca combinaram, e seguem como estão até a cobrança chegar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('teste_ate')->nullable()->after('plano');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('teste_ate');
        });
    }
};
