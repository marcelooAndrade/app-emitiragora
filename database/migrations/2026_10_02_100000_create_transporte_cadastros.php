<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Cadastros do módulo de transporte, portado do app-transm.
 |
 | No Transm a empresa era uma só e tinha uma tabela de configuração fiscal
 | própria. Aqui a empresa é o `Emitente` que já existe (CNPJ, endereço,
 | certificado), e o que é só de transporte fica numa tabela 1:1 ao lado, do
 | mesmo jeito que `emitente_nfse`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emitente_transporte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emitente_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('rntrc', 8)->nullable();
            $table->unsignedSmallInteger('cte_serie')->default(1);
            $table->unsignedSmallInteger('mdfe_serie')->default(1);
            // CFOP da operação dentro do estado. Fora dele o primeiro dígito
            // vira 6 sozinho, então não existe um segundo campo para errar.
            $table->string('cfop', 4)->default('5353');
            $table->string('natureza_operacao', 60)->default('PRESTACAO DE SERVICO DE TRANSPORTE');
            // 1 = prestador de serviço de transporte, 2 = carga própria.
            $table->char('tipo_emitente_mdfe', 1)->default('1');
            $table->string('seguradora_nome', 30)->nullable();
            $table->string('seguradora_cnpj', 14)->nullable();
            $table->string('apolice', 20)->nullable();
            // 1 = emitente do MDF-e, 2 = contratante do serviço.
            $table->char('responsavel_seguro', 1)->default('1');
            $table->unsignedSmallInteger('prazo_fatura_dias')->default(30);
            $table->timestamps();
        });

        Schema::create('veiculos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emitente_id')->constrained()->cascadeOnDelete();
            $table->string('tipo', 10); // tracao | reboque
            $table->string('placa', 7);
            $table->string('renavam', 11)->nullable();
            $table->char('uf', 2);
            $table->unsignedInteger('tara_kg');
            $table->unsignedInteger('capacidade_kg')->nullable();
            $table->unsignedSmallInteger('capacidade_m3')->nullable();
            $table->char('tipo_rodado', 2)->nullable();
            $table->char('tipo_carroceria', 2);
            $table->string('proprietario_tipo', 10)->default('proprio'); // proprio | terceiro
            $table->string('proprietario_documento', 14)->nullable();
            $table->string('proprietario_nome', 60)->nullable();
            $table->string('proprietario_rntrc', 8)->nullable();
            $table->string('proprietario_ie', 20)->nullable();
            $table->char('proprietario_uf', 2)->nullable();
            // 0 = TAC agregado, 1 = TAC independente, 2 = outros.
            $table->char('proprietario_tp', 1)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['emitente_id', 'placa']);
        });

        Schema::create('motoristas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emitente_id')->constrained()->cascadeOnDelete();
            $table->string('nome', 60);
            $table->char('cpf', 11);
            $table->string('cnh', 20)->nullable();
            $table->string('telefone', 20)->nullable();
            $table->string('chave_pix', 77)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['emitente_id', 'cpf']);
        });

        Schema::create('regras_icms_transporte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emitente_id')->constrained()->cascadeOnDelete();
            $table->string('nome', 80);
            $table->char('uf_origem', 2)->nullable();
            $table->char('uf_destino', 2)->nullable();
            $table->string('cst', 2); // 00, 20, 40, 41, 51, 90 ou SN
            $table->decimal('aliquota', 7, 4)->default(0);
            $table->decimal('reducao_base', 7, 4)->default(0);
            $table->decimal('percentual_credito', 7, 4)->default(0);
            // UFs intermediárias do percurso, na ordem da viagem, para o MDF-e.
            $table->json('percurso_ufs')->nullable();
            $table->unsignedSmallInteger('prioridade')->default(100);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['emitente_id', 'ativo']);
        });

        // Numeração de CT-e (57), MDF-e (58) e da própria viagem (0). Tabela
        // separada de `emitente_series`, que é só da NF-e e não tem modelo.
        Schema::create('transporte_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emitente_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('modelo');
            $table->unsignedSmallInteger('serie');
            $table->unsignedInteger('proximo_numero')->default(1);
            $table->timestamps();

            $table->unique(['emitente_id', 'modelo', 'serie']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transporte_series');
        Schema::dropIfExists('regras_icms_transporte');
        Schema::dropIfExists('motoristas');
        Schema::dropIfExists('veiculos');
        Schema::dropIfExists('emitente_transporte');
    }
};
