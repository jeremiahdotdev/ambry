<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaintEditorPermission extends Model
{
    public const ROLE_EDITOR = 'editor';

    public const ROLE_OWNER = 'owner';

    protected $fillable = [
        'email',
        'role',
    ];

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }
}
