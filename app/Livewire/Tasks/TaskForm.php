<?php

namespace App\Livewire\Tasks;

use App\Livewire\Concerns\Toasts;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\AttachmentService;
use App\Services\TaskService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Modal global untuk: Beri Tugas, Ajukan Tugas Mandiri, Teruskan (delegasi) dan Edit tugas.
 * Dibuka dengan event browser:  $dispatch('open-task-form', { mode: 'assign', assignee: 5 })
 */
class TaskForm extends Component
{
    use Toasts, WithFileUploads;

    public bool $open = false;

    public string $mode = 'assign'; // assign | mandiri | delegate | edit

    public ?int $parentId = null;

    public ?int $editId = null;

    public string $title = '';

    public string $description = '';

    public string $priority = 'sedang';

    public ?string $start_date = null;

    public ?string $due_date = null;

    public ?int $team_id = null;

    public array $assignees = [];

    public string $assigneeSearch = '';

    /** Sasaran penugasan: 'pegawai' (perorangan) atau 'tim' (diterima ketua tim). */
    public string $target = 'pegawai';

    public array $teamTargets = [];

    /** true = satu tugas dikerjakan bersama; false = setiap penerima mendapat tugas sendiri. */
    public bool $bersama = false;

    // Lampiran awal
    public $suratTugas = [];

    public $dokumen = [];

    public $newSurat = [];

    public $newDokumen = [];

    public array $links = [];

    #[On('open-task-form')]
    public function openForm(string $mode = 'assign', $assignee = null, $parent = null, $task = null): void
    {
        $this->reset();
        $this->resetValidation();
        $user = Auth::user();

        $this->mode = $mode === 'assign' && ! $user->canAssign() ? 'mandiri' : $mode;
        $this->start_date = now()->toDateString();
        $this->due_date = now()->addDays(7)->toDateString();
        $this->team_id = $user->ledTeamIds()->first() ?? $user->memberTeamIds()->first();

        if ($assignee) {
            $this->assignees = [(int) $assignee];
        }

        if ($this->mode === 'delegate' && $parent) {
            $p = Task::findOrFail($parent);
            abort_unless($p->isWorker($user), 403);
            $this->parentId = $p->id;
            $this->title = $p->title;
            $this->description = (string) $p->description;
            $this->priority = $p->priority;
            $this->due_date = $p->effectiveDueDate()?->toDateString();
            $this->team_id = $p->team_id;
        }

        if ($this->mode === 'edit' && $task) {
            $t = Task::findOrFail($task);
            abort_unless(app(TaskService::class)->canManage($t, $user) || ($t->status === 'pengajuan' && $t->assignee_id === $user->id), 403);
            $this->editId = $t->id;
            $this->fill([
                'title' => $t->title, 'description' => (string) $t->description, 'priority' => $t->priority,
                'start_date' => $t->start_date?->toDateString(), 'due_date' => $t->due_date?->toDateString(),
                'team_id' => $t->team_id, 'assignees' => [$t->assignee_id],
            ]);
        }

        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
    }

    /* ----------------------------------------------------------- Upload staging */

    public function updatedNewSurat(): void
    {
        $this->validate(['newSurat.*' => 'file|max:51200']);
        $this->suratTugas = array_merge($this->suratTugas, $this->newSurat);
        $this->newSurat = [];
    }

    public function updatedNewDokumen(): void
    {
        $this->validate(['newDokumen.*' => 'file|max:51200']);
        $this->dokumen = array_merge($this->dokumen, $this->newDokumen);
        $this->newDokumen = [];
    }

    public function removeStaged(string $type, int $index): void
    {
        $arr = $this->{$type};
        unset($arr[$index]);
        $this->{$type} = array_values($arr);
    }

    public function addLink(): void
    {
        $this->links[] = ['url' => '', 'name' => ''];
    }

    public function removeLink(int $i): void
    {
        unset($this->links[$i]);
        $this->links = array_values($this->links);
    }

    public function selectAll(): void
    {
        $this->assignees = array_values(array_unique(array_merge($this->assignees, $this->candidates->pluck('id')->all())));
    }

    public function clearAssignees(): void
    {
        $this->assignees = [];
    }

    /** Pilih seluruh anggota satu tim sekaligus. */
    public function selectTeamMembers(int $teamId): void
    {
        $ids = Team::find($teamId)?->members()->where('is_active', true)->pluck('users.id')->all() ?? [];
        $this->assignees = array_values(array_unique(array_merge($this->assignees, $ids)));
    }

    public function toggleTeamTarget(int $id): void
    {
        $this->teamTargets = in_array($id, $this->teamTargets)
            ? array_values(array_diff($this->teamTargets, [$id]))
            : array_merge($this->teamTargets, [$id]);
    }

    public function toggleAssignee(int $id): void
    {
        if (in_array($id, $this->assignees)) {
            $this->assignees = array_values(array_diff($this->assignees, [$id]));
        } elseif ($this->mode === 'edit') {
            $this->assignees = [$id];
        } else {
            $this->assignees[] = $id;
        }
    }

    /* ----------------------------------------------------------- Computed */

    #[Computed]
    public function candidates()
    {
        $user = Auth::user();
        $q = User::pegawai()->with('teams')->orderByRaw('COALESCE(name, username)');
        if ($this->assigneeSearch) {
            $s = '%'.$this->assigneeSearch.'%';
            $q->where(fn ($w) => $w->where('name', 'like', $s)->orWhere('username', 'like', $s)->orWhere('jabatan', 'like', $s));
        }
        $users = $q->get();

        // Urutkan: anggota tim yang dipimpin dulu
        $led = $user->ledTeamIds();

        return $users->sortBy(fn ($u) => $u->teams->pluck('id')->intersect($led)->isNotEmpty() ? 0 : 1)->values();
    }

