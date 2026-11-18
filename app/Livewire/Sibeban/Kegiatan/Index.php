<?php

namespace App\Livewire\Sibeban\Kegiatan;

use App\Models\Sibeban\Kegiatan;
use App\Services\Sibeban\HonorService;
use App\Services\Sibeban\PenugasanService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.portal')]
#[Title('Kegiatan')]
class Index extends Component
{
    use WithPagination;

    public const TIM = ['Umum', 'Sosial', 'Hadija', 'Produksi', 'Nerwilis', 'IPDS'];

    #[Url(except: '')]
    public string $cari = '';

    #[Url(except: '')]
    public string $tahun = '';

    // ---- Form ----
    public bool $formTampil = false;
    public ?int $editId = null;
    public string $nama = '';
    public string $tim = '';
    public string $jenis = 'Survei';
    public string $tanggal_mulai = '';
    public string $tanggal_selesai = '';
    public $honor_ppl = 0;
    public $honor_pml = 0;
    public string $keterangan = '';

    public function updatedCari(): void
    {
        $this->resetPage();
    }

    public function updatedTahun(): void
    {
        $this->resetPage();
    }

    public function tambah(): void
    {
        $this->resetForm();
        $this->formTampil = true;
    }

    public function edit(int $id): void
    {
        $k = Kegiatan::findOrFail($id);
        $this->editId = $k->id;
        $this->nama = $k->nama;
        $this->tim = (string) $k->tim;
        $this->jenis = $k->jenis;
        $this->tanggal_mulai = $k->tanggal_mulai->format('Y-m-d');
        $this->tanggal_selesai = $k->tanggal_selesai->format('Y-m-d');
        $this->honor_ppl = $k->honor_ppl;
        $this->honor_pml = $k->honor_pml;
        $this->keterangan = (string) $k->keterangan;
        $this->formTampil = true;
    }

    public function simpan(PenugasanService $penugasan)
    {
        $data = $this->validate([
            'nama' => 'required|string|max:255',
            'tim' => 'nullable|string|max:50',
            'jenis' => 'required|in:'.implode(',', Kegiatan::JENIS),
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'honor_ppl' => 'required|integer|min:0',
            'honor_pml' => 'required|integer|min:0',
            'keterangan' => 'nullable|string|max:1000',
        ], [], [
            'tanggal_mulai' => 'tanggal mulai',
            'tanggal_selesai' => 'tanggal selesai',
            'honor_ppl' => 'honor PPL',
            'honor_pml' => 'honor PML',
        ]);

        if ($this->editId) {
            $k = Kegiatan::findOrFail($this->editId);
            $periodeLama = $k->bulans();
            $k->update($data);
            if ($periodeLama !== $k->fresh()->bulans()) {
                $penugasan->sinkronPeriode($k->fresh());
                session()->flash('sukses', 'Kegiatan diperbarui. Honor petugas disesuaikan dengan periode baru.');
            } else {
                session()->flash('sukses', 'Kegiatan diperbarui.');
            }
            $this->formTampil = false;

            return null;
        }

        $k = Kegiatan::create($data + ['sumber' => 'manual']);
        session()->flash('sukses', 'Kegiatan dibuat. Silakan unggah daftar petugas.');

        return $this->redirectRoute('sibeban.kegiatan.show', $k, navigate: true);
    }

    public function hapus(int $id): void
    {
        $k = Kegiatan::findOrFail($id);
        $nama = $k->nama;
        $k->delete();
        session()->flash('sukses', "Kegiatan \"{$nama}\" beserta penugasannya dihapus.");
    }

    private function resetForm(): void
    {
        $this->reset(['editId', 'nama', 'tim', 'jenis', 'tanggal_mulai', 'tanggal_selesai', 'honor_ppl', 'honor_pml', 'keterangan']);
        $this->resetValidation();
    }

    public function render()
    {
        $svc = app(HonorService::class);

        $kegiatans = Kegiatan::query()
            ->withCount('penugasans')
            ->with('penugasans:id,kegiatan_id,mitra_id,jabatan')
            ->when($this->cari !== '', fn ($q) => $q->where('nama', 'like', "%{$this->cari}%"))
            ->when($this->tahun !== '', fn ($q) => $q
                ->whereDate('tanggal_mulai', '<=', $this->tahun.'-12-31')
                ->whereDate('tanggal_selesai', '>=', $this->tahun.'-01-01'))
            ->orderByDesc('tanggal_mulai')
            ->paginate(15);

        // Hitung jumlah petugas per status untuk tiap kegiatan di halaman ini
        $bulanSemua = $kegiatans->getCollection()->flatMap(fn ($k) => $k->bulans())->unique()->values()->all();
        $mitraSemua = $kegiatans->getCollection()->flatMap(fn ($k) => $k->penugasans->pluck('mitra_id'))->unique()->values()->all();
        $beban = $svc->beban($mitraSemua, $bulanSemua);

        $ringkas = [];
        foreach ($kegiatans as $k) {
            $hit = ['merah' => 0, 'oranye' => 0, 'hijau' => 0, 'kosong' => 0];
            foreach ($k->penugasans as $p) {
                $st = [];
                foreach ($k->bulans() as $b) {
                    $d = $beban[$p->mitra_id][$b] ?? null;
                    $st[] = $d ? $svc->status($d['total'], $d['limit'])['status'] : 'kosong';
                }
                $hit[HonorService::terburuk($st)]++;
            }
            $ringkas[$k->id] = $hit;
        }

        return view('livewire.sibeban.kegiatan.index', [
            'kegiatans' => $kegiatans,
            'ringkas' => $ringkas,
        ]);
    }
}
