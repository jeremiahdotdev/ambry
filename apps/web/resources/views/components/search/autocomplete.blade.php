@props(['id' => 'q'])

<div class="search-input-wrap search-autocomplete" data-search-autocomplete data-url="{{ route('search.suggestions') }}" x-data="searchAutocomplete">
    <label for="{{ $id }}">Search...</label>
    <span class="search-icon" aria-hidden="true"></span>
    <input x-ref="input" id="{{ $id }}" name="q" type="search" wire:model="query" placeholder="Search..."
        maxlength="200" autocomplete="off" role="combobox" aria-autocomplete="list"
        :aria-expanded="showSuggestions"
        :aria-activedescendant="showSuggestions && active >= 0 ? '{{ $id }}-suggestion-' + active : null"
        aria-controls="{{ $id }}-suggestions" autofocus
        @input="input($event)" @focus="open = true" @blur="open = false; active = -1"
        @compositionstart="open = false" @compositionend="input($event)"
        @keydown.escape.prevent="open = false; active = -1"
        @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
        @keydown.enter="choose($event)">
    <ul id="{{ $id }}-suggestions" x-ref="list" wire:ignore class="search-autocomplete-list" role="listbox" aria-label="Search suggestions" x-show="showSuggestions" x-cloak>
        <template x-for="(suggestion, index) in suggestions" :key="suggestion.url">
            <li :id="'{{ $id }}-suggestion-' + index" role="option" :aria-selected="active === index"
                @mousedown.prevent @click="Livewire.navigate(suggestion.url)">
                <span x-text="suggestion.name"></span>
            </li>
        </template>
    </ul>
    <span class="search-autocomplete-status" wire:ignore role="status" aria-live="polite" x-show="open && currentQuery === resolvedQuery"
        x-text="suggestions.length ? suggestions.length + ' suggestions available. Use the up and down arrow keys to browse.' : 'No suggestions. Press Enter to search.'"></span>
</div>
