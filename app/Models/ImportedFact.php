<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportedFact extends Model
{
    protected $fillable = [
        'import_batch_id',
        'citation_id',
        'entity_type',
        'entity_id',
        'field',
        'value',
        'confidence',
        'is_tradition',
        'is_disputed',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'confidence' => 'float',
            'is_tradition' => 'boolean',
            'is_disputed' => 'boolean',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    public function citation(): BelongsTo
    {
        return $this->belongsTo(Citation::class);
    }
}
