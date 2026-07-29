<a class="{{ $class ?? 'back-to-search-link' }}" href="{{ $href ?? 'javascript:history.back()' }}">
    <span aria-hidden="true">←</span>
    <span>{{ $label ?? 'Back to search' }}</span>
</a>
