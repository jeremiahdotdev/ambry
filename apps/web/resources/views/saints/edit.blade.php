@php
    $field = fn (string $name, mixed $default = null): mixed => old($name, data_get($saint, $name, $default));
@endphp

<x-blank-page
    :title="'Edit '.$saint->displayName().' - '.config('app.name', 'Ambry')"
    page-class="saint-editor-page"
    :assets="[
        'resources/css/saints/editor.css',
    ]"
>
    <div class="saint-editor-shell">
        <header class="saint-editor-header">
            <div>
                <p class="eyebrow">Saints</p>
                <h1>Edit {{ $saint->displayName() }}</h1>
            </div>

            <nav class="saint-editor-nav" aria-label="Saint editor actions">
                <a href="{{ route('saints.profile', $saint) }}">View Saint</a>
                @if ($canManageStaff)
                    <a href="{{ route('saints.edit-staff.index') }}">Edit Staff</a>
                @endif
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

        <form method="POST" action="{{ route('saints.update', $saint) }}" class="saint-editor-form">
            @csrf
            @method('PATCH')

            <section class="saint-editor-panel" aria-labelledby="saint-editor-identity-title">
                <h2 id="saint-editor-identity-title">Identity</h2>

                <div class="saint-editor-grid">
                    <label>
                        <span>Name</span>
                        <input name="primary_name" type="text" value="{{ $field('primary_name') }}" maxlength="255" required>
                    </label>

                    <label>
                        <span>Slug</span>
                        <input name="slug" type="text" value="{{ $field('slug') }}" maxlength="255" pattern="[a-z0-9]+(-[a-z0-9]+)*" required>
                    </label>

                    <label>
                        <span>Status</span>
                        <select name="canonical_status" required>
                            @foreach ($statusOptions as $value => $label)
                                <option value="{{ $value }}" @selected($field('canonical_status') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        <span>Gender</span>
                        <input name="gender" type="text" value="{{ $field('gender') }}" maxlength="80">
                    </label>
                </div>

                <div class="saint-editor-checks">
                    <input name="is_martyr" type="hidden" value="0">
                    <label>
                        <input name="is_martyr" type="checkbox" value="1" @checked((bool) $field('is_martyr'))>
                        <span>Martyr</span>
                    </label>

                    <input name="is_doctor" type="hidden" value="0">
                    <label>
                        <input name="is_doctor" type="checkbox" value="1" @checked((bool) $field('is_doctor'))>
                        <span>Doctor of the Church</span>
                    </label>
                </div>
            </section>

            <section class="saint-editor-panel" aria-labelledby="saint-editor-dates-title">
                <h2 id="saint-editor-dates-title">Life Dates</h2>

                <div class="saint-editor-grid saint-editor-grid--dates">
                    <label>
                        <span>Birth Year</span>
                        <input name="birth_year" type="number" value="{{ $field('birth_year') }}" min="-10000" max="10000">
                    </label>

                    <label>
                        <span>Birth Qualifier</span>
                        <select name="birth_year_qualifier">
                            @foreach ($yearQualifierOptions as $value => $label)
                                <option value="{{ $value }}" @selected(($field('birth_year_qualifier') ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        <span>Death Year</span>
                        <input name="death_year" type="number" value="{{ $field('death_year') }}" min="-10000" max="10000">
                    </label>

                    <label>
                        <span>Death Qualifier</span>
                        <select name="death_year_qualifier">
                            @foreach ($yearQualifierOptions as $value => $label)
                                <option value="{{ $value }}" @selected(($field('death_year_qualifier') ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="saint-editor-span">
                        <span>Display Dates</span>
                        <input name="life_dates" type="text" value="{{ $field('life_dates') }}" maxlength="255">
                    </label>
                </div>
            </section>

            <section class="saint-editor-panel" aria-labelledby="saint-editor-copy-title">
                <h2 id="saint-editor-copy-title">Profile Copy</h2>

                <label>
                    <span>Subtitle</span>
                    <input name="profile_subtitle" type="text" value="{{ $field('profile_subtitle') }}" maxlength="255">
                </label>

                <label>
                    <span>Profile Summary</span>
                    <textarea name="profile_summary" rows="7">{{ $field('profile_summary') }}</textarea>
                </label>

                <label>
                    <span>Biography</span>
                    <textarea name="biography" rows="12">{{ $field('biography') }}</textarea>
                </label>
            </section>

            <section class="saint-editor-panel" aria-labelledby="saint-editor-design-title">
                <h2 id="saint-editor-design-title">Design</h2>

                <label>
                    <span>Page Variant</span>
                    <select name="image_page_variant">
                        @foreach ($imageVariantOptions as $value => $label)
                            <option value="{{ $value }}" @selected(($field('image_page_variant') ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </section>

            <div class="saint-editor-actions">
                <button type="submit">Save Saint</button>
                <a href="{{ route('saints.profile', $saint) }}">Cancel</a>
            </div>
        </form>
    </div>
</x-blank-page>
