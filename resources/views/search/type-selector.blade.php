@php($selectedLabel = $types[$selected] ?? reset($types))
@php($menuId = 'search-type-selector-menu-'.$form)

<span class="search-type-selector" data-search-type-selector>
    <input
        type="hidden"
        name="type"
        form="{{ $form }}"
        value="{{ $selected }}"
        data-search-type-input
    >
    <button
        class="search-type-selector-button"
        type="button"
        aria-haspopup="listbox"
        aria-expanded="false"
        aria-controls="{{ $menuId }}"
        data-search-type-button
    >
        <span class="search-type-selector-text" data-search-type-label>{{ $selectedLabel }}</span>
        <span class="search-type-selector-arrow" aria-hidden="true"></span>
    </button>

    <span class="search-type-selector-menu" id="{{ $menuId }}" role="listbox" data-search-type-menu hidden>
        @foreach ($types as $value => $label)
            <button
                class="search-type-selector-option"
                type="button"
                role="option"
                aria-selected="{{ $selected === $value ? 'true' : 'false' }}"
            data-search-type-option
            data-value="{{ $value }}"
            data-plural="{{ match ($value) {
                'church_father' => 'church fathers',
                'blessed' => 'blesseds',
                'pope' => 'popes',
                'venerable' => 'venerables',
                default => 'saints',
            } }}"
        >
            {{ $label }}
        </button>
        @endforeach
    </span>
</span>
