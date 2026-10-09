<?php

namespace App\Models;

use App\Enums\Transporte\CteStatus;
use App\Enums\Transporte\MdfeStatus;
use App\Enums\Transporte\ViagemStatus;
use App\Models\Concerns\Auditavel;
use App\Models\Concerns\DoTenantViaEmitente;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A viagem: uma carga, um motorista, um veículo e as NF-e que vão nele. É a
 * "ordem de faturamento interno" do Transm, sem o que lá era da operação de
 * pátio e cerâmica (pallets, conferência, expedição).
 */
class Viagem extends Model
{
    use Auditavel, DoTenantViaEmitente;

    protected $table = 'viagens';

    protected $guarded = ['id', 'emitente_id', 'numero', 'status'];

    /** Padrões do banco declarados aqui também (DF-014). */
    protected $attributes = [
        'status' => 'rascunho',
        'frete_modo' => 'tonelada',
        'tipo_carga' => 5,
        'alto_desempenho' => false,
        'retorno_vazio' => false,
    ];

    protected function casts(): array
    {
        return [
            'status' => ViagemStatus::class,
            'data_carregamento' => 'date',
            'numero' => 'integer',
            'frete_tonelada_centavos' => 'integer',
            'frete_fechado_centavos' => 'integer',
            'pedagio_centavos' => 'integer',
            'frete_motorista_centavos' => 'integer',
            'adiantamento_centavos' => 'integer',
            'distancia_km' => 'integer',
            'previsao_entrega' => 'date',
            'tipo_carga' => 'integer',
            'alto_desempenho' => 'boolean',
            'retorno_vazio' => 'boolean',
        ];
    }

    /** @return BelongsTo<Emitente, $this> */
    public function emitente(): BelongsTo
    {
        return $this->belongsTo(Emitente::class);
    }

    /** @return HasMany<ViagemNota, $this> */
    public function notas(): HasMany
    {
        return $this->hasMany(ViagemNota::class)->orderBy('id');
    }

    /** @return HasMany<Cte, $this> */
    public function ctes(): HasMany
    {
        return $this->hasMany(Cte::class)->orderBy('id');
    }

    /** @return HasOne<Mdfe, $this> */
    public function mdfe(): HasOne
    {
        return $this->hasOne(Mdfe::class);
    }

    /** @return HasOne<ContratoFrete, $this> */
    public function contrato(): HasOne
    {
        return $this->hasOne(ContratoFrete::class);
    }

    /** @return HasMany<Ciot, $this> */
    public function ciots(): HasMany
    {
        return $this->hasMany(Ciot::class)->orderBy('id');
    }

    /**
     * O CIOT que vale para a viagem agora. Recusados e cancelados ficam no
     * histórico de `ciots()` e não contam.
     *
     * @return HasOne<Ciot, $this>
     */
    public function ciotVigente(): HasOne
    {
        return $this->hasOne(Ciot::class)->ofMany(['id' => 'max'], fn ($query) => $query->whereIn('situacao', Ciot::VIGENTES));
    }

    /**
     * Lotação (1) com um tomador só, fracionada (2) com mais de um. O que a
     * pessoa escolhe na tela vale mais que a sugestão.
     */
    public function tipoOperacao(): string
    {
        if (in_array($this->tipo_operacao, ['1', '2'], true)) {
            return $this->tipo_operacao;
        }
        $tomadores = $this->ctes
            ->reject(fn (Cte $c): bool => $c->status === CteStatus::Cancelado)
            ->map(fn (Cte $c): string => (string) preg_replace('/\D/', '', (string) ($c->tomador()['documento'] ?? '')))
            ->filter()
            ->unique();

        return $tomadores->count() > 1 ? '2' : '1';
    }

    /** Sem previsão informada: o carregamento mais os dias de `config/ciot.php`. */
    public function previsaoEntrega(): CarbonInterface
    {
        return $this->previsao_entrega?->copy()
            ?? ($this->data_carregamento ?? today())->copy()->addDays((int) config('ciot.previsao_entrega_dias', 3));
    }

    /** @return HasMany<TransporteEvento, $this> */
    public function eventos(): HasMany
    {
        return $this->hasMany(TransporteEvento::class)->latest('created_at')->latest('id');
    }

    /** @return BelongsTo<Motorista, $this> */
    public function motorista(): BelongsTo
    {
        return $this->belongsTo(Motorista::class);
    }

    /** @return BelongsTo<Veiculo, $this> */
    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class);
    }

    /** @return BelongsTo<Veiculo, $this> */
    public function reboque(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class, 'reboque_id');
    }

    /** @return BelongsTo<Veiculo, $this> */
    public function reboque2(): BelongsTo
    {
        return $this->belongsTo(Veiculo::class, 'reboque2_id');
    }

    /** Frete pago a motorista terceiro: entra contrato, CIOT e pagamento no MDF-e. */
    public function comTerceiro(): bool
    {
        return (bool) $this->veiculo?->deTerceiro();
    }

    public function numeroFormatado(): string
    {
        return str_pad((string) $this->numero, 5, '0', STR_PAD_LEFT);
    }

    public function pesoTotalKg(): float
    {
        return (float) $this->notas->sum(fn (ViagemNota $nota): float => (float) $nota->peso_kg);
    }

    public function valorCargaCentavos(): int
    {
        return (int) $this->notas->sum('valor_centavos');
    }

    /** Já tem documento na SEFAZ: notas e participantes não mudam mais. */
    public function travada(): bool
    {
        return $this->ctes->contains(fn (Cte $cte): bool => ! $cte->status->transmissivel());
    }

    public function registrar(string $tipo, string $descricao, ?array $dados = null, ?int $userId = null): TransporteEvento
    {
        return $this->eventos()->create([
            'user_id' => $userId ?? auth()->id(),
            'tipo' => $tipo,
            'descricao' => mb_substr($descricao, 0, 500),
            'dados' => $dados,
            'created_at' => now(),
        ]);
    }

    /**
     * O status é consequência dos documentos. Rodar depois de toda mudança
     * de CT-e ou MDF-e mantém a lista honesta.
     */
    public function recalcularStatus(): void
    {
        $this->unsetRelation('ctes');
        $this->unsetRelation('mdfe');
        $ctes = $this->ctes;
        $mdfe = $this->mdfe;

        $status = match (true) {
            $ctes->isNotEmpty() && $ctes->every(fn (Cte $c): bool => $c->status === CteStatus::Cancelado)
                && ($mdfe === null || in_array($mdfe->status, [MdfeStatus::Cancelado, MdfeStatus::Rascunho], true)) => ViagemStatus::Cancelada,
            $mdfe?->status === MdfeStatus::Encerrado => ViagemStatus::Encerrada,
            $mdfe?->status === MdfeStatus::Autorizado => ViagemStatus::Emitida,
            $ctes->contains(fn (Cte $c): bool => in_array($c->status, [CteStatus::Rejeitado, CteStatus::Denegado, CteStatus::EmProcessamento], true))
                || $mdfe?->status === MdfeStatus::Rejeitado => ViagemStatus::Pendente,
            $ctes->contains(fn (Cte $c): bool => $c->status === CteStatus::Autorizado) => ViagemStatus::Pendente,
            default => ViagemStatus::Rascunho,
        };

        if ($this->status !== $status) {
            $this->forceFill(['status' => $status])->save();
        }
    }
}
