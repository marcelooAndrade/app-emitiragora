<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * O que o banco precisa ter para a aplicação funcionar, e nada além disso.
 *
 * Isto não é dado de exemplo: sem os perfis, o cadastro quebra na criação da
 * conta com `RoleDoesNotExist`, porque `CreateNewUser` dá `assignRole` no
 * perfil Administrador. Foi o que aconteceu em produção em 13/09, onde o
 * deploy rodava só `migrate` e este arquivo ainda era o esqueleto do Laravel,
 * que criava um "Test User" e não semeava perfil nenhum.
 *
 * O PerfilSeeder é idempotente (`findOrCreate` e `syncPermissions`), então
 * rodar a cada deploy é seguro. As tabelas fiscais da NF-e (CST, unidades de
 * medida) saíram em 05/10/2026, com a NF-e.
 */
class DatabaseSeeder extends Seeder
{
    /*
     | Sem `WithoutModelEvents`, que vinha do esqueleto do Laravel: o spatie
     | invalida o cache de permissões por evento de model, e com os eventos
     | suprimidos o `syncPermissions` do PerfilSeeder não enxerga a permissão
     | que ele mesmo acabou de criar. Rebenta com `PermissionDoesNotExist`.
     */
    public function run(): void
    {
        $this->call([
            PerfilSeeder::class,
        ]);
    }
}
