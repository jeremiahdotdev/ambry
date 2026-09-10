    <div class="saint-editor-shell">
        <p wire:loading role="status" class="request-status">Working…</p>
        <header class="saint-editor-header">
            <div>
                <p class="eyebrow">Saints</p>
                <h1>Edit {{ $saint->displayName() }}</h1>
            </div>

            <nav class="saint-editor-nav" aria-label="Saint editor actions">
                <a wire:navigate href="{{ route('saints.profile', $saint) }}">View Saint</a>
                @if ($canManageStaff)
                    <a wire:navigate href="{{ route('saints.edit-staff.index') }}">Edit Staff</a>
                @endif
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

        <form wire:submit="save" class="saint-editor-form">

            <section class="saint-editor-panel" aria-labelledby="saint-editor-identity-title">
                <h2 id="saint-editor-identity-title">Identity</h2>

                <div class="saint-editor-grid">
                    <label>
                        <span>Name</span>
                        <input name="primary_name" wire:model="form.primary_name" type="text" maxlength="255" required>
                    
                        @error('form.primary_name') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label>
                        <span>Slug</span>
                        <input name="slug" wire:model="form.slug" type="text" maxlength="255" pattern="[a-z0-9]+(-[a-z0-9]+)*" required>
                    
                        @error('form.slug') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label>
                        <span>Status</span>
                        <select name="canonical_status" wire:model="form.canonical_status" required>
                            @foreach ($statusOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    
                        @error('form.canonical_status') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label>
                        <span>Gender</span>
                        <input name="gender" wire:model="form.gender" type="text" maxlength="80">
                    
                        @error('form.gender') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                </div>

                <div class="saint-editor-checks">
                    <label>
                        <input name="is_martyr" wire:model="form.is_martyr" type="checkbox" value="1">
                        <span>Martyr</span>
                    
                        @error('form.is_martyr') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label>
                        <input name="is_doctor" wire:model="form.is_doctor" type="checkbox" value="1">
                        <span>Doctor of the Church</span>
                    
                        @error('form.is_doctor') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                </div>
            </section>

            <section class="saint-editor-panel" aria-labelledby="saint-editor-dates-title">
                <h2 id="saint-editor-dates-title">Life Dates</h2>

                <div class="saint-editor-grid saint-editor-grid--dates">
                    <label>
                        <span>Birth Year</span>
                        <input name="birth_year" wire:model="form.birth_year" type="number" min="-10000" max="10000">
                    
                        @error('form.birth_year') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label>
                        <span>Birth Qualifier</span>
                        <select name="birth_year_qualifier" wire:model="form.birth_year_qualifier">
                            @foreach ($yearQualifierOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    
                        @error('form.birth_year_qualifier') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label>
                        <span>Death Year</span>
                        <input name="death_year" wire:model="form.death_year" type="number" min="-10000" max="10000">
                    
                        @error('form.death_year') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label>
                        <span>Death Qualifier</span>
                        <select name="death_year_qualifier" wire:model="form.death_year_qualifier">
                            @foreach ($yearQualifierOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    
                        @error('form.death_year_qualifier') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                    <label class="saint-editor-span">
                        <span>Display Dates</span>
                        <input name="life_dates" wire:model="form.life_dates" type="text" maxlength="255">
                    
                        @error('form.life_dates') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                </div>
            </section>

            <section class="saint-editor-panel" aria-labelledby="saint-editor-copy-title">
                <h2 id="saint-editor-copy-title">Profile Copy</h2>

                <label>
                    <span>Subtitle</span>
                    <input name="profile_subtitle" wire:model="form.profile_subtitle" type="text" maxlength="255">
                
                        @error('form.profile_subtitle') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                <label>
                    <span>Profile Summary</span>
                    <textarea name="profile_summary" wire:model="form.profile_summary" rows="7"></textarea>
                
                        @error('form.profile_summary') <span class="field-error">{{ $message }}</span> @enderror
                    </label>

                <label>
                    <span>Biography</span>
                    <textarea name="biography" wire:model="form.biography" rows="12"></textarea>
                
                        @error('form.biography') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
            </section>

            <section class="saint-editor-panel" aria-labelledby="saint-editor-design-title">
                <h2 id="saint-editor-design-title">Design</h2>

                <label>
                    <span>Page Variant</span>
                    <select name="image_page_variant" wire:model="form.image_page_variant">
                        @foreach ($imageVariantOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                
                        @error('form.image_page_variant') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
            </section>

            <div class="saint-editor-actions">
                <x-form.button>Save Saint</x-form.button>
                <a wire:navigate href="{{ route('saints.profile', $saint) }}">Cancel</a>
            </div>
        </form>
    </div>
