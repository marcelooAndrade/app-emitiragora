<?php

namespace App\Models;

use App\Models\Concerns\DoTenantViaEmitente;
use Illuminate\Database\Eloquent\Model;

class TransporteSerie extends Model
{
    use DoTenantViaEmitente;

    public const VIAGEM = 0;

    public const CTE = 57;

    public const MDFE = 58;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'modelo' => 'integer',
            'serie' => 'integer',
            'proximo_numero' => 'integer',
        ];
    }
}
