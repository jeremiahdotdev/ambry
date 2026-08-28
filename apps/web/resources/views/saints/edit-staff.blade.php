@php
    use Illuminate\Support\Str;
@endphp

<x-blank-page
    :title="'Edit Staff - '.config('app.name', 'Ambry')"
    page-class="saint-editor-page"
    :assets="[
        'resources/css/saints/editor.css',
    ]"
>
    <div class="saint-editor-shell">
        <header class="saint-editor-header">
            <div>
                <p class="eyebrow">Saints</p>
                <h1>Edit Staff</h1>
            </div>

            <nav class="saint-editor-nav" aria-label="Saint staff actions">
                <a href="{{ route('search.index') }}">Saints</a>
            </nav>
        </header>

        @if (session('status'))
            <div class="saint-editor-alert saint-editor-alert-success" role="status">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="message" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="saint-editor-panel" aria-labelledby="saint-editor-add-staff-title">
            <h2 id="saint-editor-add-staff-title">Add Editor</h2>

            <form method="POST" action="{{ route('saints.edit-staff.store') }}" class="saint-staff-form">
                @csrf
                <label>
                    <span>Email</span>
                    <input name="email" type="email" value="{{ old('email') }}" maxlength="255" required>
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
                    <article class="saint-staff-row">
                        <div>
                            <h3>{{ $permission->email }}</h3>
                            <p>{{ Str::headline($permission->role) }}</p>
                        </div>

                        @if ($permission->isOwner())
                            <span class="saint-staff-owner">Owner</span>
                        @else
                            <form method="POST" action="{{ route('saints.edit-staff.destroy', $permission) }}">
                                @csrf
                                @method('DELETE')
                                <x-form.button variant="danger">Remove</x-form.button>
                            </form>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    </div>
</x-blank-page>
