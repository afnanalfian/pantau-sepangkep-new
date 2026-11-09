<?php

namespace App\Livewire\Tasks;

use App\Livewire\Concerns\Toasts;
use App\Models\Task;
use App\Services\TaskService;
use App\Support\Settings;
use App\Support\WorkingDays;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Persetujuan & Verifikasi')]
class Approvals extends Component
{
    use Toasts;

    #[Url]
    public string $tab = 'review'; // review | pengajuan | perpanjangan | semua

    public ?int $noteFor = null;

    public string $noteType = '';

    public string $note = '';

    protected function base()
    {
        $u = Auth::user();
        $q = Task::with(['assignee', 'team', 'verifier'])->withCount('attachments');
        if (! ($this->tab === 'semua' && $u->is_pimpinan)) {
            $q->where('verifier_id', $u->id);
        }

        return $q;
    }

    #[Computed]
    public function counts(): array
    {
        $u = Auth::user();

        return [
            'review' => Task::where('verifier_id', $u->id)->where('status', 'review')->count(),
            'pengajuan' => Task::where('verifier_id', $u->id)->where('status', 'pengajuan')->count(),
            'perpanjangan' => $this->extensionQuery()->count(),
        ];
    }

    protected function extensionQuery()
    {
        $u = Auth::user();

        return Task::with(['assignee'])->where('extension_reason', 'like', '[DIAJUKAN]%')
            ->where(fn ($w) => $w->where('creator_id', $u->id)->orWhere('verifier_id', $u->id)
                ->when($u->is_pimpinan, fn ($x) => $x->orWhereRaw('1=1')));
    }

    #[Computed]
    public function items()
    {
        return match ($this->tab) {
            'pengajuan' => $this->base()->where('status', 'pengajuan')->oldest('submitted_at')->get(),
            'perpanjangan' => $this->extensionQuery()->get(),
            'semua' => $this->base()->whereIn('status', ['review', 'pengajuan'])->oldest('submitted_at')->get(),
            default => $this->base()->where('status', 'review')->oldest('submitted_at')->get(),
        };
    }

    public function slaDeadline(Task $t)
    {
        return $t->submitted_at ? WorkingDays::addWorkingHours($t->submitted_at, (int) Settings::get('sla_hours', 48)) : null;
    }

    public function approve(int $id): void
    {
        $t = Task::findOrFail($id);
        $svc = app(TaskService::class);
        $t->status === 'pengajuan'
            ? $this->attempt(fn () => $svc->approveSubmission($t, Auth::user()), 'Pengajuan disetujui.')
            : $this->attempt(fn () => $svc->approve($t, Auth::user()), 'Tugas diverifikasi selesai. Poin tercatat.');
        unset($this->items, $this->counts);
    }

    public function openNote(int $id, string $type): void
    {
        $this->noteFor = $id;
        $this->noteType = $type;
        $this->note = '';
        $this->resetValidation();
    }

    public function submitNote(): void
    {
        $this->validate(['note' => 'required|string|max:3000'], [], ['note' => 'catatan']);
        $t = Task::findOrFail($this->noteFor);
        $svc = app(TaskService::class);
        $ok = $this->noteType === 'reject'
            ? $this->attempt(fn () => $svc->reject($t, Auth::user(), $this->note), 'Pengajuan ditolak.')
            : $this->attempt(fn () => $svc->requestRevision($t, Auth::user(), $this->note), 'Revisi diminta.');
        if ($ok) {
            $this->noteFor = null;
        }
        unset($this->items, $this->counts);
    }

    public function decideExtension(int $id, bool $approve): void
    {
        app(TaskService::class)->decideExtension(Task::findOrFail($id), Auth::user(), $approve);
        $this->toast($approve ? 'Perpanjangan disetujui.' : 'Perpanjangan ditolak.');
        unset($this->items, $this->counts);
    }

    public function render()
    {
        return view('livewire.tasks.approvals');
    }
}
