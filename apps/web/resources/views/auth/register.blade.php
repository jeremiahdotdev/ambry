<x-blank-page
    :title="'Developer Signup - '.config('app.name', 'Ambry')"
    page-class="developer-auth-page"
    :assets="[
        'resources/css/developers/auth.css',
    ]"
>
    <livewire:register />
</x-blank-page>
