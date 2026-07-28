<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Relationship extends Model
{
    use HasUuids;

    protected $fillable = [
        'source_saint_id',
        'target_saint_id',
        'relationship_type_id',
        'citation_id',
        'confidence',
        'is_tradition',
        'is_disputed',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'is_tradition' => 'boolean',
            'is_disputed' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function sourceSaint(): BelongsTo
    {
        return $this->belongsTo(Saint::class, 'source_saint_id');
    }

    public function targetSaint(): BelongsTo
    {
        return $this->belongsTo(Saint::class, 'target_saint_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(RelationshipType::class, 'relationship_type_id');
    }

    public function citation(): BelongsTo
    {
        return $this->belongsTo(Citation::class);
    }
}
