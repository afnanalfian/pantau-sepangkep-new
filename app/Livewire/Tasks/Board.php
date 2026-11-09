<?php

namespace App\Livewire\Tasks;

use App\Livewire\Concerns\Toasts;
use App\Models\Task;
use App\Models\Team;
use App\Services\TaskService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Tugas')]
class Board extends Component
{
    use Toasts, WithPagination;

    #[Url]
    public string $view = 'board'; // board | list

    #[Url]
    public string $scope = 'mine'; // mine | created | team | all

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $priority = '';

    #[Url(except: '')]
    public string $team = '';

    #[Url(except: '')]
    public string $status = '';

    public function mount(): void
    {
        if (! in_array($this->scope, $this->scopes())) {
            $this->scope = 'mine';
        }
    }

    public function scopes(): array
    {
        $u = Auth::user();
        $s = ['mine', 'created'];
        if ($u->isLeader()) {
            $s[] = 'team';
        }
        if ($u->is_pimpinan || $u->is_admin) {
            $s[] = 'all';
        }

        return $s;
    }

    public function updating($name): void
    {
        if (in_array($name, ['search', 'priority', 'team', 'scope', 'status'])) {
            $this->resetPage();
        }
    }

    #[On('tasks-changed')]
    public function refresh(): void
    {
        unset($this->columns);
    }

    protected function baseQuery()
    {
        $u = Auth::user();
        $q = Task::query()->with(['assignee', 'team', 'creator'])
            ->withCount(['attachments', 'comments', 'children'])
            ->whereNotIn('status', ['batal']);

        match ($this->scope) {
            'created' => $q->where('creator_id', $u->id)->where('assignee_id', '!=', $u->id),
            'team' => $q->where(function ($w) use ($u) {
                $ids = $u->ledTeamIds();
                $w->whereIn('team_id', $ids)->orWhereIn('assignee_id', fn ($s) => $s->select('user_id')->from('team_user')->whereIn('team_id', $ids));
            }),
            'all' => $q,
            default => $q->where('assignee_id', $u->id),
        };

        return $q
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w->where('title', 'like', "%{$this->search}%")->orWhere('code', 'like', "%{$this->search}%")))
            ->when($this->priority, fn ($q) => $q->where('priority', $this->priority))
            ->when($this->team, fn ($q) => $q->where('team_id', $this->team));
    }

    #[Computed]
    public function columns(): array
    {
        $tasks = $this->baseQuery()
            ->whereIn('status', Task::BOARD_COLUMNS)
            ->where(fn ($w) => $w->where('status', '!=', 'selesai')->orWhere('completed_at', '>=', now()->subDays(30)))
            ->orderBy('position')->orderByRaw('due_date IS NULL, due_date')->orderBy('id')
            ->limit(400)->get();

        $cols = [];
        foreach (Task::BOARD_COLUMNS as $s) {
            $cols[$s] = $tasks->where('status', $s)->values();
        }

        return $cols;
    }

    #[Computed]
    public function pendingSubmissions()
    {
        return $this->baseQuery()->where('status', 'pengajuan')->latest()->get();
    }

    #[Computed]
    public function teams()
    {
        return Team::where('is_active', true)->orderBy('sort_order')->get();
    }

    /** Drag & drop dari papan Kanban (wire:sort). */
    public function handleSort($id, $position, $status = null): void
    {
        $task = Task::findOrFail($id);
        $user = Auth::user();
        $status ??= $task->status;

        if ($task->status !== $status) {
            $ok = $this->attempt(fn () => app(TaskService::class)->moveTo($task, $status, $user));
            if (! $ok) {
                unset($this->columns);

                return;
            }
            $this->toast('Status diubah ke "'.Task::STATUSES[$status]['label'].'".');
        }

        // Simpan urutan kartu di kolom tujuan
        $ids = collect($this->columns[$status] ?? [])->pluck('id')->reject(fn ($x) => $x == $id)->values()->all();
        array_splice($ids, max(0, (int) $position), 0, [(int) $id]);
        foreach ($ids as $i => $taskId) {
            Task::whereKey($taskId)->update(['position' => $i]);
        }
        unset($this->columns);
    }

    public function quickStart(int $id): void
    {
        $this->attempt(fn () => app(TaskService::class)->start(Task::findOrFail($id), Auth::user()), 'Tugas dimulai.');
        unset($this->columns);
    }

    public function render()
    {
        $list = $this->view === 'list'
            ? $this->baseQuery()
                ->when($this->status, fn ($q) => $q->where('status', $this->status))
                ->orderByRaw("CASE status WHEN 'revisi' THEN 0 WHEN 'in_progress' THEN 1 WHEN 'todo' THEN 2 WHEN 'review' THEN 3 WHEN 'pengajuan' THEN 4 ELSE 5 END")
                ->orderByRaw('due_date IS NULL, due_date')
                ->paginate(20)
            : null;

        return view('livewire.tasks.board', ['list' => $list]);
    }
}
