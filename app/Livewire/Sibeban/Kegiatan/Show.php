<?php

namespace App\Livewire\Sibeban\Kegiatan;

use App\Livewire\Sibeban\Monitoring;
use App\Models\Sibeban\Kegiatan;
use App\Models\Sibeban\Mitra;
use App\Models\Sibeban\Penugasan;
use App\Services\Sibeban\HonorExcelReader;
use App\Services\Sibeban\HonorService;
use App\Services\Sibeban\PenugasanService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.portal')]
class Show extends Component
{
    use WithFileUploads;

    public Kegiatan $kegiatan;

    // ---- Daftar petugas ----
    public string $cari = '';
    public string $filterStatus = '';     // '' | belum | merah | oranye | hijau

    // ---- Pilih petugas ----
    public bool $pilihTampil = false;
    public string $pCari = '';
    public string $pKecamatan = '';
    public string $pDesa = '';
    public string $pPosisi = '';
    public bool $pHanyaTersedia = true;
    public int $pBatas = 50;
    public array $pDipilih = [];          // mitra_id terpilih (belum disimpan)

    // ---- Impor honor ----
    public bool $imporTampil = false;
    public $file;
    public array $pratinjau = [];
    public array $uploadErrors = [];
    public array $uploadWarnings = [];

    // ---- Ubah satu petugas ----
    public ?int $editId = null;
    public string $editJabatan = '';
    public array $editHonor = [];

    public ?int $lepasId = null;

    public function mount(Kegiatan $kegiatan): void
    {
        $this->kegiatan = $kegiatan;
    }

    private function wajibBisaUbah(): void
    {
        abort_unless($this->kegiatan->bisaDiubah(), 403, 'Hanya admin atau pembuat kegiatan yang dapat mengubah petugas.');
    }

    // =====================================================================
    // Pilih petugas
    // =====================================================================

    public function bukaPilih(): void
    {
        $this->wajibBisaUbah();
        $this->reset(['pCari', 'pKecamatan', 'pDesa', 'pPosisi', 'pDipilih']);
        $this->pHanyaTersedia = true;
        $this->pBatas = 50;
        $this->pilihTampil = true;
    }

    public function updatedPKecamatan(): void
    {
        $this->pDesa = '';
        $this->pBatas = 50;
    }

    public function updated(string $p): void
    {
        if (in_array($p, ['pCari', 'pDesa', 'pPosisi', 'pHanyaTersedia'], true)) {
            $this->pBatas = 50;
        }
    }

    /** Kandidat: mitra yang belum ada di kegiatan ini + kalkulasi honor di bulan kegiatan. */
    private function kandidat(): array
    {
        $svc = app(HonorService::class);
        $bulans = $this->kegiatan->bulans();
        $sudah = $this->kegiatan->penugasans()->pluck('mitra_id')->all();

        $mitras = Mitra::query()
            ->whereNotIn('id', $sudah)
            ->when($this->pCari !== '', fn ($q) => $q->where(fn ($w) => $w->where('nama', 'like', "%{$this->pCari}%")->orWhere('sobat_id', 'like', "%{$this->pCari}%")))
            ->when($this->pKecamatan !== '', fn ($q) => $q->where('kecamatan', $this->pKecamatan))
            ->when($this->pDesa !== '', fn ($q) => $q->where('desa', $this->pDesa))
            ->when($this->pPosisi !== '', fn ($q) => $q->where('posisi', $this->pPosisi))
            ->orderBy('kecamatan')->orderBy('desa')->orderBy('nama')
            ->get(['id', 'sobat_id', 'nama', 'kecamatan', 'desa', 'posisi']);

        $beban = $svc->beban($mitras->pluck('id')->all(), $bulans);
        $hasil = [];
        foreach ($mitras as $m) {
            $calc = $svc->hitung($beban[$m->id] ?? [], [], $bulans);
            if ($this->pHanyaTersedia && $calc['sisa_min'] <= 0) {
                continue;
            }
            $hasil[] = ['mitra' => $m, 'calc' => $calc];
        }

        return $hasil;
    }

