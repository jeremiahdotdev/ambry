<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $credentials = $this->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt($credentials, $this->remember)) {
            $this->reset('password');
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }
        session()->regenerate();
        $this->reset('password');
        $this->redirectIntended(route('developers.api-keys.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.login');
    }
}
