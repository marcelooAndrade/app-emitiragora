<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Documentos fiscais do transporte. O XML fica no disco `fiscal`, como o da
 | NF-e, e a tabela guarda só o caminho: XML autorizado é guarda legal e não
 | pertence a uma coluna que toda listagem carrega.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ctes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emitente_id')->constrained()->cascadeOnDelete();
            $table->foreignId('viagem_id')->constrained('viagens')->cascadeOnDelete();
            $table->string('status', 20)->default('rascunho');
            $table->string('ambiente', 12);
            $table->unsignedSmallInteger('serie');
            $table->unsignedInteger('numero')->nullable();
            $table->char('chave', 44)->nullable()->unique();
            $table->string('protocolo', 20)->nullable();
            $table->char('cfop', 4);
            // 0 remetente, 1 expedidor, 2 recebedor, 3 destinatário.
            $table->char('tomador_tipo', 1)->default('0');
            $table->foreignId('tomador_pessoa_id')->nullable()->constrained('pessoas')->nullOnDelete();
            $table->json('remetente');
            $table->json('destinatario');
            $table->char('municipio_inicio_codigo', 7);
            $table->string('municipio_inicio', 60);
            $table->char('uf_inicio', 2);
            $table->char('municipio_fim_codigo', 7);
            $table->string('municipio_fim', 60);
            $table->char('uf_fim', 2);
            $table->decimal('peso_kg', 12, 3)->default(0);
            $table->bigInteger('valor_carga_centavos')->default(0);
            $table->string('produto_predominante', 60)->nullable();
            $table->bigInteger('valor_frete_centavos')->default(0);
            $table->bigInteger('valor_pedagio_centavos')->default(0);
            $table->bigInteger('valor_total_centavos')->default(0);
            $table->foreignId('regra_icms_id')->nullable()->constrained('regras_icms_transporte')->nullOnDelete();
            $table->string('icms_cst', 2)->nullable();
            $table->bigInteger('icms_base_centavos')->default(0);
            $table->decimal('icms_aliquota', 7, 4)->default(0);
            $table->bigInteger('icms_valor_centavos')->default(0);
            $table->bigInteger('icms_credito_centavos')->default(0);
            $table->text('observacoes')->nullable();
            $table->string('xml_path')->nullable();
            $table->string('xml_autorizado_path')->nullable();
            $table->string('c_stat', 4)->nullable();
            $table->text('x_motivo')->nullable();
            $table->timestamp('emitido_em')->nullable();
            $table->timestamp('autorizado_em')->nullable();
            $table->timestamp('cancelado_em')->nullable();
            $table->string('protocolo_cancelamento', 20)->nullable();
            $table->foreignId('fatura_id')->nullable()->constrained('faturas')->nullOnDelete();
            $table->foreignId('transmitido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['emitente_id', 'status']);
        });

        Schema::table('viagem_notas', function (Blueprint $table) {
            $table->foreignId('cte_id')->nullable()->constrained('ctes')->nullOnDelete();
        });

        Schema::create('cte_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cte_id')->constrained('ctes')->cascadeOnDelete();
            $table->string('tipo', 20); // cancelamento | carta_correcao
            $table->unsignedTinyInteger('sequencia')->default(1);
            $table->text('descricao');
            $table->string('protocolo', 20)->nullable();
            $table->string('c_stat', 4)->nullable();
            $table->text('x_motivo')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('mdfes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emitente_id')->constrained()->cascadeOnDelete();
            $table->foreignId('viagem_id')->unique()->constrained('viagens')->cascadeOnDelete();
            $table->string('status', 20)->default('rascunho');
            $table->string('ambiente', 12);
            $table->unsignedSmallInteger('serie');
            $table->unsignedInteger('numero')->nullable();
            $table->char('chave', 44)->nullable()->unique();
            $table->string('protocolo', 20)->nullable();
            $table->char('uf_inicio', 2);
            $table->char('uf_fim', 2);
            $table->json('percurso_ufs')->nullable();
            $table->char('municipio_carregamento_codigo', 7);
            $table->string('municipio_carregamento', 60);
            $table->json('seguro')->nullable();
            $table->string('ciot', 12)->nullable();
            $table->string('xml_path')->nullable();
            $table->string('xml_autorizado_path')->nullable();
            $table->string('c_stat', 4)->nullable();
            $table->text('x_motivo')->nullable();
            $table->timestamp('emitido_em')->nullable();
            $table->timestamp('autorizado_em')->nullable();
            $table->timestamp('encerrado_em')->nullable();
            $table->string('protocolo_encerramento', 20)->nullable();
            $table->timestamp('cancelado_em')->nullable();
            $table->string('protocolo_cancelamento', 20)->nullable();
            $table->foreignId('transmitido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['emitente_id', 'status']);
        });

        // Linha do tempo da viagem: o que aconteceu, quando e por quem. É o
        // `fiscal_eventos` do Transm, sem os campos de sessão (esses ficam no
        // audit_logs, que o EmitirAgora já grava sozinho).
        Schema::create('transporte_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viagem_id')->constrained('viagens')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo', 40);
            $table->string('descricao', 500);
            $table->json('dados')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['viagem_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transporte_eventos');
        Schema::dropIfExists('mdfes');
        Schema::dropIfExists('cte_eventos');
        Schema::table('viagem_notas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cte_id');
        });
        Schema::dropIfExists('ctes');
    }
};
