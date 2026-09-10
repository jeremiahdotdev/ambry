@php
    use Illuminate\Support\Str;
@endphp

    <div class="developer-shell">
        <p wire:loading role="status" class="request-status">Working…</p>
        <section class="developer-hero">
            <div>
                <p class="eyebrow">Developers</p>
                <h1>API Keys</h1>
                <p class="lede">Create and revoke keys for the Ambry API.</p>
            </div>

            <div class="developer-actions">
                @if ($apiDocumentationUrl)
                    <a class="developer-docs-link" href="{{ $apiDocumentationUrl }}" target="_blank" rel="noreferrer">
                        API documentation
                    </a>
                @endif

                <form wire:submit="logout">
                    <button type="submit" class="developer-logout-link">Logout</button>
                </form>
            </div>
        </section>

        @if ($status)
            <div class="developer-alert developer-alert-success" role="status">
                {{ $status }}
            </div>
        @endif

        @if ($newToken)
            <section class="developer-token" aria-label="New API token">
                <h2>API key created</h2>
                <p>The key value is hidden and cannot be viewed again.</p>
            </section>
        @endif

        @if ($errors->any())
            <div class="message" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="developer-panel" aria-label="Create an API key">
            <h2>Create Key</h2>
            <form wire:submit="create" class="developer-form">

                <x-form.input name="name" wire:model="name" label="Name" maxlength="80" />
                <x-form.input name="expires_at" wire:model="expires_at" label="Expiration date" type="date" :required="false" />
                <x-form.button>Create API Key</x-form.button>
            </form>
        </section>

        <section class="developer-panel" aria-label="Existing API keys">
            <div class="developer-section-heading">
                <h2>Existing Keys</h2>
                <span>{{ $apiKeys->count() }} {{ Str::plural('key', $apiKeys->count()) }}</span>
            </div>

            @if ($apiKeys->isEmpty())
                <p class="empty">No API keys yet.</p>
            @else
                <div class="developer-key-list">
                    @foreach ($apiKeys as $apiKey)
                        @php
                            $status = match (true) {
                                $apiKey->isRevoked() => 'revoked',
                                $apiKey->isExpired() => 'expired',
                                default => 'active',
                            };
                        @endphp

                        <article class="developer-key" wire:key="api-key-{{ $apiKey->id }}">
                            <div>
                                <h3>{{ $apiKey->name }}</h3>
                            </div>

                            <dl>
                                <div>
                                    <dt>Status</dt>
                                    <dd><span class="developer-status developer-status-{{ $status }}">{{ Str::headline($status) }}</span></dd>
                                </div>
                                <div>
                                    <dt>Last used</dt>
                                    <dd>{{ $apiKey->last_used_at?->toFormattedDateString() ?? 'Never' }}</dd>
                                </div>
                                <div>
                                    <dt>Expires</dt>
                                    <dd>{{ $apiKey->expires_at?->toFormattedDateString() ?? 'Never' }}</dd>
                                </div>
                            </dl>

                            @if (! $apiKey->isRevoked())
                                <form wire:submit="revoke({{ $apiKey->id }})">
                                    <x-form.button variant="danger">Revoke</x-form.button>
                                </form>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
