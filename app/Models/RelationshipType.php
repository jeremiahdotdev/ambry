<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RelationshipType extends Model
{
    protected $fillable = [
        'key',
        'label',
        'inverse_key',
        'category',
        'is_symmetric',
        'is_derived',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'is_symmetric' => 'boolean',
            'is_derived' => 'boolean',
        ];
    }

    public function relationships(): HasMany
    {
        return $this->hasMany(Relationship::class);
    }
}
