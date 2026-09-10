<?php

namespace App\Http\Controllers;

use App\Models\DeveloperApiKey;
use App\Services\DeveloperApiKeyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $token = app(DeveloperApiKeyService::class)->create($request->user(), $validated);

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
}