    public function pilihSemua(): void
    {
        $this->wajibBisaUbah();
        $ids = array_map(fn ($k) => $k['mitra']->id, $this->kandidat());
        $this->pDipilih = array_values(array_unique(array_merge(array_map('intval', $this->pDipilih), $ids)));
    }

    public function kosongkanPilihan(): void
    {
        $this->pDipilih = [];
    }

    public function simpanPilihan(PenugasanService $svc): void
    {
        $this->wajibBisaUbah();
        $n = $svc->pilih($this->kegiatan, $this->pDipilih);
        $this->pilihTampil = false;
        $this->pDipilih = [];
        session()->flash('sukses', "{$n} petugas ditambahkan. Unduh template untuk mengisi jabatan & honor mereka.");
    }

    // =====================================================================
    // Impor template
    // =====================================================================

    public function bukaImpor(): void
    {
        $this->wajibBisaUbah();
        $this->batalPratinjau();
        $this->imporTampil = true;
    }

    public function updatedFile(HonorExcelReader $reader): void
    {
        $this->wajibBisaUbah();
        $this->resetErrorBag('file');
        $this->validate(['file' => 'required|file|extensions:xlsx,xls,csv|max:10240'], [], ['file' => 'file Excel']);

        $hasil = $reader->baca($this->file->getRealPath(), $this->kegiatan);
        $this->uploadErrors = $hasil['errors'];
        $this->uploadWarnings = $hasil['warnings'];

        // Hitung status: honor kegiatan lain + honor di template
        $svc = app(HonorService::class);
        $bulans = $this->kegiatan->bulans();
        $lain = $svc->beban(array_column($hasil['rows'], 'mitra_id'), $bulans, $this->kegiatan->id);
        foreach ($hasil['rows'] as $i => $r) {
            $hasil['rows'][$i]['calc'] = $svc->hitung($lain[$r['mitra_id']] ?? [], $r['honor'], $bulans);
        }
        usort($hasil['rows'], fn ($a, $b) => [HonorService::URUTAN[$b['calc']['status']], $b['calc']['persen_maks']]
            <=> [HonorService::URUTAN[$a['calc']['status']], $a['calc']['persen_maks']]);
        $this->pratinjau = $hasil['rows'];
    }

    public function buangDariPratinjau(int $i): void
    {
        unset($this->pratinjau[$i]);
        $this->pratinjau = array_values($this->pratinjau);
    }

    public function batalPratinjau(): void
    {
        $this->reset(['file', 'pratinjau', 'uploadErrors', 'uploadWarnings']);
        $this->resetErrorBag();
    }

    public function simpanImpor(PenugasanService $svc): void
    {
        $this->wajibBisaUbah();
        if (empty($this->pratinjau)) {
            return;
        }
        $stat = $svc->simpanImpor($this->kegiatan, array_map(fn ($r) => [
            'mitra_id' => $r['mitra_id'], 'jabatan' => $r['jabatan'], 'honor' => $r['honor'],
        ], $this->pratinjau));
        $lebih = count(array_filter($this->pratinjau, fn ($r) => $r['calc']['status'] === 'merah'));
        $this->batalPratinjau();
        $this->imporTampil = false;
        session()->flash('sukses', "Honor tersimpan: {$stat['diperbarui']} petugas diperbarui".($stat['baru'] ? ", {$stat['baru']} petugas baru ditambahkan" : '').'.'
            .($lebih ? " Perhatian: {$lebih} petugas melebihi limit bulanan." : ''));
    }

    // =====================================================================
    // Ubah / lepas satu petugas
    // =====================================================================

    public function ubah(int $penugasanId): void
    {
        $this->wajibBisaUbah();
        $p = Penugasan::with('honors')->where('kegiatan_id', $this->kegiatan->id)->findOrFail($penugasanId);
        $this->editId = $p->id;
        $this->editJabatan = (string) $p->jabatan;
        $ada = $p->honorPerBulan();
        $this->editHonor = [];
        foreach ($this->kegiatan->bulans() as $b) {
            $this->editHonor[$b] = (int) ($ada[$b] ?? 0);
        }
        $this->resetValidation();
    }

