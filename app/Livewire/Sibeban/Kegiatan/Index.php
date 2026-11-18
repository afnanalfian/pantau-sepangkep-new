<?php

namespace App\Livewire\Sibeban\Kegiatan;

use App\Models\Sibeban\HonorBulanan;
use App\Models\Sibeban\Kegiatan;
use App\Services\Sibeban\HonorService;
use App\Services\Sibeban\PenugasanService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.portal')]
#[Title('SIBEBAN · Kegiatan')]
class Index extends Component
{
    use WithPagination;

    public const TIM = ['Umum', 'Sosial', 'Hadija', 'Produksi', 'Nerwilis', 'IPDS'];

    #[Url(except: '')]
    public string $cari = '';

    #[Url(except: '')]
    public string $tahun = '';

    #[Url(except: false)]
    public bool $milikSaya = false;

    public bool $formTampil = false;
    public ?int $editId = null;
    public string $nama = '';
    public string $tim = '';
    public string $tanggal_mulai = '';
    public string $tanggal_selesai = '';
    public string $keterangan = '';

    public ?int $hapusId = null;

    public function updated(string $p): void
    {
        if (in_array($p, ['cari', 'tahun', 'milikSaya'], true)) {
            $this->resetPage();
        }
    }

    public function tambah(): void
    {
        $this->resetForm();
        $this->tanggal_mulai = now()->startOfMonth()->toDateString();
        $this->tanggal_selesai = now()->endOfMonth()->toDateString();
        $this->formTampil = true;
    }

    public function edit(int $id): void
    {
        $k = Kegiatan::findOrFail($id);
        abort_unless($k->bisaDiubah(), 403);
        $this->resetForm();
        $this->editId = $k->id;
        $this->nama = $k->nama;
        $this->tim = (string) $k->tim;
        $this->tanggal_mulai = $k->tanggal_mulai->toDateString();
        $this->tanggal_selesai = $k->tanggal_selesai->toDateString();
        $this->keterangan = (string) $k->keterangan;
        $this->formTampil = true;
    }

    public function simpan(PenugasanService $penugasan)
    {
        $data = $this->validate([
            'nama' => 'required|string|max:255',
            'tim' => 'nullable|string|max:50',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'keterangan' => 'nullable|string|max:1000',
        ], [], ['tanggal_mulai' => 'tanggal mulai', 'tanggal_selesai' => 'tanggal selesai', 'nama' => 'nama kegiatan']);

        if ($this->editId) {
            $k = Kegiatan::findOrFail($this->editId);
            abort_unless($k->bisaDiubah(), 403);
            $k->update($data);
            $penugasan->sinkronPeriode($k->fresh());
            $this->formTampil = false;
            session()->flash('sukses', 'Kegiatan diperbarui.');

            return null;
        }

        $u = Auth::user();
        $k = Kegiatan::create($data + ['sumber' => 'manual', 'user_id' => $u->id, 'pembuat' => $u->name ?: $u->username]);
        session()->flash('sukses', 'Kegiatan dibuat. Langkah berikutnya: pilih petugas, unduh template, isi jabatan & honor, lalu impor.');

        return $this->redirectRoute('sibeban.kegiatan.show', $k, navigate: true);
    }

    public function hapus(): void
    {
        $k = Kegiatan::findOrFail($this->hapusId);
        abort_unless($k->bisaDiubah(), 403);
        $nama = $k->nama;
        $k->delete();
        $this->hapusId = null;
        session()->flash('sukses', "Kegiatan \"{$nama}\" beserta petugas & honornya dihapus.");
    }

    private function resetForm(): void
    {
        $this->reset(['editId', 'nama', 'tim', 'tanggal_mulai', 'tanggal_selesai', 'keterangan']);
        $this->resetValidation();
    }

    public function render()
    {
        $kegiatans = Kegiatan::query()
            ->withCount('penugasans')
            ->when($this->cari !== '', fn ($q) => $q->where(fn ($w) => $w->where('nama', 'like', "%{$this->cari}%")->orWhere('tim', 'like', "%{$this->cari}%")))
            ->when($this->tahun !== '', fn ($q) => $q
                ->whereDate('tanggal_mulai', '<=', $this->tahun.'-12-31')
                ->whereDate('tanggal_selesai', '>=', $this->tahun.'-01-01'))
            ->when($this->milikSaya, fn ($q) => $q->where('user_id', Auth::id()))
            ->orderByDesc('tanggal_mulai')->orderByDesc('id')
            ->paginate(15);

        $ids = $kegiatans->getCollection()->pluck('id');
        $totalHonor = HonorBulanan::query()
            ->join('sibeban_penugasans', 'sibeban_penugasans.id', '=', 'sibeban_honor_bulanans.penugasan_id')
            ->whereIn('sibeban_penugasans.kegiatan_id', $ids)
            ->groupBy('sibeban_penugasans.kegiatan_id')
            ->selectRaw('sibeban_penugasans.kegiatan_id, SUM(sibeban_honor_bulanans.jumlah) as total, COUNT(DISTINCT sibeban_penugasans.id) as terisi')
            ->get()->keyBy('kegiatan_id');

        return view('livewire.sibeban.kegiatan.index', [
            'kegiatans' => $kegiatans,
            'totalHonor' => $totalHonor,
            'limit' => app(HonorService::class)->limit(),
        ]);
    }
}
