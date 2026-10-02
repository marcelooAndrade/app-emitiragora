<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\DoTenantViaEmitente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Motorista extends Model
{
    use Auditavel, DoTenantViaEmitente;

    protected $guarded = ['id', 'emitente_id'];

    protected $attributes = ['ativo' => true];

    protected function casts(): array
    {
        return ['ativo' => 'boolean', 'nascimento' => 'date'];
    }

    /** @return BelongsTo<Emitente, $this> */
    public function emitente(): BelongsTo
    {
        return $this->belongsTo(Emitente::class);
    }

    /** O que o e-Frete pede do motorista além do que o MDF-e pede. */
    public function pendenciasEfrete(): array
    {
        $faltando = [];
        if (strlen(preg_replace('/\D/', '', (string) $this->cnh)) !== 11) {
            $faltando[] = 'CNH com 11 dígitos';
        }
        if ($this->nascimento === null) {
            $faltando[] = 'data de nascimento';
        }
        if (! in_array(strlen(preg_replace('/\D/', '', (string) $this->telefone)), [10, 11], true)) {
            $faltando[] = 'celular com DDD';
        }
        if (blank($this->cep) || blank($this->municipio_codigo) || blank($this->logradouro) || blank($this->bairro)) {
            $faltando[] = 'endereço com CEP';
        }

        return $faltando;
    }

    public function cpfFormatado(): string
    {
        $cpf = (string) $this->cpf;

        return strlen($cpf) === 11
            ? substr($cpf, 0, 3).'.'.substr($cpf, 3, 3).'.'.substr($cpf, 6, 3).'-'.substr($cpf, 9)
            : $cpf;
    }
}