    public function simpanUbah(PenugasanService $svc): void
    {
        $this->wajibBisaUbah();
        $this->validate([
            'editJabatan' => 'nullable|string|max:60',
            'editHonor.*' => 'required|integer|min:0',
        ], [], ['editHonor.*' => 'honor', 'editJabatan' => 'jabatan']);
        $p = Penugasan::with('mitra')->where('kegiatan_id', $this->kegiatan->id)->findOrFail($this->editId);
        $svc->simpan($this->kegiatan, $p->mitra_id, $this->editJabatan, array_map('intval', $this->editHonor));
        $this->editId = null;
        session()->flash('sukses', "Jabatan & honor {$p->mitra->nama} diperbarui.");
    }

    public function lepas(PenugasanService $svc): void
    {
        $this->wajibBisaUbah();
        $p = $svc->lepas($this->kegiatan, (int) $this->lepasId);
        $this->lepasId = null;
        if ($p) {
            session()->flash('sukses', "{$p->mitra->nama} dikeluarkan dari kegiatan ini.");
        }
    }

    public function filter(string $s): void
    {
        $this->filterStatus = $this->filterStatus === $s ? '' : $s;
    }

    public function render()
    {
        $svc = app(HonorService::class);
        $this->kegiatan->refresh();
        $bulans = $this->kegiatan->bulans();

        $penugasans = $this->kegiatan->penugasans()->with(['mitra', 'honors'])->get();
        $bebanLain = $svc->beban($penugasans->pluck('mitra_id')->all(), $bulans, $this->kegiatan->id);

        $ringkas = ['belum' => 0, 'hijau' => 0, 'oranye' => 0, 'merah' => 0];
        $petugas = [];
        foreach ($penugasans as $p) {
            $honor = $p->honorPerBulan();
            $calc = $svc->hitung($bebanLain[$p->mitra_id] ?? [], $honor, $bulans);
            $belum = array_sum($honor) === 0;
            $kunci = $belum ? 'belum' : ($calc['status'] === 'kosong' ? 'hijau' : $calc['status']);
            $ringkas[$kunci]++;
            if ($this->filterStatus !== '' && $kunci !== $this->filterStatus) {
                continue;
            }
            if ($this->cari !== '' && ! str_contains(mb_strtolower($p->mitra->nama.' '.$p->mitra->sobat_id.' '.$p->jabatan.' '.$p->mitra->desa.' '.$p->mitra->kecamatan), mb_strtolower($this->cari))) {
                continue;
            }
            $petugas[] = ['p' => $p, 'calc' => $calc, 'belum' => $belum];
        }
        usort($petugas, fn ($a, $b) => [$a['belum'], -HonorService::URUTAN[$a['calc']['status']], (string) $a['p']->mitra->kecamatan, $a['p']->mitra->nama]
            <=> [$b['belum'], -HonorService::URUTAN[$b['calc']['status']], (string) $b['p']->mitra->kecamatan, $b['p']->mitra->nama]);

        $kandidat = $this->pilihTampil ? $this->kandidat() : [];

        return view('livewire.sibeban.kegiatan.show', [
            'bulans' => $bulans,
            'petugas' => $petugas,
            'ringkas' => $ringkas,
            'jumlahPetugas' => $penugasans->count(),
            'totalHonor' => $penugasans->sum(fn ($p) => $p->honors->sum('jumlah')),
            'limit' => $svc->limit(),
            'bisa' => $this->kegiatan->bisaDiubah(),
            'kandidat' => array_slice($kandidat, 0, $this->pBatas),
            'jumlahKandidat' => count($kandidat),
            'opsi' => $this->pilihTampil ? Monitoring::opsiWilayah($this->pKecamatan) : null,
            'editPetugas' => $this->editId ? $penugasans->firstWhere('id', $this->editId) : null,
        ])->title($this->kegiatan->nama.' · SIBEBAN');
    }
}
