<?php

namespace App\Livewire;

use App\Models\PresensiKegiatan;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\Team;
use App\Models\User;
use App\Services\KinerjaService;
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
        return app(KinerjaService::class)->forUser(Auth::user());
    }

    #[Computed]
    public function counts(): array
    {
        $u = Auth::user();
        $base = Task::forWorker($u->id);

        return [
            'todo' => (clone $base)->where('status', 'todo')->count(),
            'in_progress' => (clone $base)->whereIn('status', ['in_progress', 'revisi'])->count(),
            'review' => (clone $base)->where('status', 'review')->count(),
            'done' => (clone $base)->where('status', 'selesai')->where('completed_at', '>=', now()->startOfMonth())->count(),
            'overdue' => (clone $base)->overdue()->count(),
            'to_verify' => Task::where('verifier_id', $u->id)->whereIn('status', ['review', 'pengajuan'])->count(),
            'delegasi' => \App\Models\TaskDelegation::where('to_user_id', $u->id)->where('status', 'menunggu')->count(),
        ];
    }

    #[Computed]
    public function upcoming()
    {
        return Task::with(['team', 'creator'])->forWorker(Auth::id())
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

    /** Presensi saya bulan ini (5 kegiatan terakhir). */
    #[Computed]
    public function myPresensi()
    {
        $uid = Auth::id();

        return PresensiKegiatan::with(['kehadirans' => fn ($q) => $q->where('user_id', $uid)])
            ->whereBetween('tanggal', [now()->startOfMonth()->toDateString(), now()->toDateString()])
            ->orderByDesc('tanggal')->limit(5)->get();
    }

    /* ---------------- Pemantauan (pimpinan / ketua tim) ---------------- */

    #[Computed]
    public function monitorUsers()
    {
        $u = Auth::user();
        if (! $u->canMonitor()) {
            return collect();
        }
        $q = User::pegawai()->whereHas('teams')->orderBy('name');
        if (! ($u->is_pimpinan || $u->is_admin)) {
            $q->whereHas('teams', fn ($w) => $w->whereIn('teams.id', $u->ledTeamIds()));
        }

        return $q->get();
    }

    #[Computed]
    public function matriksTim()
    {
        return app(KinerjaService::class)->matriks($this->monitorUsers);
    }

    #[Computed]
    public function teamStats()
    {
        $u = Auth::user();
        if (! ($u->is_pimpinan || $u->is_admin)) {
            return collect();
        }
        $m = $this->matriksTim;

        return Team::with('members')->where('is_active', true)->orderBy('sort_order')->get()->map(function ($t) use ($m) {
            $rows = $t->members->map(fn ($x) => $m[$x->id] ?? null)->filter();
            $tot = $rows->sum('tugas_total');

            return [
                'team' => $t,
                'pct' => $tot ? round($rows->sum('tugas_selesai') / $tot * 100, 1) : null,
                'selesai' => $rows->sum('tugas_selesai'),
                'total' => $tot,
            ];
        });
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
