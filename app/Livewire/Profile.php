<?php

namespace App\Livewire;

use App\Livewire\Concerns\Toasts;
use App\Services\GoogleDriveService;
use App\Services\KinerjaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
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
        $u = Auth::user();
        $emailBerubah = mb_strtolower((string) $u->email) !== mb_strtolower((string) ($data['email'] ?? ''));
        $u->update($data);
        $this->toast('Kontak diperbarui.');

        // Email baru → bagikan ulang folder Drive ke email itu
        $drive = app(GoogleDriveService::class);
        if ($emailBerubah && ! empty($data['email']) && $drive->enabled() && $drive->cachedUserFolderId($u)) {
            try {
                $drive->userFolder($u->fresh());
            } catch (\Throwable) {
                // tidak menghalangi penyimpanan kontak
            }
        }
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
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [], ['current_password' => 'password lama', 'password' => 'password baru']);

        Auth::user()->forceFill(['password' => $this->password, 'password_changed_at' => now()])->save();
        $this->reset('current_password', 'password', 'password_confirmation');
        $this->toast('Password berhasil diganti.');
    }

    /** Buka folder Drive pengguna (dibuat & dibagikan ke email pengguna bila belum). */
    public function bukaFolderDrive(GoogleDriveService $drive): void
    {
        if (! $drive->enabled()) {
            $this->toast('Google Drive belum diaktifkan admin.', 'error');

            return;
        }
        try {
            $f = $drive->userFolder(Auth::user());
        } catch (\Throwable $e) {
            report($e);
            $this->toast('Folder Drive belum bisa dibuka. Coba lagi nanti atau hubungi admin.', 'error');

            return;
        }
        if (! $f['dibagikan'] && Auth::user()->email) {
            $this->toast('Folder siap, tetapi belum bisa dibagikan ke email Anda. Pastikan email adalah akun Google.', 'info');
        }
    }

    public function render()
    {
        $u = Auth::user()->load('teams');

        return view('livewire.profile', [
            'user' => $u,
            'riwayat' => app(KinerjaService::class)->riwayat($u, 12),
            'leave' => $u->currentLeave(),
            'driveAktif' => app(GoogleDriveService::class)->enabled(),
            'driveFolderId' => app(GoogleDriveService::class)->cachedUserFolderId($u),
            // Folder dianggap siap dibuka bila sudah dibagikan ke email pengguna yang sekarang
            'driveSiap' => \App\Models\DriveFolder::where('key', 'share:user:'.$u->id)->value('drive_id') === mb_strtolower((string) $u->email) && $u->email,
        ]);
    }
}
