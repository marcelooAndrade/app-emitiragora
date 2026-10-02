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
        //
        // Sem `after()`: a primeira versão pedia `after('situacao_comercial')`,
        // coluna que a migração de 15/09 já tinha removido. O SQLite dos testes
        // ignora `after()` e passou; o MySQL de produção recusou o deploy.
        Schema::table('tenants', function (Blueprint $table) {
            $table->json('perfil_cadastro')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('perfil_cadastro');
        });
    }
};
