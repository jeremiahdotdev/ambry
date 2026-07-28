<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SourceDocument extends Model
{
    use HasUuids;

    protected $fillable = [
        'source_id',
        'title',
        'slug',
        'author',
        'edition',
        'language',
        'url',
        'raw_text',
        'checksum',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }
}
