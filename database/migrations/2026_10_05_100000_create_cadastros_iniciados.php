<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Cadastro começado e ainda não confirmado pelo e-mail.
 |
 | A conta só nasce quando a pessoa abre o link que chegou no e-mail: é isso
 | que prova que o e-mail é dela. Até lá, o que ela informou fica aqui, e
 | quem nunca clica continua na tabela com `convertido_em` nulo, que é a
 | lista de quem vale procurar.
 |
 | Sem tenant de propósito: a pessoa ainda não tem empresa no sistema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cadastros_iniciados', function (Blueprint $table) {
            $table->id();
            $table->string('email', 254)->unique();
            $table->string('nome', 160);
            $table->string('telefone', 20);
            // Respostas do modal do site, já filtradas por PerfilDeCadastro.
            $table->json('perfil')->nullable();
            // Só o hash: quem lê o banco não consegue abrir o link de ninguém.
            $table->string('convite_hash', 64)->nullable()->unique();
            $table->timestamp('convite_expira_em')->nullable();
            // Página onde o cadastro começou, com os parâmetros do anúncio.
            $table->string('origem', 1000)->nullable();
            // Cookies do pixel no momento do clique no anúncio. O link do
            // e-mail costuma abrir em outro navegador, sem eles, e a conversão
            // perderia a atribuição ao anúncio.
            $table->string('fbp', 255)->nullable();
            $table->string('fbc', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('convertido_em')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cadastros_iniciados');
    }
};
