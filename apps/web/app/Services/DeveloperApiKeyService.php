<?php

namespace App\Services;

use App\Models\DeveloperApiKey;
use App\Models\User;
use Illuminate\Support\Carbon;

class DeveloperApiKeyService
{
    public function create(User $user, array $validated): string
    {
        $token = sprintf('saints_%s_%s', app()->isProduction() ? 'live' : 'test', bin2hex(random_bytes(32)));

        $user->developerApiKeys()->create([
            'name' => $validated['name'],
            'prefix' => substr($token, 0, 24),
            'token_hash' => DeveloperApiKey::hashToken($token),
            'expires_at' => filled($validated['expires_at'] ?? null)
                ? Carbon::parse($validated['expires_at'])->endOfDay()
                : null,
        ]);

        return $token;
    }
}
