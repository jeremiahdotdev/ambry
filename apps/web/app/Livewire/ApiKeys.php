<?php

namespace App\Livewire;

use App\Services\DeveloperApiKeyService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ApiKeys extends Component
{
    public string $name = '';

    public string $expires_at = '';

    public string $status = '';

    public bool $newToken = false;

    public function mount(): void
    {
        $this->newToken = session()->has('new_api_token');
    }

    public function create(): void
    {
        abort_unless(auth()->check(), 403);
        $validated = $this->validate([
            'name' => 'required|string|max:80',
            'expires_at' => 'nullable|date|after:today',
        ]);
        app(DeveloperApiKeyService::class)->create(auth()->user(), $validated);
        // Preserve the existing UI: raw tokens are never rendered or serialized.
        $this->newToken = true;
        $this->reset('name', 'expires_at', 'status');
    }

    public function revoke(int $id): void
    {
        abort_unless(auth()->check(), 403);
        $key = auth()->user()->developerApiKeys()->find($id);
        abort_unless($key, 404);
        if ($key->revoked_at === null) {
            $key->forceFill(['revoked_at' => now()])->save();
        }
        $this->newToken = false;
        $this->status = 'API key revoked.';
    }

    public function logout(): void
    {
        Auth::guard('web')->logout();
        session()->invalidate();
        session()->regenerateToken();
        $this->redirectRoute('login', navigate: true);
    }

    public function render()
    {
        abort_unless(auth()->check(), 403);

        return view('livewire.api-keys', [
            'apiKeys' => auth()->user()->developerApiKeys()->latest()->get(),
            'apiDocumentationUrl' => config('services.saints_api.url'),
        ]);
    }
}
