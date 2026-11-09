<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Ganti Password')]
class ChangePassword extends Component
{
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function save()
    {
        $user = Auth::user();
        $rules = [
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers(), 'different:current_password'],
        ];
        if (! $user->must_change_password) {
            $rules['current_password'] = ['required', 'current_password'];
        }
        $this->validate($rules, [
            'password.different' => 'Password baru harus berbeda dari password lama.',
        ], ['password' => 'password baru', 'current_password' => 'password lama']);

        if (Hash::check($this->password, $user->password)) {
            $this->addError('password', 'Password baru tidak boleh sama dengan password awal.');

            return;
        }

        $user->forceFill([
            'password' => $this->password,
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();

        session()->flash('toast', 'Password berhasil diganti. Selamat datang!');

        return $this->redirectRoute('dashboard');
    }

    public function render()
    {
        return view('livewire.auth.change-password', ['forced' => Auth::user()->must_change_password]);
    }
}
