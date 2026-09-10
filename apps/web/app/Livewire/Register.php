<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class Register extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function register(): void
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
        $user = User::create($validated);
        event(new Registered($user));
        Auth::login($user);
        session()->regenerate();
        $this->reset('password', 'password_confirmation');
        $this->redirectIntended(route('developers.api-keys.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.register');
    }
}
