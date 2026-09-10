<a @isset($href) wire:navigate @else x-data @click.prevent="window.history.length > 1 ? window.history.back() : Livewire.navigate($el.href)" @endisset class="{{ $class ?? 'back-to-search-link' }}" href="{{ $href ?? route('search.index') }}">
    <span aria-hidden="true">←</span>
    <span>{{ $label ?? 'Back to search' }}</span>
</a>
