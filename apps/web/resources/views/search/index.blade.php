<x-blank-page
    :title="config('app.name', 'Ambry')"
    :show-footer="true"
    :assets="[
        'resources/css/search/type-selector.css',
        'resources/css/search/results.css',
    ]"
>
    <livewire:search />
</x-blank-page>
