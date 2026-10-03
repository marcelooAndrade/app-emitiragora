<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 | Os planos passam a ser os do site: Transporte e Pequena Empresa.
 |
 | Toda empresa que já existe vai para o Transporte, que libera tudo: ninguém
 | perde, na virada, um recurso que tinha ontem. Quem fica no Pequena
 | Empresa é decidido pela cobrança, não pela migração.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('tenants')->whereIn('plano', ['gratuito', 'avancado'])->update(['plano' => 'transporte']);
    }

    public function down(): void
    {
        DB::table('tenants')->update(['plano' => 'gratuito']);
    }
};
