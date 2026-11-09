<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Masuk')]
class Login extends Component
{
    public string $username = '';

    public string $password = '';

    public function login()
    {
        $this->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ], [], ['username' => 'username', 'password' => 'password']);

        $username = Str::lower(trim($this->username));
        $key = 'login:'.$username.'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 6)) {
            throw ValidationException::withMessages([
                'username' => 'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.',
            ]);
        }

        $user = User::where('username', $username)->first();
        if ($user && ! $user->is_active) {
            throw ValidationException::withMessages(['username' => 'Akun Anda dinonaktifkan. Hubungi administrator.']);
        }

        // Sesi dibuat "ingat saya" secara permanen: pengguna tetap login sampai logout.
        if (! Auth::attempt(['username' => $username, 'password' => $this->password, 'is_active' => true], true)) {
            RateLimiter::hit($key, 120);
            throw ValidationException::withMessages(['username' => 'Username atau password salah.']);
        }

        RateLimiter::clear($key);
        session()->regenerate();
        Auth::user()->forceFill(['last_login_at' => now(), 'last_seen_at' => now()])->saveQuietly();

        return $this->redirectIntended(
            Auth::user()->must_change_password ? route('password.change') : route('dashboard'),
            navigate: false
        );
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
