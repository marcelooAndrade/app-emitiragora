<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CteEvento extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['sequencia' => 'integer', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<Cte, $this> */
    public function cte(): BelongsTo
    {
        return $this->belongsTo(Cte::class);
    }
}
