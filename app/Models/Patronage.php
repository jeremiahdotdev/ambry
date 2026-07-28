<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Patronage extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'category',
        'description',
    ];

    public function saints(): BelongsToMany
    {
        return $this->belongsToMany(Saint::class)
            ->withPivot(['citation_id', 'confidence', 'is_tradition'])
            ->withTimestamps();
    }
}
