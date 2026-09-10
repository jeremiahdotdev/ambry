@php
    use Illuminate\Support\Str;
@endphp

    <div class="saint-editor-shell">
        <p wire:loading role="status" class="request-status">Working…</p>
        <header class="saint-editor-header">
            <div>
                <p class="eyebrow">Saints</p>
                <h1>Edit Staff</h1>
            </div>

            <nav class="saint-editor-nav" aria-label="Saint staff actions">
                <a wire:navigate href="{{ route('search.index') }}">Saints</a>
            </nav>
        </header>

        @if ($status)
            <div class="saint-editor-alert saint-editor-alert-success" role="status">
                {{ $status }}
            </div>
        @endif

        @if ($errors->any())
            <div class="message" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="saint-editor-panel" aria-labelledby="saint-editor-add-staff-title">
            <h2 id="saint-editor-add-staff-title">Add Editor</h2>

            <form wire:submit="add" class="saint-staff-form">
                <label>
                    <span>Email</span>
                    <input name="email" wire:model="email" type="email" value="{{ old('email') }}" maxlength="255" required>
                </label>
                <x-form.button>Add Email</x-form.button>
            </form>
        </section>

        <section class="saint-editor-panel" aria-labelledby="saint-editor-staff-list-title">
            <div class="saint-editor-section-heading">
                <h2 id="saint-editor-staff-list-title">Current Staff</h2>
                <span>{{ $staff->count() }} {{ Str::plural('email', $staff->count()) }}</span>
            </div>

            <div class="saint-staff-list">
                @foreach ($staff as $permission)
                    <article class="saint-staff-row" wire:key="permission-{{ $permission->id }}">
                        <div>
                            <h3>{{ $permission->email }}</h3>
                            <p>{{ Str::headline($permission->role) }}</p>
                        </div>

                        @if ($permission->isOwner())
                            <span class="saint-staff-owner">Owner</span>
                        @else
                            <form wire:submit="remove({{ $permission->id }})">
                                <x-form.button variant="danger">Remove</x-form.button>
                            </form>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    </div>
