<?php

namespace App\Livewire\Tasks;

use App\Livewire\Concerns\Toasts;
use App\Models\ActivityCatalog;
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

    public ?int $catalog_id = null;

    public $quantity = 1;

    public string $complexity = 'rendah';

    public string $priority = 'sedang';

    public ?string $start_date = null;

    public ?string $due_date = null;

    public ?int $team_id = null;

    public array $assignees = [];

    public string $assigneeSearch = '';

    public string $catalogSearch = '';

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
        $this->team_id = $user->ledTeamIds()->first() ?? $user->teams()->value('teams.id');

        if ($assignee) {
            $this->assignees = [(int) $assignee];
        }

        if ($this->mode === 'delegate' && $parent) {
            $p = Task::findOrFail($parent);
            abort_unless($p->assignee_id === $user->id, 403);
            $this->parentId = $p->id;
            $this->title = $p->title;
            $this->description = (string) $p->description;
            $this->catalog_id = $p->catalog_id;
            $this->complexity = $p->complexity;
            $this->priority = $p->priority;
            $this->due_date = $p->effectiveDueDate()?->toDateString();
            $this->team_id = $p->team_id;
            $this->quantity = $p->quantity;
        }

        if ($this->mode === 'edit' && $task) {
            $t = Task::findOrFail($task);
            abort_unless(app(TaskService::class)->canManage($t, $user) || ($t->status === 'pengajuan' && $t->assignee_id === $user->id), 403);
            $this->editId = $t->id;
            $this->fill([
                'title' => $t->title, 'description' => (string) $t->description, 'catalog_id' => $t->catalog_id,
                'quantity' => $t->quantity, 'complexity' => $t->complexity, 'priority' => $t->priority,
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

    public function updatedCatalogId($value): void
    {
        if ($value && ($c = ActivityCatalog::find($value))) {
            $this->complexity = $c->complexity;
            if (! $this->title) {
                $this->title = $c->name;
            }
        }
    }

    /* ----------------------------------------------------------- Computed */

    #[Computed]
    public function catalogs()
    {
        return ActivityCatalog::where('is_active', true)
            ->when($this->catalogSearch, fn ($q) => $q->where('name', 'like', '%'.$this->catalogSearch.'%'))
            ->orderBy('rumpun')->orderBy('name')->get();
    }

    #[Computed]
    public function selectedCatalog(): ?ActivityCatalog
    {
        return $this->catalog_id ? ActivityCatalog::find($this->catalog_id) : null;
    }

    #[Computed]
    public function points(): float
    {
        return Task::computePoints($this->selectedCatalog, (float) $this->quantity, $this->complexity);
    }

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

    #[Computed]
    public function teams()
    {
        return Team::where('is_active', true)->orderBy('sort_order')->get();
    }

    /* ----------------------------------------------------------- Simpan */

    public function save(TaskService $service, AttachmentService $files)
    {
        $user = Auth::user();
        $data = $this->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:20000',
            'catalog_id' => 'nullable|exists:activity_catalogs,id',
            'quantity' => 'required|numeric|min:0.1|max:9999',
            'complexity' => 'required|in:rendah,sedang,tinggi',
            'priority' => 'required|in:rendah,sedang,tinggi',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
            'team_id' => 'nullable|exists:teams,id',
            'links.*.url' => 'nullable|string|max:2000',
            'suratTugas.*' => 'file|max:51200',
            'dokumen.*' => 'file|max:51200',
        ], [], [
            'title' => 'judul tugas', 'quantity' => 'volume / jam', 'due_date' => 'deadline', 'start_date' => 'tanggal mulai',
        ]);

        if (in_array($this->mode, ['assign', 'delegate']) && ! $this->assignees) {
            $this->addError('assignees', 'Pilih minimal satu pegawai penerima tugas.');

            return;
        }

        if ($this->mode === 'edit') {
            $task = Task::findOrFail($this->editId);
            $task->fill([
                'title' => $data['title'], 'description' => $data['description'], 'catalog_id' => $data['catalog_id'],
                'quantity' => $data['quantity'], 'complexity' => $data['complexity'], 'priority' => $data['priority'],
                'start_date' => $data['start_date'], 'due_date' => $data['due_date'], 'team_id' => $data['team_id'],
                'points' => Task::computePoints($this->selectedCatalog, (float) $data['quantity'], $data['complexity']),
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
        } else {
            $parent = $this->parentId ? Task::find($this->parentId) : null;
            $tasks = $service->assign($data, $this->assignees, $user, $parent);
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
            default => $tasks->count() > 1 ? 'Tugas dibuat untuk '.$tasks->count().' pegawai.' : 'Tugas berhasil dibuat & notifikasi terkirim.',
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
