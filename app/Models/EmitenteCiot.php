<?php

namespace App\Models;

use App\Enums\Fiscal\Ambiente;
use App\Models\Concerns\Auditavel;
use App\Models\Concerns\DoTenantViaEmitente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Qual empresa gera o CIOT deste emitente e com que credenciais. 1:1 com
 * `Emitente`, ao lado de `EmitenteTransporte`.
 *
 * As credenciais ficam por empresa, e não só as da empresa atual: um CIOT
 * gerado pelo e-Frete continua sendo encerrado no e-Frete depois que o
 * emitente troca de empresa, e para isso as credenciais dele precisam
 * continuar aqui.
 */
class EmitenteCiot extends Model
{
    use Auditavel, DoTenantViaEmitente;

    protected $table = 'emitente_ciot';

    protected $guarded = ['id', 'emitente_id'];

    /** Oculto também tira do audit_logs (ver Auditavel). */
    protected $hidden = ['credenciais_homologacao', 'credenciais_producao'];

    protected $attributes = ['provedor' => 'manual', 'recebimento_tipo' => 'pix'];

    protected function casts(): array
    {
        return [
            'credenciais_homologacao' => 'encrypted:array',
            'credenciais_producao' => 'encrypted:array',
        ];
    }

    /** @return BelongsTo<Emitente, $this> */
    public function emitente(): BelongsTo
    {
        return $this->belongsTo(Emitente::class);
    }

    /** @return array<string, mixed> */
    public function credenciais(string $provedor, Ambiente $ambiente): array
    {
        return (array) ($this->{$this->coluna($ambiente)}[$provedor] ?? []);
    }

    /** Regrava só a empresa informada; as das outras ficam como estão. */
    public function guardarCredenciais(string $provedor, Ambiente $ambiente, array $valores): void
    {
        $coluna = $this->coluna($ambiente);
        $todas = (array) $this->{$coluna};
        $todas[$provedor] = $valores;
        $this->forceFill([$coluna => $todas])->save();
    }

    private function coluna(Ambiente $ambiente): string
    {
        return $ambiente === Ambiente::Producao ? 'credenciais_producao' : 'credenciais_homologacao';
    }
}
