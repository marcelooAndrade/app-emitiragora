<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\DoTenantViaEmitente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * O que o emitente precisa a mais para emitir CT-e e MDF-e. 1:1 com
 * `Emitente`, como `EmitenteNfse`: quem não transporta não carrega nada disso.
 */
class EmitenteTransporte extends Model
{
    use Auditavel, DoTenantViaEmitente;

    protected $table = 'emitente_transporte';

    protected $guarded = ['id', 'emitente_id'];

    /** Oculto também tira do audit_logs (ver Auditavel). */
    protected $hidden = ['atm_usuario', 'atm_senha', 'atm_codigo', 'efrete_usuario', 'efrete_senha', 'efrete_integrador'];

    protected $attributes = [
        'cte_serie' => 1,
        'mdfe_serie' => 1,
        'cfop' => '5353',
        'natureza_operacao' => 'PRESTACAO DE SERVICO DE TRANSPORTE',
        'tipo_emitente_mdfe' => '1',
        'responsavel_seguro' => '1',
        'prazo_fatura_dias' => 30,
        'adiantamento_percentual' => 80,
    ];

    protected function casts(): array
    {
        return [
            'cte_serie' => 'integer',
            'mdfe_serie' => 'integer',
            'prazo_fatura_dias' => 'integer',
            'adiantamento_percentual' => 'integer',
            'atm_usuario' => 'encrypted',
            'atm_senha' => 'encrypted',
            'atm_codigo' => 'encrypted',
            'efrete_usuario' => 'encrypted',
            'efrete_senha' => 'encrypted',
            'efrete_integrador' => 'encrypted',
            'efrete_massa_antt' => 'boolean',
        ];
    }

    /** @return BelongsTo<Emitente, $this> */
    public function emitente(): BelongsTo
    {
        return $this->belongsTo(Emitente::class);
    }

    /**
     * CFOP da prestação: o configurado vale dentro do estado; entre estados
     * o primeiro dígito vira 6 (5353 → 6353), que é a regra da tabela CFOP.
     */
    public function cfopPara(string $ufInicio, string $ufFim): string
    {
        $cfop = $this->cfop ?: '5353';

        return strtoupper($ufInicio) === strtoupper($ufFim) ? '5'.substr($cfop, 1) : '6'.substr($cfop, 1);
    }

    /** e-Frete configurado: o CIOT pode sair sozinho pela viagem. */
    public function temEfrete(): bool
    {
        return filled($this->efrete_usuario) && filled($this->efrete_senha) && filled($this->efrete_integrador);
    }

    public function temAtm(): bool
    {
        return filled($this->atm_usuario) && filled($this->atm_senha) && filled($this->atm_codigo);
    }

    /** O que falta para o MDF-e sair, em linguagem de quem preenche. */
    public function pendenciasMdfe(): array
    {
        $faltando = [];
        if (strlen((string) $this->rntrc) !== 8) {
            $faltando[] = 'RNTRC da empresa (8 dígitos)';
        }
        if ($this->tipo_emitente_mdfe === '1') {
            if (blank($this->seguradora_nome) || strlen((string) $this->seguradora_cnpj) !== 14 || blank($this->apolice)) {
                $faltando[] = 'seguradora, CNPJ da seguradora e apólice do seguro de carga';
            }
        }

        return $faltando;
    }
}
