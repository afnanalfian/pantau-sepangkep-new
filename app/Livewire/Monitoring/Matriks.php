<?php

namespace App\Livewire\Monitoring;

use App\Models\Team;
use App\Models\User;
use App\Services\KinerjaService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Matriks Kinerja & TK')]
class Matriks extends Component
{
    #[Url]
    public string $month = '';

    #[Url(except: '')]
    public string $team = '';

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $potongan = '';

    public ?int $detailId = null;

    public function mount(): void
    {
        $this->month = $this->month ?: now()->format('Y-m');
    }

    public static function usersFor(User $viewer, ?string $team = null)
    {
        $q = User::pegawai()->with('teams')->orderByRaw('COALESCE(name, username)');
        if (! ($viewer->is_admin || $viewer->is_pimpinan || $viewer->is_keuangan)) {
            $q->whereHas('teams', fn ($w) => $w->whereIn('teams.id', $viewer->ledTeamIds()));
        }
        if ($team) {
            $q->whereHas('teams', fn ($w) => $w->where('teams.id', $team));
        }

        return $q->get();
    }

    #[Computed]
    public function teams()
    {
        $u = Auth::user();
        $q = Team::where('is_active', true)->orderBy('sort_order');
        if (! ($u->is_admin || $u->is_pimpinan || $u->is_keuangan)) {
            $q->whereIn('id', $u->ledTeamIds());
        }

        return $q->get();
    }

    #[Computed]
    public function rows()
    {
        $rows = app(KinerjaService::class)->matriks(self::usersFor(Auth::user(), $this->team ?: null), Carbon::parse($this->month.'-01'))->values();
        if ($this->search) {
            $s = mb_strtolower($this->search);
            $rows = $rows->filter(fn ($r) => str_contains(mb_strtolower($r['user']->display_name.' '.$r['user']->nip), $s))->values();
        }

        return $rows;
    }

    #[Computed]
    public function ringkasan(): array
    {
        $all = $this->rows;

        return [
            'pegawai' => $all->count(),
            'rata' => $all->count() ? round($all->avg('nilai'), 2) : null,
            'p0' => $all->where('potongan', 0)->count(),
            'p1' => $all->filter(fn ($r) => $r['potongan'] > 0 && $r['potongan'] < 2)->count(),
            'p2' => $all->where('potongan', '>=', 2)->count(),
        ];
    }

    public function render()
    {
        $rows = $this->rows;
        if ($this->potongan !== '') {
            $rows = $rows->filter(fn ($r) => match ($this->potongan) {
                '0' => $r['potongan'] == 0,
                '1' => $r['potongan'] > 0 && $r['potongan'] < 2,
                default => $r['potongan'] >= 2,
            });
        }

        return view('livewire.monitoring.matriks', [
            'list' => $rows,
            'detail' => $this->detailId ? $this->rows->firstWhere('user.id', $this->detailId) : null,
            'w' => KinerjaService::weights(),
            'monthLabel' => bulan_tahun(Carbon::parse($this->month.'-01')),
            'isCurrent' => $this->month === now()->format('Y-m'),
        ]);
    }
}
