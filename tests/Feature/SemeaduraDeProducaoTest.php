<?php

/**
 * Guarda o buraco entre o que a suíte semeia e o que a produção tem.
 *
 * Em 13/09 o cadastro quebrou em produção com `RoleDoesNotExist`: o
 * `CreateNewUser` dá `assignRole` no perfil Administrador, mas a tabela de
 * papéis estava vazia. O deploy rodava só `migrate`, e o `DatabaseSeeder`
 * ainda era o esqueleto do Laravel, que não chamava o `PerfilSeeder`.
 *
 * O teste de cadastro não pegou porque ele semeia o `PerfilSeeder` no
 * `beforeEach`: passava verde justamente enquanto a produção quebrava. Por
 * isso os testes daqui partem do `DatabaseSeeder`, que é o que o deploy roda,
 * e não do seeder específico.
 */

use App\Enums\Perfil;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PerfilSeeder;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

it('o seeder do deploy cria todos os perfis do sistema', function () {
    $this->seed(DatabaseSeeder::class);

    foreach (Perfil::cases() as $perfil) {
        expect(Role::query()->where('name', $perfil->value)->where('guard_name', 'web')->exists())
            ->toBeTrue("o perfil {$perfil->value} não foi semeado");
    }
});

it('o seeder do deploy cria todas as permissoes', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Permission::query()->count())->toBe(count(PerfilSeeder::PERMISSOES));
});

it('o seeder do deploy nao cria usuario nenhum, porque producao roda ele', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('rodar o seeder duas vezes nao duplica nada, porque o deploy roda a cada release', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(Role::query()->count())->toBe(count(Perfil::cases()))
        ->and(Permission::query()->count())->toBe(count(PerfilSeeder::PERMISSOES));
});

it('o cadastro funciona num banco semeado como o da producao', function () {
    Http::fake();
    config(['produto.dominio' => 'vendaredonda.com.br']);

    $this->seed(DatabaseSeeder::class);

    $this->post('http://vendaredonda.com.br/register', comConvite([
        'name' => 'Marcelo Andrade',
        'email' => 'marcelo@exemplo.com.br',
        'password' => 'Rt7#kzQwLm',
        'password_confirmation' => 'Rt7#kzQwLm',
        'razao_social' => 'Exemplo Comércio Ltda',
        'cnpj' => '19.131.243/0001-97',
        'inscricao_estadual' => 'ISENTO',
        'crt' => '3',
        'telefone' => '(19) 99999-0000',
    ]))->assertRedirect();

    $usuario = User::query()->withoutGlobalScopes()->firstWhere('email', 'marcelo@exemplo.com.br');

    expect($usuario)->not->toBeNull('o cadastro não criou o usuário');

    app(PermissionRegistrar::class)
        ->setPermissionsTeamId($usuario->emitentes()->first()->getKey());

    expect($usuario->fresh()->hasRole(Perfil::Administrador->value))->toBeTrue();
});
