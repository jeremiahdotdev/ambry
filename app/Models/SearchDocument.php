<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SearchDocument extends Model
{
    use HasUuids;

    protected $fillable = [
        'entity_type',
        'entity_id',
        'title',
        'summary',
        'body',
        'facets',
        'relationship_terms',
        'indexed_at',
    ];

    protected function casts(): array
    {
        return [
            'facets' => 'array',
            'relationship_terms' => 'array',
            'indexed_at' => 'datetime',
        ];
    }
}
