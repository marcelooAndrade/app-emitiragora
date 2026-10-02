<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | A viagem é a "ordem de faturamento" do Transm: uma carga, um motorista,
 | um veículo e as NF-e que vão no caminhão. Dela saem os CT-e, o MDF-e e a
 | fatura do cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('viagens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emitente_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('numero');
            $table->string('status', 20)->default('rascunho');
            $table->date('data_carregamento');
            $table->foreignId('motorista_id')->nullable()->constrained('motoristas')->nullOnDelete();
            $table->foreignId('veiculo_id')->nullable()->constrained('veiculos')->nullOnDelete();
            $table->foreignId('reboque_id')->nullable()->constrained('veiculos')->nullOnDelete();
            $table->foreignId('reboque2_id')->nullable()->constrained('veiculos')->nullOnDelete();
            $table->string('frete_modo', 12)->default('tonelada'); // tonelada | fechado
            $table->bigInteger('frete_tonelada_centavos')->default(0);
            $table->bigInteger('frete_fechado_centavos')->default(0);
            $table->bigInteger('pedagio_centavos')->default(0);
            $table->bigInteger('frete_motorista_centavos')->default(0);
            $table->bigInteger('adiantamento_centavos')->default(0);
            $table->text('observacoes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['emitente_id', 'numero']);
            $table->index(['emitente_id', 'status']);
        });

        Schema::create('viagem_notas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viagem_id')->constrained('viagens')->cascadeOnDelete();
            $table->char('chave', 44);
            $table->string('numero', 9);
            $table->string('serie', 3);
            $table->timestamp('emitida_em')->nullable();
            $table->json('remetente');
            $table->json('destinatario');
            $table->char('uf_origem', 2);
            $table->char('municipio_origem_codigo', 7);
            $table->char('uf_destino', 2);
            $table->char('municipio_destino_codigo', 7);
            $table->bigInteger('valor_centavos');
            $table->decimal('peso_kg', 12, 3)->default(0);
            $table->string('produto', 120)->nullable();
            $table->string('ncm', 8)->nullable();
            $table->char('mod_frete', 1)->nullable();
            $table->string('xml_path');
            $table->timestamps();

            $table->unique(['viagem_id', 'chave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viagem_notas');
        Schema::dropIfExists('viagens');
    }
};
