<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Frete com veículo de terceiro (contrato do motorista, CIOT, pagamento) e
 | averbação do seguro da carga pela AT&M. Portados do app-transm.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emitente_transporte', function (Blueprint $table) {
            // Credenciais da AT&M. Cifradas pelo cast `encrypted` do model:
            // text, porque o texto cifrado é bem maior que o original.
            $table->text('atm_usuario')->nullable();
            $table->text('atm_senha')->nullable();
            $table->text('atm_codigo')->nullable();
            $table->unsignedTinyInteger('adiantamento_percentual')->default(80);
        });

        Schema::table('ctes', function (Blueprint $table) {
            $table->string('averbacao_status', 20)->nullable(); // aprovada | recusada
            $table->string('averbacao_protocolo', 100)->nullable();
            $table->string('averbacao_numero', 40)->nullable();
            $table->text('averbacao_mensagem')->nullable();
        });

        Schema::create('contratos_frete', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emitente_id')->constrained()->cascadeOnDelete();
            $table->foreignId('viagem_id')->unique()->constrained('viagens')->cascadeOnDelete();
            $table->string('numero', 30);
            $table->string('status', 20)->default('ativo'); // ativo | cancelado
            $table->string('contratado_documento', 14);
            $table->string('contratado_nome', 60);
            $table->string('contratado_rntrc', 8);
            $table->char('contratado_tp', 1)->nullable();
            $table->string('motorista_nome', 60);
            $table->char('motorista_cpf', 11);
            $table->string('placas', 40);
            $table->bigInteger('frete_centavos');
            $table->bigInteger('adiantamento_centavos')->default(0);
            $table->bigInteger('imposto_renda_centavos')->default(0);
            $table->bigInteger('falta_mercadoria_centavos')->default(0);
            $table->bigInteger('seguro_motorista_centavos')->default(0);
            $table->bigInteger('seguro_carga_centavos')->default(0);
            $table->bigInteger('saldo_centavos')->default(0);
            $table->date('vencimento_saldo');
            $table->string('forma_pagamento', 20)->default('pix'); // pix | transferencia
            $table->string('chave_pix', 77)->nullable();
            $table->string('banco_codigo', 3)->nullable();
            $table->string('agencia', 10)->nullable();
            $table->string('conta', 20)->nullable();
            $table->string('ciot', 12)->nullable();
            $table->foreignId('conta_pagar_adiantamento_id')->nullable()->constrained('contas_pagar')->nullOnDelete();
            $table->foreignId('conta_pagar_saldo_id')->nullable()->constrained('contas_pagar')->nullOnDelete();
            $table->timestamp('emitido_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratos_frete');
        Schema::table('ctes', function (Blueprint $table) {
            $table->dropColumn(['averbacao_status', 'averbacao_protocolo', 'averbacao_numero', 'averbacao_mensagem']);
        });
        Schema::table('emitente_transporte', function (Blueprint $table) {
            $table->dropColumn(['atm_usuario', 'atm_senha', 'atm_codigo', 'adiantamento_percentual']);
        });
    }
};
