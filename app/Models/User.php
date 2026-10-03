<?php

namespace App\Models;

use App\Enums\Perfil;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\Auditavel;
use App\Support\TenantAtual;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['tenant_id', 'name', 'email', 'password', 'email_verified_at'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use Auditavel, HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    use HasRoles {
        hasPermissionTo as protected temPermissaoPeloPapel;
    }

    /**
     * Default também em memória, não só no banco: coluna booleana vem `null`
     * num model recém criado, e `null` é falsy. Mesma regra dos outros
     * models, coberta por DefaultsEmMemoriaTest.
     */
    protected $attributes = [
        'ativo' => true,
    ];

    /**
     * Escopo próprio, e não o `DoTenant` comum, porque o `User` tem uma regra
     * a mais: ele é consultado pelo provider de autenticação, antes de existir
     * sessão.
     *
     * Com tenant resolvido pelo host, a busca fica restrita a ele. É o que
     * impede a credencial de um cliente de entrar no domínio próprio de outro,
     * e continua coberto por LoginEntreTenantsTest.
     *
     * Sem tenant no host, que é o caso do domínio do produto, a busca é global.
     * É assim que todo cliente entra pela mesma porta. Depois da autenticação
     * o `DefinirTenantDoUsuario` fixa o tenant a partir do próprio usuário, e
     * daí em diante todo dado volta a ser escopado normalmente.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function (Builder $query): void {
            $id = app(TenantAtual::class)->id();

            if ($id !== null) {
                $query->where($query->getModel()->getTable().'.tenant_id', $id);
            }
        });

        static::creating(function (self $user): void {
            if ($user->getAttribute('tenant_id') === null) {
                $user->setAttribute('tenant_id', app(TenantAtual::class)->id());
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'ultimo_acesso_em' => 'datetime',
            'ativo' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /**
     * Emitentes aos quais este usuário tem acesso.
     *
     * @return BelongsToMany<Emitente, $this>
     */
    public function emitentes(): BelongsToMany
    {
        return $this->belongsToMany(Emitente::class)->withTimestamps();
    }

    /**
     * Todo dado fiscal é isolado por emitente. Sem vínculo, sem acesso.
     *
     * Sem o escopo de tenant, de propósito: esta checagem também vale para
     * escolher uma empresa diferente da que está em foco agora, incluindo a
     * primeira vez, quando o tenant da sessão ainda não é o dela. Ver
     * EmitenteAtual::escolher().
     */
    public function podeAcessar(Emitente $emitente): bool
    {
        return $this->emitentes()->withoutGlobalScope('tenant')->whereKey($emitente->getKey())->exists();
    }

    /**
     * O plano corta antes do papel: na Pequena Empresa ninguém tem
     * `transporte.*`, nem o Administrador.
     *
     * Fica aqui, e não num `Gate::before`, porque o spatie registra o dele
     * primeiro e responde `true` pelo papel antes de qualquer outro ser
     * ouvido. Todo caminho do spatie (`can`, `checkPermissionTo`,
     * `hasAnyPermission`) passa por este método.
     */
    public function hasPermissionTo($permission, $guardName = null): bool
    {
        $nome = $permission instanceof \BackedEnum ? $permission->value : $permission;
        $nome = is_object($nome) && isset($nome->name) ? $nome->name : $nome;

        if (is_string($nome) && str_starts_with($nome, 'transporte.')) {
            $tenant = app(TenantAtual::class)->obter();

            if ($tenant !== null && ! $tenant->plano->permiteTransporte()) {
                return false;
            }
        }

        return $this->temPermissaoPeloPapel($permission, $guardName);
    }

    /**
     * As roles do spatie são escopadas por emitente (teams), então a checagem
     * global precisa olhar o pivô direto, sem o escopo do time corrente.
     */
    public function eAdministradorEmAlgumEmitente(): bool
    {
        return DB::table(config('permission.table_names.model_has_roles'))
            ->where('model_type', $this->getMorphClass())
            ->where(config('permission.column_names.model_morph_key'), $this->getKey())
            ->whereIn('role_id', Role::query()->where('name', Perfil::Administrador->value)->pluck('id'))
            ->exists();
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
