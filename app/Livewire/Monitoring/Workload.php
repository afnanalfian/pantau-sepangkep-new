<?php

namespace App\Livewire\Monitoring;

use App\Models\Team;
use App\Models\User;
use App\Services\WorkloadService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Beban Kerja Pegawai')]
class Workload extends Component
{
    #[Url]
    public string $month = '';

    #[Url(except: '')]
    public string $team = '';

    #[Url]
    public string $sort = 'load_asc'; // load_asc | load_desc | name | overdue

    #[Url(except: '')]
    public string $level = '';

    public string $display = 'grid'; // grid | table

    public function mount(): void
    {
        $this->month = $this->month ?: now()->format('Y-m');
        $u = Auth::user();
        if (! $u->is_pimpinan && ! $u->is_admin && ! $this->team) {
            $this->team = (string) $u->ledTeamIds()->first();
        }
    }

    #[On('tasks-changed')]
    public function refresh(): void
    {
        unset($this->rows);
    }

    #[Computed]
    public function teams()
    {
        $u = Auth::user();
        $q = Team::where('is_active', true)->orderBy('sort_order');
        if (! $u->is_pimpinan && ! $u->is_admin) {
            $q->whereIn('id', $u->ledTeamIds());
        }

        return $q->get();
    }

    #[Computed]
    public function rows()
    {
        $u = Auth::user();
        $q = User::pegawai()->whereHas('teams')->with('teams');
        if ($this->team) {
            $q->whereHas('teams', fn ($w) => $w->where('teams.id', $this->team));
        } elseif (! $u->is_pimpinan && ! $u->is_admin) {
            $q->whereHas('teams', fn ($w) => $w->whereIn('teams.id', $u->ledTeamIds()));
        }

        $rows = app(WorkloadService::class)->summarize($q->get(), Carbon::parse($this->month.'-01'));
        if ($this->level) {
            $rows = $rows->where('level', $this->level);
        }

        $sorted = match ($this->sort) {
            'load_desc' => $rows->sortByDesc('load_pct'),
            'name' => $rows->sortBy(fn ($r) => $r['user']->display_name),
            'overdue' => $rows->sortByDesc('overdue_count'),
            default => $rows->sortBy('load_pct'),
        };

        return $sorted->values();
    }

    #[Computed]
    public function distribution(): array
    {
        $all = $this->rows;

        return collect(WorkloadService::LEVELS)->map(fn ($v, $k) => $all->where('level', $k)->count())->all();
    }

    public function render()
    {
        return view('livewire.monitoring.workload');
    }
}
