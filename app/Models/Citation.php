<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Citation extends Model
{
    use HasUuids;

    protected $fillable = [
        'source_id',
        'title',
        'locator',
        'url',
        'excerpt',
        'accessed_at',
    ];

    protected function casts(): array
    {
        return [
            'accessed_at' => 'date',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }
}
