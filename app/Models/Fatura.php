<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\DoTenantViaEmitente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Contas a receber, do jeito da origem: a fatura é o acordo, e o título é a
 * parcela.
 *
 * Uma venda em três vezes são três títulos com vencimentos próprios, e não um
 * título com data única. É a diferença entre cobrar certo e cobrar no chute.
 *
 * @property string|null $public_token
 */
class Fatura extends Model
{
    use Auditavel, DoTenantViaEmitente;

    protected $table = 'faturas';

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'ativa'];

    protected function casts(): array
    {
        return ['mensalidade' => 'boolean'];
    }

    protected static function booted(): void
    {
        // O link público nasce com a fatura. Sorteado, nunca derivado do id.
        static::creating(function (self $fatura): void {
            if (blank($fatura->public_token)) {
                $fatura->public_token = (string) Str::uuid();
            }
        });
    }

    public function parcelas(): HasMany
    {
        return $this->hasMany(FaturaParcela::class)->orderBy('numero');
    }

    public function totalCentavos(): int
    {
        return (int) $this->parcelas()->where('status', '!=', 'cancelado')->sum('valor_centavos');
    }

    public function estaAtiva(): bool
    {
        return $this->status === 'ativa';
    }

    public function destinatario(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'pessoa_id');
    }

    public function centroCusto(): BelongsTo
    {
        return $this->belongsTo(CentroCusto::class, 'centro_custo_id');
    }

    public function emitente(): BelongsTo
    {
        return $this->belongsTo(Emitente::class);
    }
}
