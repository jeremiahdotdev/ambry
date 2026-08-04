<x-blank-page
    :title="'Developer Signup - '.config('app.name', 'Ambry')"
    page-class="developer-auth-page"
    :assets="[
        'resources/css/developers/auth.css',
    ]"
>
    <x-auth.form
        title="Signup"
        :action="route('register.store')"
        submit="Create Account"
    >
        <x-form.input name="name" label="Name" autocomplete="name" autofocus />
        <x-form.input name="email" label="Email" type="email" autocomplete="email" />
        <x-form.input name="password" label="Password" type="password" autocomplete="new-password" />
        <x-form.input name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" />

        <x-slot:footer>
            <span>Already have an account?</span>
            <a href="{{ route('login') }}">Login</a>
        </x-slot:footer>
    </x-auth.form>
</x-blank-page>
