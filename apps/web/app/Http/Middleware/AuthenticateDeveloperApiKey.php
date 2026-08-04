<?php

namespace App\Http\Middleware;

use App\Models\DeveloperApiKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDeveloperApiKey
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->tokenFromRequest($request);

        if ($token === null) {
            return $this->unauthorized('API key is required.');
        }

        $apiKey = DeveloperApiKey::active()
            ->where('token_hash', DeveloperApiKey::hashToken($token))
            ->first();

        if ($apiKey === null) {
            return $this->unauthorized('API key is invalid or inactive.');
        }

        $apiKey->forceFill(['last_used_at' => now()])->saveQuietly();
        $request->attributes->set('developer_api_key', $apiKey);

        return $next($request);
    }

    private function tokenFromRequest(Request $request): ?string
    {
        $token = $request->bearerToken() ?: $request->header('X-API-Key');
        $token = is_string($token) ? trim($token) : '';

        return $token !== '' ? $token : null;
    }

    private function unauthorized(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], Response::HTTP_UNAUTHORIZED);
    }
}
