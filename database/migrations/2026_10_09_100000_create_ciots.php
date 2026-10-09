<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 | CIOT para todos (Res. ANTT 6.078/2026, ver DF-026): toda viagem tem CIOT,
 | com caminhão próprio ou de terceiro, e a empresa que gera o CIOT é
 | escolhida por emitente. O CIOT sai do contrato do frete, que só existe
 | com terceiro, e ganha tabela própria; distância, tipo de carga e previsão
 | de entrega vão para a viagem. As colunas antigas só saem em
 | 2026_10_09_110000, quando o código do e-Frete deixa de lê-las.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ciots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emitente_id')->constrained()->cascadeOnDelete();
            $table->foreignId('viagem_id')->constrained('viagens')->cascadeOnDelete();
            // Chave da empresa em config/ciot.php. Fica no CIOT para que
            // consulta, cancelamento e encerramento voltem à empresa que o
            // gerou, mesmo depois de o emitente trocar de empresa.
            $table->string('provedor', 20);
            $table->string('ambiente', 12);
            $table->string('situacao', 12)->default('rascunho'); // rascunho | processando | registrado | encerrado | cancelado | recusado
            $table->string('origem', 10)->default('provedor'); // provedor | digitado
            $table->char('numero', 12)->nullable();
            $table->char('verificador', 4)->nullable();
            $table->string('protocolo', 60)->nullable();
            // Quem gerou o CIOT: vai no infCIOT do MDF-e.
            $table->string('responsavel_documento', 14)->nullable();
            $table->string('codigo_retorno', 10)->nullable();
            $table->text('mensagem')->nullable();
            // A ANTT manda mostrar e imprimir quando vem (DCS, serviço 03).
            $table->text('aviso_transportador')->nullable();
            $table->json('resposta')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamp('declarado_em')->nullable();
            $table->timestamp('encerrado_em')->nullable();
            $table->timestamp('cancelado_em')->nullable();
            $table->string('motivo_cancelamento', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['viagem_id', 'situacao']);
        });

        Schema::create('emitente_ciot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emitente_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provedor', 20)->default('manual');
            // JSON cifrado, chaveado pela empresa: {"efrete": {...}, "strada": {...}}.
            $table->text('credenciais_homologacao')->nullable();
            $table->text('credenciais_producao')->nullable();
            // Como os clientes pagam o frete, que a ANTT pede também na frota própria.
            $table->string('recebimento_tipo', 14)->default('pix'); // pix | transferencia | boleto
            $table->char('recebimento_banco', 3)->nullable();
            $table->string('recebimento_agencia', 10)->nullable();
            $table->string('recebimento_conta', 20)->nullable();
            $table->timestamps();
        });

        Schema::table('viagens', function (Blueprint $table) {
            $table->char('tipo_operacao', 1)->nullable(); // 1 lotação | 2 fracionada; nulo = sugerido pelo sistema
            $table->unsignedInteger('distancia_km')->nullable();
            $table->date('previsao_entrega')->nullable();
            $table->unsignedTinyInteger('tipo_carga')->default(5);
            $table->boolean('alto_desempenho')->default(false);
            $table->boolean('retorno_vazio')->default(false);
        });

        $this->copiarDoContrato();
        $this->copiarCredenciaisEfrete();
    }

    public function down(): void
    {
        Schema::table('viagens', function (Blueprint $table) {
            $table->dropColumn(['tipo_operacao', 'distancia_km', 'previsao_entrega', 'tipo_carga', 'alto_desempenho', 'retorno_vazio']);
        });
        Schema::dropIfExists('emitente_ciot');
        Schema::dropIfExists('ciots');
    }

    private function copiarDoContrato(): void
    {
        foreach (DB::table('contratos_frete')->orderBy('id')->get() as $c) {
            DB::table('viagens')->where('id', $c->viagem_id)->update(array_filter([
                'distancia_km' => $c->ciot_distancia_km,
                'tipo_carga' => $c->ciot_tipo_carga,
                'previsao_entrega' => $c->ciot_fim_previsto,
            ], fn (mixed $v): bool => $v !== null));

            if (blank($c->ciot) && $c->ciot_status !== 'processando') {
                continue;
            }
            $emitente = DB::table('emitentes')->where('id', $c->emitente_id)->first(['cnpj', 'ambiente']);
            DB::table('ciots')->insert([
                'emitente_id' => $c->emitente_id,
                'viagem_id' => $c->viagem_id,
                'provedor' => $c->ciot_status !== null ? 'efrete' : 'manual',
                'ambiente' => $emitente->ambiente ?? 'homologacao',
                'situacao' => match (true) {
                    $c->status === 'cancelado' => 'cancelado',
                    $c->ciot_status === 'encerrado' => 'encerrado',
                    $c->ciot_status === 'processando' => 'processando',
                    default => 'registrado',
                },
                'origem' => $c->ciot_status !== null ? 'provedor' : 'digitado',
                'numero' => $c->ciot,
                'verificador' => $c->ciot_verificador,
                'protocolo' => $c->ciot_protocolo,
                'responsavel_documento' => $emitente->cnpj ?? null,
                'resposta' => $c->ciot_resposta,
                'pdf_path' => $c->ciot_pdf_path,
                'declarado_em' => $c->ciot_emitido_em,
                'encerrado_em' => $c->ciot_encerrado_em,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /** O e-Frete só rodava em homologação, então as credenciais vão para lá. */
    private function copiarCredenciaisEfrete(): void
    {
        $ler = fn (?string $v): ?string => $v === null ? null : Crypt::decryptString($v);
        foreach (DB::table('emitente_transporte')->whereNotNull('efrete_usuario')->get() as $t) {
            DB::table('emitente_ciot')->insert([
                'emitente_id' => $t->emitente_id,
                'provedor' => 'efrete',
                'credenciais_homologacao' => Crypt::encryptString((string) json_encode(['efrete' => [
                    'usuario' => $ler($t->efrete_usuario),
                    'senha' => $ler($t->efrete_senha),
                    'integrador' => $ler($t->efrete_integrador),
                    'massa_antt' => (bool) $t->efrete_massa_antt,
                    'embalagem' => 'Pallet',
                ]])),
                'recebimento_tipo' => 'pix',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
