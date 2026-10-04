<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Cobrança da assinatura com fatura e Pix.
 |
 | `tenants.pago_ate`: até que dia a empresa está paga. Cada parcela paga de
 | uma fatura marcada como mensalidade empurra a data um mês.
 | `faturas.mensalidade`: só a empresa que cobra o EmitirAgora vê a opção, e
 | é ela que diz que a fatura é de mensalidade, e não de outro serviço.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->date('pago_ate')->nullable()->after('teste_ate');
        });

        Schema::table('faturas', function (Blueprint $table) {
            $table->boolean('mensalidade')->default(false)->after('titulo');
        });
    }

    public function down(): void
    {
        Schema::table('faturas', function (Blueprint $table) {
            $table->dropColumn('mensalidade');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('pago_ate');
        });
    }
};
