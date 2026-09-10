<x-blank-page
    :title="'Developer Login - '.config('app.name', 'Ambry')"
    page-class="developer-auth-page"
    :assets="[
        'resources/css/developers/auth.css',
    ]"
>
    <livewire:login />
</x-blank-page>
