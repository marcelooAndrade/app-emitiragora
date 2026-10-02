<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | CIOT automático pelo e-Frete, portado do app-transm. O e-Frete pede mais
 | do que o MDF-e: endereço e nascimento do motorista, endereço do
 | proprietário, chassi e eixos do veículo. Tudo opcional, só cobrado de
 | quem liga a integração.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emitente_transporte', function (Blueprint $table) {
            // Cifradas pelo cast `encrypted` do model.
            $table->text('efrete_usuario')->nullable();
            $table->text('efrete_senha')->nullable();
            $table->text('efrete_integrador')->nullable();
            $table->boolean('efrete_massa_antt')->default(false);
        });

        Schema::table('motoristas', function (Blueprint $table) {
            $table->date('nascimento')->nullable();
            $table->char('cep', 8)->nullable();
            $table->char('municipio_codigo', 7)->nullable();
            $table->string('logradouro', 60)->nullable();
            $table->string('numero', 10)->nullable();
            $table->string('complemento', 60)->nullable();
            $table->string('bairro', 60)->nullable();
        });

        Schema::table('veiculos', function (Blueprint $table) {
            $table->string('chassi', 17)->nullable();
            $table->unsignedTinyInteger('eixos')->nullable();
            $table->char('proprietario_cep', 8)->nullable();
            $table->char('proprietario_municipio_codigo', 7)->nullable();
            $table->string('proprietario_logradouro', 60)->nullable();
            $table->string('proprietario_numero', 10)->nullable();
            $table->string('proprietario_complemento', 60)->nullable();
            $table->string('proprietario_bairro', 60)->nullable();
        });

        Schema::table('contratos_frete', function (Blueprint $table) {
            $table->char('ciot_verificador', 4)->nullable();
            $table->string('ciot_status', 20)->nullable(); // processando | registrado | encerrado
            $table->string('ciot_protocolo', 60)->nullable();
            $table->string('ciot_pdf_path')->nullable();
            $table->unsignedInteger('ciot_distancia_km')->nullable();
            $table->unsignedTinyInteger('ciot_tipo_carga')->default(5);
            $table->string('ciot_embalagem', 20)->nullable();
            $table->date('ciot_fim_previsto')->nullable();
            $table->json('ciot_resposta')->nullable();
            $table->timestamp('ciot_emitido_em')->nullable();
            $table->timestamp('ciot_encerrado_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contratos_frete', function (Blueprint $table) {
            $table->dropColumn(['ciot_verificador', 'ciot_status', 'ciot_protocolo', 'ciot_pdf_path', 'ciot_distancia_km',
                'ciot_tipo_carga', 'ciot_embalagem', 'ciot_fim_previsto', 'ciot_resposta', 'ciot_emitido_em', 'ciot_encerrado_em']);
        });
        Schema::table('veiculos', function (Blueprint $table) {
            $table->dropColumn(['chassi', 'eixos', 'proprietario_cep', 'proprietario_municipio_codigo', 'proprietario_logradouro',
                'proprietario_numero', 'proprietario_complemento', 'proprietario_bairro']);
        });
        Schema::table('motoristas', function (Blueprint $table) {
            $table->dropColumn(['nascimento', 'cep', 'municipio_codigo', 'logradouro', 'numero', 'complemento', 'bairro']);
        });
        Schema::table('emitente_transporte', function (Blueprint $table) {
            $table->dropColumn(['efrete_usuario', 'efrete_senha', 'efrete_integrador', 'efrete_massa_antt']);
        });
    }
};
