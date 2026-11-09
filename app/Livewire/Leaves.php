<?php

namespace App\Livewire;

use App\Livewire\Concerns\Toasts;
use App\Models\Leave;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Cuti & Dinas Luar')]
class Leaves extends Component
{
    use Toasts;

    public ?int $user_id = null;

    public string $type = 'dinas_luar';

    public ?string $start_date = null;

    public ?string $end_date = null;

    public string $note = '';

    public bool $showForm = false;

    public function mount(): void
    {
        $this->user_id = Auth::id();
        $this->start_date = $this->end_date = now()->toDateString();
    }

    protected function canManageAll(): bool
    {
        $u = Auth::user();

        return $u->is_admin || $u->is_pimpinan;
    }

    public function save(): void
    {
        $data = $this->validate([
            'user_id' => 'required|exists:users,id',
            'type' => 'required|in:'.implode(',', array_keys(Leave::TYPES)),
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'note' => 'nullable|string|max:255',
        ], [], ['start_date' => 'tanggal mulai', 'end_date' => 'tanggal selesai']);

        if (! $this->canManageAll()) {
            $data['user_id'] = Auth::id();
        }
        Leave::create($data + ['created_by' => Auth::id()]);
        $this->showForm = false;
        $this->note = '';
        $this->toast('Jadwal '.Leave::TYPES[$data['type']].' tersimpan. Target poin & alur persetujuan otomatis menyesuaikan.');
    }

    public function delete(int $id): void
    {
        $l = Leave::findOrFail($id);
        abort_unless($this->canManageAll() || $l->user_id === Auth::id(), 403);
        $l->delete();
        $this->toast('Jadwal dihapus.');
    }

    public function render()
    {
        $q = Leave::with('user')->orderByDesc('start_date');
        if (! $this->canManageAll()) {
            $q->where('user_id', Auth::id());
        }

        return view('livewire.leaves', [
            'items' => $q->limit(100)->get(),
            'users' => $this->canManageAll() ? User::active()->orderBy('name')->get() : collect(),
            'manageAll' => $this->canManageAll(),
            'today' => Leave::with('user')->whereDate('start_date', '<=', now())->whereDate('end_date', '>=', now())->get(),
        ]);
    }
}
