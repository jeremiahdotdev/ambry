@php
    use Illuminate\Support\Str;
@endphp

<x-blank-page
    :title="'Developer API Keys - '.config('app.name', 'Ambry')"
    page-class="developer-page"
    :assets="[
        'resources/css/developers/api-keys.css',
    ]"
>
    <livewire:api-keys />
</x-blank-page>
