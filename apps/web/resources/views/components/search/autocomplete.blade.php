@props(['suggestions' => [], 'id' => 'q'])

<div class="search-input-wrap search-autocomplete" data-search-autocomplete x-data="searchAutocomplete">
    <label for="{{ $id }}">Search...</label>
    <span class="search-icon" aria-hidden="true"></span>
    <input id="{{ $id }}" name="q" type="search" wire:model.live.debounce.200ms="query" placeholder="Search..."
        maxlength="200" autocomplete="off" role="combobox" aria-autocomplete="list"
        :aria-expanded="open && $wire.suggestions.length > 0"
        :aria-activedescendant="active >= 0 ? '{{ $id }}-suggestion-' + active : null"
        aria-controls="{{ $id }}-suggestions" autofocus
        @input="active = -1; open = true" @focus="open = true" @blur="open = false; active = -1"
        @compositionstart="open = false" @compositionend="open = true"
        @keydown.escape.prevent="open = false; active = -1"
        @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
        @keydown.enter="choose($event)">
    <ul id="{{ $id }}-suggestions" x-ref="list" class="search-autocomplete-list" role="listbox" aria-label="Search suggestions" x-show="open && $wire.suggestions.length > 0" x-cloak>
        @foreach ($suggestions as $suggestion)
            <li id="{{ $id }}-suggestion-{{ $loop->index }}" role="option"
                wire:key="suggestion-{{ $suggestion['url'] }}"
                :aria-selected="active === {{ $loop->index }}"
                data-url="{{ $suggestion['url'] }}"
                @mousedown.prevent @click="Livewire.navigate($el.dataset.url)">
                <span class="search-autocomplete-type">{{ $suggestion['type'] }}</span>
                <span>{{ $suggestion['name'] }}</span>
            </li>
        @endforeach
    </ul>
    <span class="search-autocomplete-status" role="status" aria-live="polite" x-show="open">
        @if (count($suggestions))
            {{ count($suggestions) }} suggestions available. Use the up and down arrow keys to browse.
        @elseif (mb_strlen($this->query) >= 2)
            No suggestions. Press Enter to search.
        @endif
    </span>
</div>
