<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Respostas do modal do site (ver App\Support\PerfilDeCadastro).
        // Nulo para quem se cadastrou direto pelo app, sem passar pelo modal.
        Schema::table('tenants', function (Blueprint $table) {
            $table->json('perfil_cadastro')->nullable()->after('situacao_comercial');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('perfil_cadastro');
        });
    }
};
