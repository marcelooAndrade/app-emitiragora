<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Em 12/10/2026 o site entrou em stand-by e o rastreamento do Meta saiu
 | inteiro: pixel, Lead e CompleteRegistration, no navegador e pela
 | Conversions API. Os cookies do pixel que o cadastro guardava (fbp, fbc)
 | só serviam a isso.
 |
 | Para religar, o código está no histórico do git (commits de 05 a
 | 08/10/2026: "marketing: Lead pela Conversions API" e o pixel do site).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cadastros_iniciados', function (Blueprint $table) {
            $table->dropColumn(['fbp', 'fbc']);
        });
    }

    public function down(): void
    {
        Schema::table('cadastros_iniciados', function (Blueprint $table) {
            $table->string('fbp', 255)->nullable()->after('origem');
            $table->string('fbc', 255)->nullable()->after('fbp');
        });
    }
};
