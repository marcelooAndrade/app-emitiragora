<?php

namespace App\Models;

use App\Enums\PlanoTenant;
use App\Models\Concerns\Auditavel;
use App\Support\HostDoProduto;
use App\Support\TemaMarca;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class Tenant extends Model
{
    use Auditavel;

    /**
     * Default também em memória, não só no banco. Um tenant recém-criado
     * precisa já se dizer ativo, senão quem o consulta antes de reler recebe
     * null e o trata como inativo.
     */
    protected $attributes = [
        'ativo' => true,
        'plano' => PlanoTenant::Transporte->value,
    ];

    protected $fillable = ['nome', 'nome_curto', 'slug', 'dominio', 'logo_path', 'tema', 'ativo', 'plano', 'perfil_cadastro'];

    protected function casts(): array
    {
        return ['tema' => 'array', 'ativo' => 'boolean', 'plano' => PlanoTenant::class, 'perfil_cadastro' => 'array'];
    }

    /**
     * Slug reservado é recusado no model, e não só na tela.
     *
     * O primeiro rótulo do host vira slug. Um tenant de slug `app` passaria a
     * receber `app.<dominio>`, que é o host de login do próprio produto. Como
     * ainda não existe tela de cadastro de tenant, a regra precisa morar onde
     * toda criação passa.
     */
    protected static function booted(): void
    {
        static::saving(function (self $tenant): void {
            $slug = strtolower(trim((string) $tenant->slug));

            if (in_array($slug, HostDoProduto::slugsReservados(), true)) {
                throw new InvalidArgumentException(
                    "O slug \"{$slug}\" é reservado: ele capturaria um host do próprio produto."
                );
            }

            // Domínio próprio é benefício de plano. Guardar a regra aqui, e não
            // só na tela, impede que um rebaixamento deixe o cliente com o que
            // ele deixou de pagar.
            if (filled($tenant->dominio) && ! $tenant->plano->permiteMarcaPropria()) {
                throw new InvalidArgumentException(
                    'O plano desta empresa não permite domínio próprio. Retire o domínio antes de trocar o plano.'
                );
            }
        });
    }

    public function emitentes(): HasMany
    {
        return $this->hasMany(Emitente::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Null quando o tenant não tem marca própria: vale o padrão do bundle. */
    public function marca(): ?TemaMarca
    {
        return TemaMarca::deArray($this->tema ?? []);
    }

    public function rotulo(): string
    {
        return $this->nome_curto ?: $this->nome;
    }
}
