<div>
    <x-auth.form wire:submit="login"
        title="Login"
        :action="route('login.store')"
        submit="Login"
    >
        <x-form.input name="email" wire:model="email" label="Email" type="email" autocomplete="email" autofocus />
        <x-form.input name="password" wire:model="password" label="Password" type="password" autocomplete="current-password" />

        <label class="developer-auth-checkbox">
            <input name="remember" wire:model="remember" type="checkbox" value="1">
            <span>Remember me</span>
        </label>

        <x-slot:footer>
            <span>Need an account?</span>
            <a wire:navigate href="{{ route('register') }}">Signup</a>
        </x-slot:footer>
    </x-auth.form>
</div>
