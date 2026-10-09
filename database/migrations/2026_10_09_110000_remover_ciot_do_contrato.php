<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | O CIOT saiu do contrato do frete para `ciots`, e as credenciais do e-Frete
 | para `emitente_ciot` (2026_10_09_100000, que copiou o que havia). Aqui
 | saem as colunas antigas, que o código não lê mais. Não volta: recriar as
 | colunas vazias não traria os dados de volta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos_frete', function (Blueprint $table) {
            $table->dropColumn(['ciot', 'ciot_verificador', 'ciot_status', 'ciot_protocolo', 'ciot_pdf_path', 'ciot_distancia_km',
                'ciot_tipo_carga', 'ciot_embalagem', 'ciot_fim_previsto', 'ciot_resposta', 'ciot_emitido_em', 'ciot_encerrado_em']);
        });

        Schema::table('emitente_transporte', function (Blueprint $table) {
            $table->dropColumn(['efrete_usuario', 'efrete_senha', 'efrete_integrador', 'efrete_massa_antt']);
        });
    }

    public function down(): void {}
};
