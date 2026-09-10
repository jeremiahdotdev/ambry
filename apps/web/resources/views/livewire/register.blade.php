<div>
    <x-auth.form wire:submit="register"
        title="Signup"
        :action="route('register.store')"
        submit="Create Account"
    >
        <x-form.input name="name" wire:model="name" label="Name" autocomplete="name" autofocus />
        <x-form.input name="email" wire:model="email" label="Email" type="email" autocomplete="email" />
        <x-form.input name="password" wire:model="password" label="Password" type="password" autocomplete="new-password" />
        <x-form.input name="password_confirmation" wire:model="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" />

        <x-slot:footer>
            <span>Already have an account?</span>
            <a wire:navigate href="{{ route('login') }}">Login</a>
        </x-slot:footer>
    </x-auth.form>
</div>
