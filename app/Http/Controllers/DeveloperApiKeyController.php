<?php

namespace App\Http\Controllers;

use App\Models\DeveloperApiKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DeveloperApiKeyController extends Controller
{
    public function index(Request $request): View
    {
        return view('developers.api-keys.index', [
            'apiKeys' => $request->user()
                ->developerApiKeys()
                ->latest()
                ->get(),
            'newToken' => session('new_api_token'),
            'apiDocumentationUrl' => config('services.saints_api.url'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        $user = $request->user();

        if ($user->developerApiKeys()->active()->count() >= 10) {
            return back()
                ->withErrors(['name' => 'You can have up to 10 active API keys. Revoke an existing key before creating another.'])
                ->withInput();
        }

        $token = $this->generateToken();

        $user->developerApiKeys()->create([
            'name' => $validated['name'],
            'prefix' => substr($token, 0, 24),
            'token_hash' => Str::lower(hash('sha256', $token)),
            'expires_at' => isset($validated['expires_at'])
                ? Carbon::parse($validated['expires_at'])->endOfDay()
                : null,
        ]);

        return redirect()
            ->route('developers.api-keys.index')
            ->with('new_api_token', $token);
    }

    public function destroy(Request $request, DeveloperApiKey $apiKey): RedirectResponse
    {
        abort_unless($apiKey->user_id === $request->user()->id, 404);

        if ($apiKey->revoked_at === null) {
            $apiKey->forceFill(['revoked_at' => now()])->save();
        }

        return redirect()
            ->route('developers.api-keys.index')
            ->with('status', 'API key revoked.');
    }

    private function generateToken(): string
    {
        $environment = app()->isProduction() ? 'live' : 'test';

        return sprintf('saints_%s_%s', $environment, Str::lower(bin2hex(random_bytes(32))));
    }
}
