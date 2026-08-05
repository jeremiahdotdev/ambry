<?php

namespace App\Http\Middleware;

use App\Models\DeveloperApiKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDeveloperApiKey
{
    private const MAX_REQUESTS_PER_SECOND = 10;

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

        if (! $this->hitRateLimitWindow($apiKey)) {
            return $this->rateLimited();
        }

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

    private function hitRateLimitWindow(DeveloperApiKey $apiKey): bool
    {
        $now = now();
        $windowStartedAt = $now->copy()->startOfSecond()->format('Y-m-d H:i:s');

        return DeveloperApiKey::query()
            ->whereKey($apiKey->id)
            ->where(function ($query) use ($windowStartedAt): void {
                $query
                    ->whereNull('request_window_started_at')
                    ->orWhere('request_window_started_at', '<>', $windowStartedAt)
                    ->orWhere('request_window_count', '<', self::MAX_REQUESTS_PER_SECOND);
            })
            ->update([
                'last_used_at' => $now,
                'request_window_started_at' => $windowStartedAt,
                'request_window_count' => DB::raw("case when request_window_started_at = '{$windowStartedAt}' then request_window_count + 1 else 1 end"),
                'updated_at' => $now,
            ]) === 1;
    }

    private function rateLimited(): JsonResponse
    {
        return response()
            ->json([
                'message' => 'Too many requests for this API key.',
            ], Response::HTTP_TOO_MANY_REQUESTS)
            ->withHeaders([
                'Retry-After' => '1',
            ]);
    }
}
