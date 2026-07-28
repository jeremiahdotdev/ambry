<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Source extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'license',
        'attribution',
        'canonical_url',
        'reliability_notes',
    ];

    public function citations(): HasMany
    {
        return $this->hasMany(Citation::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SourceDocument::class);
    }
}
