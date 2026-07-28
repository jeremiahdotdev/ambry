<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResearchLead extends Model
{
    protected $fillable = [
        'source',
        'external_id',
        'candidate_name',
        'status',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }
}
