<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ReligiousOrder extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'abbreviation',
        'description',
    ];

    public function saints(): BelongsToMany
    {
        return $this->belongsToMany(Saint::class)
            ->withPivot(['role', 'citation_id', 'confidence'])
            ->withTimestamps();
    }
}
