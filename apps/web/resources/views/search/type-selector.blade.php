@php($selectedLabel = $types[$selected] ?? reset($types))
@php($menuId = 'search-type-selector-menu-'.$form)

<span class="search-type-selector" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false; $refs.button.focus()">
    <input type="hidden" name="type" form="{{ $form }}" value="{{ $selected }}">
    <button x-ref="button" class="search-type-selector-button" type="button"
        aria-label="Search Filter: {{ $selectedLabel }}" aria-haspopup="listbox"
        :aria-expanded="open" aria-controls="{{ $menuId }}"
        @click="open = !open; if (open) $nextTick(() => $refs.menu.querySelector('[aria-selected=true]')?.focus())">
        <span class="search-type-selector-text">{{ $selectedLabel }}</span>
        <span class="search-type-selector-arrow" aria-hidden="true"></span>
    </button>
    <span x-ref="menu" class="search-type-selector-menu" id="{{ $menuId }}" role="listbox" aria-label="Search Filter" x-show="open" x-cloak>
        @foreach ($types as $value => $label)
            <button class="search-type-selector-option" type="button" role="option"
                aria-selected="{{ $selected === $value ? 'true' : 'false' }}"
                wire:click="$set('type', '{{ $value }}')" @click="open = false; $refs.button.focus()">
                {{ $label }}
            </button>
        @endforeach
    </span>
</span>
