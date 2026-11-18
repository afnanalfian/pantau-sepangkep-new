<?php

namespace App\Livewire\Presensi;

use App\Livewire\Concerns\Toasts;
use App\Models\PresensiKegiatan;
use App\Models\User;
use App\Services\KinerjaService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Presensi Apel & Rapat')]
class Index extends Component
{
    use Toasts;

    #[Url]
    public string $month = '';

    #[Url]
    public string $tab = 'kegiatan'; // kegiatan | rekap | petugas

    #[Url(except: '')]
    public string $jenisFilter = '';

    public bool $showForm = false;

    public ?int $editId = null;

    public array $form = [];

    public ?int $confirmDeleteId = null;

    public string $petugasSearch = '';

    public function mount(): void
    {
        $this->month = $this->month ?: now()->format('Y-m');
    }

    protected function monthDate(): Carbon
    {
        return Carbon::parse($this->month.'-01');
    }

    public function shift(int $n): void
    {
        $this->month = $this->monthDate()->addMonths($n)->format('Y-m');
        unset($this->kegiatans, $this->rekap);
    }

    public function canManagePetugas(): bool
    {
        $u = Auth::user();

        return $u->is_admin || $u->is_pimpinan;
    }

    /* ---------------------------------------------------------- Kegiatan */

    #[Computed]
    public function kegiatans()
    {
        $m = $this->monthDate();
        $total = User::pegawai()->count();

        return PresensiKegiatan::withCount([
            'kehadirans as hadir_count' => fn ($q) => $q->where('status', 'hadir'),
            'kehadirans as izin_count' => fn ($q) => $q->where('status', 'izin'),
        ])
            ->whereBetween('tanggal', [$m->copy()->startOfMonth()->toDateString(), $m->copy()->endOfMonth()->toDateString()])
            ->when($this->jenisFilter, fn ($q) => $q->where('jenis', $this->jenisFilter))
            ->orderByDesc('tanggal')->orderByDesc('jam')->get()
            ->each(fn ($k) => $k->total_pegawai = $total);
    }

    public function create(string $jenis = 'apel'): void
    {
        $this->resetValidation();
        $this->editId = null;
        $this->form = [
            'jenis' => $jenis,
            'nama' => $jenis === 'apel' ? 'Apel pagi' : '',
            'tanggal' => now()->toDateString(),
            'jam' => $jenis === 'apel' ? '07:30' : '09:00',
            'tempat' => $jenis === 'apel' ? 'Halaman kantor' : 'Ruang rapat',
            'keterangan' => '',
        ];
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $k = PresensiKegiatan::findOrFail($id);
        $this->resetValidation();
        $this->editId = $k->id;
        $this->form = [
            'jenis' => $k->jenis, 'nama' => $k->nama, 'tanggal' => $k->tanggal->toDateString(),
            'jam' => $k->jam ? substr($k->jam, 0, 5) : '', 'tempat' => (string) $k->tempat, 'keterangan' => (string) $k->keterangan,
        ];
        $this->showForm = true;
    }

    public function save()
    {
        $data = $this->validate([
            'form.jenis' => 'required|in:apel,rapat',
            'form.nama' => 'required|string|max:200',
            'form.tanggal' => 'required|date',
            'form.jam' => 'nullable|date_format:H:i',
            'form.tempat' => 'nullable|string|max:150',
            'form.keterangan' => 'nullable|string|max:2000',
        ], [], ['form.nama' => 'nama kegiatan', 'form.tanggal' => 'tanggal', 'form.jam' => 'jam'])['form'];
        $data['jam'] = $data['jam'] ?: null;

        if ($this->editId) {
            PresensiKegiatan::findOrFail($this->editId)->update($data);
            $this->showForm = false;
            unset($this->kegiatans);
            $this->toast('Kegiatan diperbarui.');

            return;
        }

        $k = PresensiKegiatan::create($data + ['created_by' => Auth::id()]);
        $this->toast(PresensiKegiatan::JENIS[$k->jenis]['label'].' dibuat. Silakan centang kehadiran.');

        return $this->redirectRoute('presensi.show', $k, navigate: true);
    }

    public function delete(): void
    {
        PresensiKegiatan::whereKey($this->confirmDeleteId)->delete();
        $this->confirmDeleteId = null;
        unset($this->kegiatans, $this->rekap);
        $this->toast('Kegiatan presensi dihapus.');
    }

    /* ---------------------------------------------------------- Rekap */

    #[Computed]
    public function rekap()
    {
        return app(KinerjaService::class)->matriks(User::pegawai()->orderByRaw('COALESCE(name, username)')->get(), $this->monthDate())->values();
    }

    /* ---------------------------------------------------------- Petugas */

    #[Computed]
    public function petugas()
    {
        return User::active()->where('is_petugas_presensi', true)->orderBy('name')->get();
    }

    #[Computed]
    public function kandidat()
    {
        return User::active()->where('is_petugas_presensi', false)
            ->when($this->petugasSearch, fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$this->petugasSearch}%")->orWhere('username', 'like', "%{$this->petugasSearch}%")))
            ->orderByRaw('COALESCE(name, username)')->limit(30)->get();
    }

    public function setPetugas(int $userId, bool $on): void
    {
        abort_unless($this->canManagePetugas(), 403);
        $u = User::findOrFail($userId);
        $u->forceFill(['is_petugas_presensi' => $on])->save();
        unset($this->petugas, $this->kandidat);
        $this->toast($u->display_name.($on ? ' ditunjuk sebagai petugas presensi.' : ' tidak lagi menjadi petugas presensi.'));
    }

    public function render()
    {
        if ($this->tab === 'petugas' && ! $this->canManagePetugas()) {
            $this->tab = 'kegiatan';
        }

        return view('livewire.presensi.index', ['monthLabel' => bulan_tahun($this->monthDate())]);
    }
}
