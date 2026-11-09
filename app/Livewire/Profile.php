<?php

namespace App\Livewire;

use App\Livewire\Concerns\Toasts;
use App\Services\WorkloadService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Profil Saya')]
class Profile extends Component
{
    use Toasts, WithFileUploads;

    public string $email = '';

    public string $phone = '';

    public $avatar;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $u = Auth::user();
        $this->email = (string) $u->email;
        $this->phone = (string) $u->phone;
    }

    public function saveContact(): void
    {
        $data = $this->validate([
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:30',
        ]);
        Auth::user()->update($data);
        $this->toast('Kontak diperbarui.');
    }

    public function updatedAvatar(): void
    {
        $this->validate(['avatar' => 'image|max:4096']);
        $u = Auth::user();
        if ($u->avatar_path) {
            Storage::disk('public')->delete($u->avatar_path);
        }
        $u->update(['avatar_path' => $this->avatar->store('avatars', 'public')]);
        $this->avatar = null;
        $this->toast('Foto profil diperbarui.');
    }

    public function changePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [], ['current_password' => 'password lama', 'password' => 'password baru']);

        Auth::user()->forceFill(['password' => $this->password, 'password_changed_at' => now()])->save();
        $this->reset('current_password', 'password', 'password_confirmation');
        $this->toast('Password berhasil diganti.');
    }

    public function render()
    {
        $u = Auth::user()->load('teams');

        return view('livewire.profile', [
            'user' => $u,
            'stats' => app(WorkloadService::class)->forUser($u),
            'leave' => $u->currentLeave(),
        ]);
    }
}
