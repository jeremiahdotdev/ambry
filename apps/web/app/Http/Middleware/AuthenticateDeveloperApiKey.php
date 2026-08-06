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
    private const MAX_REQUESTS_PER_DAY = 5000;
    private const MAX_REQUESTS_PER_MINUTE = 60;

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

        if (! $this->hitAccountRateLimitWindows($apiKey)) {
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

    private function hitAccountRateLimitWindows(DeveloperApiKey $apiKey): bool
    {
        $now = now();
        $minuteStartedAt = $now->copy()->startOfMinute()->format('Y-m-d H:i:s');
        $dayStartedAt = $now->copy()->startOfDay()->format('Y-m-d H:i:s');

        $accepted = DB::table('users')
            ->where('id', $apiKey->user_id)
            ->where(function ($query) use ($minuteStartedAt): void {
                $query
                    ->whereNull('api_minute_window_started_at')
                    ->orWhere('api_minute_window_started_at', '<>', $minuteStartedAt)
                    ->orWhere('api_minute_request_count', '<', self::MAX_REQUESTS_PER_MINUTE);
            })
            ->where(function ($query) use ($dayStartedAt): void {
                $query
                    ->whereNull('api_day_window_started_at')
                    ->orWhere('api_day_window_started_at', '<>', $dayStartedAt)
                    ->orWhere('api_day_request_count', '<', self::MAX_REQUESTS_PER_DAY);
            })
            ->update([
                'api_minute_window_started_at' => $minuteStartedAt,
                'api_minute_request_count' => DB::raw("case when api_minute_window_started_at = '{$minuteStartedAt}' then api_minute_request_count + 1 else 1 end"),
                'api_day_window_started_at' => $dayStartedAt,
                'api_day_request_count' => DB::raw("case when api_day_window_started_at = '{$dayStartedAt}' then api_day_request_count + 1 else 1 end"),
                'updated_at' => $now,
            ]) === 1;

        if ($accepted) {
            $apiKey->forceFill(['last_used_at' => $now])->saveQuietly();
        }

        return $accepted;
    }

    private function rateLimited(): JsonResponse
    {
        return response()
            ->json([
                'message' => 'Too many requests for this user account.',
            ], Response::HTTP_TOO_MANY_REQUESTS)
            ->withHeaders([
                'Retry-After' => '60',
            ]);
    }
}
