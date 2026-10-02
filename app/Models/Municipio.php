<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabela oficial do IBGE (semeada por TabelasFiscaisSeeder). Só leitura.
 */
class Municipio extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'codigo_ibge';

    protected $keyType = 'string';

    protected $guarded = [];

    public function rotulo(): string
    {
        return "{$this->nome}/{$this->uf}";
    }
}
