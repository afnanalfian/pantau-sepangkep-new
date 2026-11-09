<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\Toasts;
use App\Models\Team;
use App\Models\User;
use App\Services\UserImporter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Title('Kelola Pengguna')]
class Users extends Component
{
    use Toasts, WithFileUploads, WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $role = '';

    #[Url(except: '')]
    public string $status = '';

    // Form
    public bool $showForm = false;

    public ?int $editId = null;

    public array $form = [];

    public array $formTeams = [];

    public ?string $generatedPassword = null;

    // Import
    public ?string $importType = null; // akun | biodata

    public $importFile;

    public ?array $importResult = null;

    public ?int $confirmDeleteId = null;

    public function updating($name): void
    {
        if (in_array($name, ['search', 'role', 'status'])) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function teams()
    {
        return Team::orderBy('sort_order')->get();
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'total' => User::count(),
            'active' => User::where('is_active', true)->count(),
            'pending' => User::where('must_change_password', true)->count(),
            'no_bio' => User::whereNull('name')->count(),
        ];
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->editId = null;
        $this->generatedPassword = null;
        $this->form = [
            'username' => '', 'password' => Str::lower(Str::random(4)).random_int(1000, 9999), 'name' => '', 'nip' => '',
            'jabatan' => '', 'email' => '', 'is_admin' => false, 'is_pimpinan' => false, 'is_active' => true, 'monthly_target' => null,
        ];
        $this->formTeams = [];
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->resetValidation();
        $u = User::with('teams')->findOrFail($id);
        $this->editId = $u->id;
        $this->generatedPassword = null;
        $this->form = $u->only(['username', 'name', 'nip', 'jabatan', 'email', 'is_admin', 'is_pimpinan', 'is_active', 'monthly_target']) + ['password' => ''];
        $this->formTeams = $u->teams->mapWithKeys(fn ($t) => [$t->id => $t->pivot->role])->all();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->form['username'] = UserImporter::normalizeUsername((string) ($this->form['username'] ?? ''));
        $this->validate([
            'form.username' => ['required', 'string', 'max:100', Rule::unique('users', 'username')->ignore($this->editId)],
            'form.password' => [$this->editId ? 'nullable' : 'required', 'string', 'min:6'],
            'form.name' => 'nullable|string|max:150',
            'form.nip' => 'nullable|string|max:30',
            'form.jabatan' => 'nullable|string|max:150',
            'form.email' => 'nullable|email|max:150',
            'form.monthly_target' => 'nullable|integer|min:1|max:400',
        ], [], ['form.username' => 'username', 'form.password' => 'password', 'form.monthly_target' => 'target poin']);

        $data = collect($this->form)->only(['username', 'name', 'nip', 'jabatan', 'email', 'is_admin', 'is_pimpinan', 'is_active', 'monthly_target'])
            ->map(fn ($v) => $v === '' ? null : $v)->all();
        $data['is_admin'] = (bool) $data['is_admin'];
        $data['is_pimpinan'] = (bool) $data['is_pimpinan'];
        $data['is_active'] = (bool) $data['is_active'];

        if ($this->editId === Auth::id() && ! $data['is_admin']) {
            $this->addError('form.is_admin', 'Anda tidak bisa mencabut hak admin diri sendiri.');

            return;
        }

        if ($this->editId) {
            $user = User::findOrFail($this->editId);
            $user->update($data);
            if (! empty($this->form['password'])) {
                $user->forceFill(['password' => $this->form['password'], 'must_change_password' => true])->save();
                $this->generatedPassword = $this->form['password'];
            }
        } else {
            $user = User::create($data + ['password' => $this->form['password'], 'must_change_password' => true]);
            $this->generatedPassword = $this->form['password'];
        }

        $sync = [];
        foreach ($this->formTeams as $teamId => $role) {
            if ($role) {
                $sync[$teamId] = ['role' => $role === 'ketua' ? 'ketua' : 'anggota'];
            }
        }
        $user->teams()->sync($sync);

        $this->toast($this->editId ? 'Data pengguna diperbarui.' : 'Akun '.$user->username.' dibuat.');
        if (! $this->generatedPassword) {
            $this->showForm = false;
        }
        unset($this->stats);
    }

    public function toggleActive(int $id): void
    {
        abort_if($id === Auth::id(), 422, 'Tidak bisa menonaktifkan akun sendiri.');
        $u = User::findOrFail($id);
        $u->update(['is_active' => ! $u->is_active]);
        if (! $u->is_active) {
            \Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $u->id)->delete();
            $u->forceFill(['remember_token' => Str::random(60)])->save();
        }
        $this->toast($u->username.($u->is_active ? ' diaktifkan.' : ' dinonaktifkan & dikeluarkan dari semua perangkat.'));
    }

    public function resetPassword(int $id): void
    {
        $u = User::findOrFail($id);
        $pwd = Str::lower(Str::random(4)).random_int(1000, 9999);
        $u->forceFill(['password' => $pwd, 'must_change_password' => true])->save();
        $this->editId = $u->id;
        $this->form = ['username' => $u->username];
        $this->generatedPassword = $pwd;
        $this->showForm = true;
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmDeleteId = $id;
    }

    public function delete(): void
    {
        $id = $this->confirmDeleteId;
        abort_if($id === Auth::id(), 422, 'Tidak bisa menghapus akun sendiri.');
        $u = User::findOrFail($id);
        \Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $u->id)->delete();
        $u->teams()->detach();
        $u->pushSubscriptions()->delete();
        // Username dibebaskan agar bisa dipakai lagi; riwayat tugas tetap tersimpan.
        $u->forceFill(['username' => $u->username.'__hapus_'.$u->id, 'is_active' => false])->save();
        $u->delete();
        $this->confirmDeleteId = null;
        $this->toast('Akun dihapus. Riwayat tugasnya tetap tersimpan.');
        unset($this->stats);
    }

    public function openImport(string $type): void
    {
        $this->importType = $type;
        $this->importFile = null;
        $this->importResult = null;
        $this->resetValidation();
    }

    public function runImport(UserImporter $importer): void
    {
        $this->validate(['importFile' => 'required|file|mimes:xlsx,xls,csv|max:5120'], [], ['importFile' => 'file Excel']);
        $path = $this->importFile->getRealPath();
        $this->importResult = $this->importType === 'biodata'
            ? $importer->importBiodata($path)
            : $importer->importAccounts($path);
        $this->importFile = null;
        unset($this->stats);
        $this->toast('Import selesai.');
    }

    public function render()
    {
        $users = User::with('teams')
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w->where('username', 'like', "%{$this->search}%")
                ->orWhere('name', 'like', "%{$this->search}%")->orWhere('nip', 'like', "%{$this->search}%")))
            ->when($this->role === 'admin', fn ($q) => $q->where('is_admin', true))
            ->when($this->role === 'pimpinan', fn ($q) => $q->where('is_pimpinan', true))
            ->when($this->role === 'ketua', fn ($q) => $q->whereHas('ledTeams'))
            ->when($this->status === 'aktif', fn ($q) => $q->where('is_active', true))
            ->when($this->status === 'nonaktif', fn ($q) => $q->where('is_active', false))
            ->when($this->status === 'belum_ganti', fn ($q) => $q->where('must_change_password', true))
            ->orderByRaw('name IS NULL, name')->orderBy('username')
            ->paginate(25);

        return view('livewire.admin.users', compact('users'));
    }
}
