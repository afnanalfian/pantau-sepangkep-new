<?php

namespace App\Livewire\Presensi;

use App\Livewire\Concerns\Toasts;
use App\Models\Leave;
use App\Models\PresensiKegiatan;
use App\Models\PresensiKehadiran;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class Checklist extends Component
{
    use Toasts;

    public PresensiKegiatan $kegiatan;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 'semua')]
    public string $filter = 'semua'; // semua | belum | hadir | izin

    public function mount(PresensiKegiatan $kegiatan): void
    {
        $this->kegiatan = $kegiatan;
    }

    #[Computed]
    public function marks()
    {
        return PresensiKehadiran::where('presensi_kegiatan_id', $this->kegiatan->id)->pluck('status', 'user_id');
    }

    #[Computed]
    public function onLeave()
    {
        return Leave::whereDate('start_date', '<=', $this->kegiatan->tanggal)->whereDate('end_date', '>=', $this->kegiatan->tanggal)
            ->get()->keyBy('user_id');
    }

    #[Computed]
    public function pegawai()
    {
        return User::pegawai()->with('teams')->orderByRaw('COALESCE(name, username)')->get();
    }

    /** Status efektif: tanda yang dicatat, atau "izin" otomatis bila sedang cuti/DL. */
    public function statusOf(int $userId): ?string
    {
        return $this->marks[$userId] ?? ($this->onLeave->has($userId) ? 'izin' : null);
    }

    #[Computed]
    public function stats(): array
    {
        $s = ['hadir' => 0, 'izin' => 0, 'absen' => 0, 'belum' => 0];
        foreach ($this->pegawai as $p) {
            $st = $this->statusOf($p->id);
            $s[$st ?? 'belum']++;
        }

        return $s;
    }

    public function mark(int $userId, string $status): void
    {
        abort_unless(in_array($status, ['hadir', 'izin', 'absen', 'reset']), 422);
        if ($status === 'reset') {
            PresensiKehadiran::where('presensi_kegiatan_id', $this->kegiatan->id)->where('user_id', $userId)->delete();
        } else {
            PresensiKehadiran::updateOrCreate(
                ['presensi_kegiatan_id' => $this->kegiatan->id, 'user_id' => $userId],
                ['status' => $status, 'dicatat_oleh' => Auth::id()]
            );
        }
        unset($this->marks, $this->stats);
    }

    public function markAll(string $status): void
    {
        abort_unless(in_array($status, ['hadir', 'absen']), 422);
        foreach ($this->pegawai as $p) {
            if ($this->marks->has($p->id) || ($status === 'hadir' && $this->onLeave->has($p->id))) {
                continue; // tidak menimpa yang sudah dicatat / sedang cuti
            }
            PresensiKehadiran::create(['presensi_kegiatan_id' => $this->kegiatan->id, 'user_id' => $p->id, 'status' => $status, 'dicatat_oleh' => Auth::id()]);
        }
        unset($this->marks, $this->stats);
        $this->toast($status === 'hadir' ? 'Semua yang belum dicatat ditandai hadir.' : 'Semua yang belum dicatat ditandai tidak hadir.');
    }

    public function render()
    {
        $list = $this->pegawai->filter(function ($p) {
            if ($this->search && ! str_contains(mb_strtolower($p->display_name.' '.$p->username.' '.$p->jabatan), mb_strtolower($this->search))) {
                return false;
            }
            $st = $this->statusOf($p->id);

            return match ($this->filter) {
                'belum' => $st === null,
                'hadir' => $st === 'hadir',
                'izin' => $st === 'izin',
                'absen' => $st === 'absen',
                default => true,
            };
        });

        return view('livewire.presensi.checklist', ['list' => $list])
            ->title('Presensi '.$this->kegiatan->jenisLabel().' · '.$this->kegiatan->nama);
    }
}