    /** Pilihan kolom Tim: pimpinan/admin semua tim; lainnya tim yang diikuti + subtim yang diawasi. */
    #[Computed]
    public function teams()
    {
        $u = Auth::user();
        $q = Team::with('parent')->where('is_active', true)->orderByRaw('COALESCE(parent_id, id)')->orderByRaw('parent_id IS NOT NULL')->orderBy('sort_order');
        if (! $u->is_pimpinan && ! $u->is_admin) {
            $q->whereIn('id', $u->memberTeamIds()->merge($u->supervisedTeamIds())->unique());
        }

        return $q->get();
    }

    /** Tim yang bisa diberi tugas (pimpinan/admin: semua; ketua tim utama: subtimnya). */
    #[Computed]
    public function targetTeams()
    {
        return app(TaskService::class)->assignableTeams(Auth::user());
    }

    /** Pintasan "pilih anggota tim". */
    #[Computed]
    public function quickTeams()
    {
        $u = Auth::user();
        $q = Team::withCount(['members' => fn ($m) => $m->where('is_active', true)])->where('is_active', true)->orderByRaw('COALESCE(parent_id, id)')->orderByRaw('parent_id IS NOT NULL')->orderBy('sort_order');
        if (! $u->is_pimpinan && ! $u->is_admin) {
            $q->whereIn('id', $u->supervisedTeamIds());
        }

        return $q->get();
    }

    /* ----------------------------------------------------------- Simpan */

    public function save(TaskService $service, AttachmentService $files)
    {
        $user = Auth::user();
        $data = $this->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:20000',
            'priority' => 'required|in:rendah,sedang,tinggi',
            'start_date' => 'nullable|date',
            'due_date' => 'required|date|after_or_equal:start_date',
            'team_id' => 'nullable|exists:teams,id',
            'links.*.url' => 'nullable|string|max:2000',
            'suratTugas.*' => 'file|max:51200',
            'dokumen.*' => 'file|max:51200',
        ], [], [
            'title' => 'judul tugas', 'due_date' => 'deadline', 'start_date' => 'tanggal mulai',
        ]);

        $toTeams = $this->mode === 'assign' && $this->target === 'tim';
        if ($toTeams && ! $this->teamTargets) {
            $this->addError('assignees', 'Pilih minimal satu tim.');

            return;
        }
        if (in_array($this->mode, ['assign', 'delegate']) && ! $toTeams && ! $this->assignees) {
            $this->addError('assignees', 'Pilih minimal satu pegawai penerima tugas.');

            return;
        }

        if ($this->mode === 'edit') {
            $task = Task::findOrFail($this->editId);
            $task->fill([
                'title' => $data['title'], 'description' => $data['description'], 'priority' => $data['priority'],
                'start_date' => $data['start_date'], 'due_date' => $data['due_date'], 'team_id' => $data['team_id'],
                'overdue_notified' => false, 'reminder_sent' => false,
            ]);
            if ($this->assignees && $task->assignee_id !== (int) $this->assignees[0] && $service->canManage($task, $user)) {
                $task->assignee_id = (int) $this->assignees[0];
                $task->verifier_id = $service->resolveVerifier($task->setRelation('assignee', User::find($task->assignee_id)));
            }
            $task->save();
            $service->log($task, $user, 'updated', 'memperbarui detail tugas');
            $tasks = collect([$task]);
        } elseif ($this->mode === 'mandiri') {
            $tasks = collect([$service->submitSelf($data, $user)]);
        } elseif ($toTeams) {
            $tasks = $service->assignToTeams($data, $this->teamTargets, $user);
        } else {
            $parent = $this->parentId ? Task::find($this->parentId) : null;
            $tasks = $service->assign($data, $this->assignees, $user, $parent, $this->bersama && count($this->assignees) > 1);
        }

        // Lampiran awal disalin ke setiap tugas yang dibuat
        foreach ($tasks as $task) {
            foreach ($this->suratTugas as $f) {
                $files->storeFile($task, $f, 'surat_tugas', $user);
            }
            foreach ($this->dokumen as $f) {
                $files->storeFile($task, $f, 'dokumen', $user);
            }
            foreach ($this->links as $l) {
                if (trim($l['url'] ?? '') !== '') {
                    $files->addLink($task, trim($l['url']), $l['name'] ?? null, 'dokumen', $user);
                }
            }
        }

        $this->open = false;
        $this->dispatch('tasks-changed');

        $msg = match ($this->mode) {
            'mandiri' => 'Pengajuan tugas mandiri terkirim, menunggu persetujuan.',
            'edit' => 'Tugas berhasil diperbarui.',
            'delegate' => 'Tugas diteruskan ke '.$tasks->count().' pegawai.',
            default => $toTeams ? 'Tugas terkirim ke ketua '.$tasks->count().' tim.'
                : ($tasks->count() > 1 ? 'Tugas dibuat untuk '.$tasks->count().' pegawai.'
                : ($this->bersama && count($this->assignees) > 1 ? 'Tugas bersama dibuat untuk '.count($this->assignees).' pegawai.' : 'Tugas berhasil dibuat & notifikasi terkirim.')),
        };
        $this->toast($msg);

        if ($tasks->count() === 1 && $this->mode !== 'edit') {
            return $this->redirectRoute('tasks.show', $tasks->first(), navigate: true);
        }
    }

    public function render()
    {
        return view('livewire.tasks.task-form');
    }
}
