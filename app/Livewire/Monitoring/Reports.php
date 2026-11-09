<?php

namespace App\Livewire\Monitoring;

use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\WorkloadService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Laporan Kinerja')]
class Reports extends Component
{
    #[Url]
    public string $month = '';

    public function mount(): void
    {
        $this->month = $this->month ?: now()->format('Y-m');
    }

    protected function monthDate(): Carbon
    {
        return Carbon::parse($this->month.'-01');
    }

    #[Computed]
    public function users()
    {
        $u = Auth::user();
        $q = User::pegawai()->with('teams')->orderBy('name');
        if (! $u->is_pimpinan && ! $u->is_admin) {
            $q->whereHas('teams', fn ($w) => $w->whereIn('teams.id', $u->ledTeamIds()));
        }

        return $q->get();
    }

    #[Computed]
    public function summary()
    {
        return app(WorkloadService::class)->summarize($this->users, $this->monthDate())
            ->sortByDesc('achievement_pct')->values();
    }

    #[Computed]
    public function teamStats()
    {
        $u = Auth::user();
        $q = Team::with('members')->where('is_active', true)->orderBy('sort_order');
        if (! $u->is_pimpinan && ! $u->is_admin) {
            $q->whereIn('id', $u->ledTeamIds());
        }

        return app(WorkloadService::class)->teamUtilization($q->get(), $this->monthDate());
    }

    #[Computed]
    public function leaders()
    {
        $svc = app(WorkloadService::class);

        return User::pegawai()->whereHas('ledTeams')->with('ledTeams')->get()
            ->filter(fn ($l) => Auth::user()->is_pimpinan || Auth::user()->is_admin || $l->id === Auth::id())
            ->map(fn ($l) => ['user' => $l] + $svc->leaderStats($l, $this->monthDate()));
    }

    #[Computed]
    public function totals(): array
    {
        $start = $this->monthDate()->startOfMonth();
        $end = $this->monthDate()->endOfMonth();
        $ids = $this->users->pluck('id');
        $done = Task::whereIn('assignee_id', $ids)->where('status', 'selesai')->whereBetween('completed_at', [$start, $end]);
        $onTime = (clone $done)->whereNotNull('due_date')
            ->whereRaw('DATE(completed_at) <= COALESCE(extension_until, due_date)')->count();
        $withDue = (clone $done)->whereNotNull('due_date')->count();

        return [
            'done' => (clone $done)->count(),
            'points' => (float) (clone $done)->sum('points'),
            'on_time_pct' => $withDue ? round($onTime / $withDue * 100, 1) : null,
            'created' => Task::whereIn('assignee_id', $ids)->whereBetween('created_at', [$start, $end])->count(),
            'disparity' => $this->disparity(),
        ];
    }

    /** Koefisien variasi beban (indikator pemerataan, target < 15%). */
    protected function disparity(): ?float
    {
        $loads = $this->summary->pluck('load_pct');
        if ($loads->count() < 2 || $loads->avg() == 0) {
            return null;
        }
        $mean = $loads->avg();
        $sd = sqrt($loads->map(fn ($x) => ($x - $mean) ** 2)->avg());

        return round($sd / $mean * 100, 1);
    }

    public function render()
    {
        return view('livewire.monitoring.reports');
    }
}
