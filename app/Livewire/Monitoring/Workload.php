<?php

namespace App\Livewire\Monitoring;

use App\Models\Team;
use App\Models\User;
use App\Services\KinerjaService;
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
    public string $sort = 'aktif_asc'; // aktif_asc | aktif_desc | name | overdue | pct_asc

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

        $rows = app(KinerjaService::class)->matriks($q->get(), Carbon::parse($this->month.'-01'));
        if ($this->level) {
            $rows = $rows->where('beban', $this->level);
        }

        $sorted = match ($this->sort) {
            'aktif_desc' => $rows->sortByDesc('aktif'),
            'name' => $rows->sortBy(fn ($r) => $r['user']->display_name),
            'overdue' => $rows->sortByDesc('terlambat'),
            'pct_asc' => $rows->sortBy(fn ($r) => $r['tugas_pct'] ?? 101),
            default => $rows->sortBy('aktif'),
        };

        return $sorted->values();
    }

    #[Computed]
    public function distribution(): array
    {
        $all = $this->rows;

        return collect(KinerjaService::BEBAN)->map(fn ($v, $k) => $all->where('beban', $k)->count())->all();
    }

    public function render()
    {
        return view('livewire.monitoring.workload');
    }
}
