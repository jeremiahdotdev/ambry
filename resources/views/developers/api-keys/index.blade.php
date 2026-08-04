@php
    use Illuminate\Support\Str;
@endphp

<x-blank-page
    :title="'Developer API Keys - '.config('app.name', 'Ambry')"
    page-class="developer-page"
    :assets="[
        'resources/css/developers/api-keys.css',
    ]"
>
    <div class="developer-shell">
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

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <a
                        href="{{ route('logout') }}"
                        class="developer-logout-link"
                        onclick="event.preventDefault(); this.closest('form').submit();"
                    >
                        Logout
                    </a>
                </form>
            </div>
        </section>

        @if (session('status'))
            <div class="developer-alert developer-alert-success" role="status">
                {{ session('status') }}
            </div>
        @endif

        @if ($newToken)
            <section class="developer-token" aria-label="New API token">
                <h2>Copy your new API key</h2>
                <p>This is the only time the full token will be shown.</p>
                <code>{{ $newToken }}</code>
            </section>
        @endif

        @if ($errors->any())
            <div class="message" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="developer-panel" aria-label="Create an API key">
            <h2>Create Key</h2>
            <form method="POST" action="{{ route('developers.api-keys.store') }}" class="developer-form">
                @csrf

                <x-form.input name="name" label="Name" maxlength="80" />
                <x-form.input name="expires_at" label="Expiration date" type="date" :required="false" />
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

                        <article class="developer-key">
                            <div>
                                <h3>{{ $apiKey->name }}</h3>
                                <p><code>{{ $apiKey->prefix }}...</code></p>
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
                                <form method="POST" action="{{ route('developers.api-keys.destroy', $apiKey) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="developer-danger-button">Revoke</button>
                                </form>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</x-blank-page>
