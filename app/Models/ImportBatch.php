<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    use HasUuids;

    protected $fillable = [
        'source_id',
        'adapter',
        'status',
        'created_count',
        'updated_count',
        'skipped_count',
        'report',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'report' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function facts(): HasMany
    {
        return $this->hasMany(ImportedFact::class);
    }
}
