<?php

namespace App\Models;

use App\Enums\Fiscal\Ambiente;
use App\Models\Concerns\Auditavel;
use App\Models\Concerns\DoTenantViaEmitente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * O CIOT de uma viagem (Res. ANTT 6.078/2026: toda operação tem um). Um
 * registro por declaração: recusados e cancelados ficam como histórico, e
 * fora deles a viagem tem no máximo um, o vigente.
 */
class Ciot extends Model
{
    use Auditavel, DoTenantViaEmitente;

    /** Situações que ocupam a vaga de CIOT da viagem. */
    public const VIGENTES = ['rascunho', 'processando', 'registrado', 'encerrado'];

    protected $table = 'ciots';

    protected $guarded = ['id'];

    protected $attributes = ['situacao' => 'rascunho', 'origem' => 'provedor'];

    protected function casts(): array
    {
        return [
            'ambiente' => Ambiente::class,
            'resposta' => 'array',
            'declarado_em' => 'datetime',
            'encerrado_em' => 'datetime',
            'cancelado_em' => 'datetime',
        ];
    }

    /** @return BelongsTo<Viagem, $this> */
    public function viagem(): BelongsTo
    {
        return $this->belongsTo(Viagem::class);
    }

    /** @return BelongsTo<Emitente, $this> */
    public function emitente(): BelongsTo
    {
        return $this->belongsTo(Emitente::class);
    }

    /** Tem número válido: pode ir no MDF-e. */
    public function registrado(): bool
    {
        return in_array($this->situacao, ['registrado', 'encerrado'], true) && filled($this->numero);
    }

    public function digitado(): bool
    {
        return $this->origem === 'digitado';
    }

    /** "123456789012/1234", do jeito que a ANTT e as empresas mostram. */
    public function numeroCompleto(): string
    {
        return $this->numero.($this->verificador ? '/'.$this->verificador : '');
    }
}
