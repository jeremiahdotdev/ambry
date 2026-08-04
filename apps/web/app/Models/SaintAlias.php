<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaintAlias extends Model
{
    protected $fillable = [
        'saint_id',
        'alias',
        'normalized_alias',
        'language',
        'citation_id',
        'confidence',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
        ];
    }

    public function saint(): BelongsTo
    {
        return $this->belongsTo(Saint::class);
    }
}
