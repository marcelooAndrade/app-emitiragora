<?php

namespace App\Models;

use App\Enums\PlanoTenant;
use App\Models\Concerns\Auditavel;
use App\Support\HostDoProduto;
use App\Support\TemaMarca;
use Carbon\CarbonInterface;
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

    protected $fillable = ['nome', 'nome_curto', 'slug', 'dominio', 'logo_path', 'tema', 'ativo', 'plano', 'teste_ate', 'pago_ate', 'perfil_cadastro'];

    protected function casts(): array
    {
        return ['tema' => 'array', 'ativo' => 'boolean', 'plano' => PlanoTenant::class, 'teste_ate' => 'datetime', 'pago_ate' => 'date', 'perfil_cadastro' => 'array'];
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

    /** Dias de teste grátis de quem se cadastra. */
    public const DIAS_DE_TESTE = 14;

    /**
     * O teste vai até o fim do 14º dia no horário de Brasília, não até a
     * mesma hora do cadastro: quem entrou às 22h não perde o último dia.
     */
    public static function fimDoTeste(): CarbonInterface
    {
        return now('America/Sao_Paulo')->addDays(self::DIAS_DE_TESTE)->endOfDay()->utc();
    }

    /**
     * Paga até `pago_ate`, mais os dias de tolerância. Dia de calendário em
     * Brasília: quem paga até o dia 10 emite o dia 10 inteiro.
     */
    public function assinante(): bool
    {
        return $this->pago_ate !== null && $this->hoje() <= $this->ultimoDiaDeTolerancia();
    }

    /** Venceu o "pago até", mas ainda está nos dias de tolerância. */
    public function pagamentoVencido(): bool
    {
        return $this->assinante() && $this->hoje() > $this->pago_ate->toDateString();
    }

    /** Último dia em que a empresa com pagamento vencido ainda emite. */
    public function ultimoDiaDeTolerancia(): ?string
    {
        return $this->pago_ate?->addDays((int) config('planos.cobranca.diasDeTolerancia'))->toDateString();
    }

    public function emTeste(): bool
    {
        return ! $this->assinante() && $this->teste_ate !== null && now()->lessThanOrEqualTo($this->teste_ate);
    }

    /**
     * Teste acabado ou mensalidade vencida além da tolerância: a empresa só
     * consulta, não emite nem altera. Empresa sem `teste_ate` e sem
     * `pago_ate` (as que já existiam antes da cobrança) nunca cai aqui.
     */
    public function somenteConsulta(): bool
    {
        return ($this->teste_ate !== null || $this->pago_ate !== null)
            && ! $this->assinante()
            && ! $this->emTeste();
    }

    private function hoje(): string
    {
        return now('America/Sao_Paulo')->toDateString();
    }

    /** Dias de calendário que faltam no teste, contando em Brasília. 0 é o último dia. */
    public function diasDeTesteRestantes(): int
    {
        if ($this->teste_ate === null) {
            return 0;
        }

        $hoje = now('America/Sao_Paulo')->startOfDay();
        $fim = $this->teste_ate->setTimezone('America/Sao_Paulo')->startOfDay();

        return max(0, (int) $hoje->diffInDays($fim, false));
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
