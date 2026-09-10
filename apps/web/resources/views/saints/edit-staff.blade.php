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
    <livewire:editor-staff />
</x-blank-page>
