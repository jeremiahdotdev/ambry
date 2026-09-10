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
    <livewire:saint-editor :saint="$saint" />
</x-blank-page>
