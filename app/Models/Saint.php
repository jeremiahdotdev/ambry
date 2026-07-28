<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Saint extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'primary_name',
        'slug',
        'biography',
        'birth_year',
        'birth_year_qualifier',
        'death_year',
        'death_year_qualifier',
        'life_dates',
        'gender',
        'canonical_status',
        'is_martyr',
        'is_doctor',
        'virtues',
        'vices',
        'roles',
        'ai_reason',
        'ai_confidence',
        'image_prompt',
    ];

    protected function casts(): array
    {
        return [
            'birth_year' => 'integer',
            'death_year' => 'integer',
            'is_martyr' => 'boolean',
            'is_doctor' => 'boolean',
            'virtues' => 'array',
            'vices' => 'array',
            'roles' => 'array',
            'ai_confidence' => 'decimal:3',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(SaintAlias::class);
    }

    public function feastDays(): HasMany
    {
        return $this->hasMany(FeastDay::class);
    }

    public function patronages(): BelongsToMany
    {
        return $this->belongsToMany(Patronage::class)
            ->withPivot(['citation_id', 'confidence', 'is_tradition'])
            ->withTimestamps();
    }

    public function religiousOrders(): BelongsToMany
    {
        return $this->belongsToMany(ReligiousOrder::class)
            ->withPivot(['role', 'citation_id', 'confidence'])
            ->withTimestamps();
    }

    public function outgoingRelationships(): HasMany
    {
        return $this->hasMany(Relationship::class, 'source_saint_id');
    }

    public function incomingRelationships(): HasMany
    {
        return $this->hasMany(Relationship::class, 'target_saint_id');
    }
}
