<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\DoTenantViaEmitente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cavalo (tração) ou carreta (reboque). No Transm eram duas tabelas e mais
 * uma de proprietários; aqui é uma só, com o proprietário embutido, porque o
 * MDF-e só precisa do proprietário quando ele não é a própria empresa.
 */
class Veiculo extends Model
{
    use Auditavel, DoTenantViaEmitente;

    public const TIPOS_RODADO = [
        '01' => 'Truck', '02' => 'Toco', '03' => 'Cavalo mecânico', '04' => 'VAN',
        '05' => 'Utilitário', '06' => 'Outros',
    ];

    public const TIPOS_CARROCERIA = [
        '00' => 'Não aplicável', '01' => 'Aberta', '02' => 'Fechada/Baú', '03' => 'Graneleira',
        '04' => 'Porta-contêiner', '05' => 'Sider',
    ];

    public const TIPOS_PROPRIETARIO = [
        '0' => 'TAC agregado', '1' => 'TAC independente', '2' => 'Outros',
    ];

    protected $guarded = ['id', 'emitente_id'];

    protected $attributes = [
        'tipo' => 'tracao',
        'proprietario_tipo' => 'proprio',
        'ativo' => true,
    ];

    protected function casts(): array
    {
        return [
            'tara_kg' => 'integer',
            'capacidade_kg' => 'integer',
            'capacidade_m3' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    /** @return BelongsTo<Emitente, $this> */
    public function emitente(): BelongsTo
    {
        return $this->belongsTo(Emitente::class);
    }

    public function eTracao(): bool
    {
        return $this->tipo === 'tracao';
    }

    /** Veículo de terceiro: entra o grupo `prop` no MDF-e e, com TAC, o CIOT. */
    public function deTerceiro(): bool
    {
        return $this->proprietario_tipo === 'terceiro';
    }

    public function placaFormatada(): string
    {
        $placa = strtoupper((string) $this->placa);

        return strlen($placa) === 7 ? substr($placa, 0, 3).'-'.substr($placa, 3) : $placa;
    }

    public function rotulo(): string
    {
        return $this->placaFormatada().($this->eTracao() ? ' · cavalo' : ' · carreta');
    }

    /** O que falta no cadastro para o MDF-e aceitar este veículo. */
    public function pendenciasMdfe(): array
    {
        $faltando = [];
        if (strlen((string) $this->placa) !== 7) {
            $faltando[] = 'placa';
        }
        if ((int) $this->tara_kg <= 0) {
            $faltando[] = 'tara';
        }
        if ($this->eTracao() && blank($this->tipo_rodado)) {
            $faltando[] = 'tipo de rodado';
        }
        if (blank($this->tipo_carroceria)) {
            $faltando[] = 'tipo de carroceria';
        }
        if ($this->deTerceiro()) {
            if (! in_array(strlen((string) $this->proprietario_documento), [11, 14], true)) {
                $faltando[] = 'CPF/CNPJ do proprietário';
            }
            if (blank($this->proprietario_nome)) {
                $faltando[] = 'nome do proprietário';
            }
            if (strlen((string) $this->proprietario_rntrc) !== 8) {
                $faltando[] = 'RNTRC do proprietário';
            }
            if (blank($this->proprietario_tp)) {
                $faltando[] = 'tipo do proprietário';
            }
        }

        return $faltando;
    }
}
