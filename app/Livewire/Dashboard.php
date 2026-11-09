<?php

namespace App\Livewire;

use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\Team;
use App\Models\User;
use App\Services\WorkloadService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Beranda')]
class Dashboard extends Component
{
    #[Computed]
    public function me(): array
    {
        return app(WorkloadService::class)->forUser(Auth::user());
    }

    #[Computed]
    public function counts(): array
    {
        $u = Auth::user();
        $base = Task::where('assignee_id', $u->id);

        return [
            'todo' => (clone $base)->where('status', 'todo')->count(),
            'in_progress' => (clone $base)->whereIn('status', ['in_progress', 'revisi'])->count(),
            'review' => (clone $base)->where('status', 'review')->count(),
            'done' => (clone $base)->where('status', 'selesai')->where('completed_at', '>=', now()->startOfMonth())->count(),
            'overdue' => (clone $base)->overdue()->count(),
            'to_verify' => Task::where('verifier_id', $u->id)->whereIn('status', ['review', 'pengajuan'])->count(),
        ];
    }

    #[Computed]
    public function upcoming()
    {
        return Task::with(['team', 'creator'])->where('assignee_id', Auth::id())
            ->whereIn('status', ['todo', 'in_progress', 'revisi'])
            ->orderByRaw('COALESCE(extension_until, due_date) IS NULL, COALESCE(extension_until, due_date)')
            ->limit(6)->get();
    }

    #[Computed]
    public function recentActivity()
    {
        $u = Auth::user();

        return TaskActivity::with(['user', 'task'])
            ->whereHas('task', fn ($q) => $q->visibleTo($u))
            ->latest('created_at')->latest('id')->limit(8)->get();
    }

    #[Computed]
    public function trend(): array
    {
        return app(WorkloadService::class)->dailyTrend(collect([Auth::id()]), now());
    }

    /* ---------------- Pemantauan (pimpinan / ketua tim) ---------------- */

    #[Computed]
    public function monitorUsers()
    {
        $u = Auth::user();
        if (! $u->canMonitor()) {
            return collect();
        }
        if ($u->is_pimpinan || $u->is_admin) {
            return User::pegawai()->orderBy('name')->get();
        }

        return User::pegawai()->whereHas('teams', fn ($q) => $q->whereIn('teams.id', $u->ledTeamIds()))->orderBy('name')->get();
    }

    #[Computed]
    public function workload()
    {
        return app(WorkloadService::class)->summarize($this->monitorUsers);
    }

    #[Computed]
    public function teamStats()
    {
        $u = Auth::user();
        if (! ($u->is_pimpinan || $u->is_admin)) {
            return collect();
        }
        $teams = Team::with('members')->where('is_active', true)->orderBy('sort_order')->get();

        return app(WorkloadService::class)->teamUtilization($teams, now());
    }

    #[Computed]
    public function overdueTasks()
    {
        $u = Auth::user();
        if (! $u->canMonitor()) {
            return collect();
        }

        return Task::with('assignee')->visibleTo($u)->overdue()->orderBy('due_date')->limit(6)->get();
    }

    public function render()
    {
        return view('livewire.dashboard');
    }
}
