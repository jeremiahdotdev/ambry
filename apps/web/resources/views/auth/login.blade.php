<x-blank-page
    :title="'Developer Login - '.config('app.name', 'Ambry')"
    page-class="developer-auth-page"
    :assets="[
        'resources/css/developers/auth.css',
    ]"
>
    <x-auth.form
        title="Login"
        :action="route('login.store')"
        submit="Login"
    >
        <x-form.input name="email" label="Email" type="email" autocomplete="email" autofocus />
        <x-form.input name="password" label="Password" type="password" autocomplete="current-password" />

        <label class="developer-auth-checkbox">
            <input name="remember" type="checkbox" value="1">
            <span>Remember me</span>
        </label>

        <x-slot:footer>
            <span>Need an account?</span>
            <a href="{{ route('register') }}">Signup</a>
        </x-slot:footer>
    </x-auth.form>
</x-blank-page>
