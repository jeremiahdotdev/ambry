<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeastDay extends Model
{
    protected $fillable = [
        'saint_id',
        'month',
        'day',
        'calendar',
        'rite',
        'locality',
        'citation_id',
        'confidence',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'day' => 'integer',
            'confidence' => 'float',
        ];
    }

    public function saint(): BelongsTo
    {
        return $this->belongsTo(Saint::class);
    }
}
