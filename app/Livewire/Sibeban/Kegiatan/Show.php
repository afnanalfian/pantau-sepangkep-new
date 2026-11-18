<?php

namespace App\Livewire\Sibeban\Kegiatan;

use App\Models\Sibeban\HonorBerjalan;
use App\Models\Sibeban\Kegiatan;
use App\Models\Sibeban\Mitra;
use App\Models\Sibeban\Penugasan;
use App\Services\Sibeban\HonorService;
use App\Services\Sibeban\PenugasanExcelReader;
use App\Services\Sibeban\PenugasanService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.portal')]
class Show extends Component
{
    use WithFileUploads;

    public Kegiatan $kegiatan;

    // ---- Upload & pratinjau ----
    public $file;
    public array $pratinjau = [];
    public array $uploadErrors = [];
    public array $uploadWarnings = [];

    // ---- Tabel petugas ----
    public string $cari = '';
    public string $filterStatus = '';
    public ?int $aturId = null;          // penugasan yang panel "Atur"-nya terbuka
    public string $editJabatan = 'ppl';
    public array $editHonor = [];        // honor kegiatan ini per bulan
    public array $editBerjalan = [];     // honor berjalan (rekap) per bulan
    public array $berjalanAwal = [];

    // ---- Rekomendasi ----
    public bool $rekTampil = false;
    public string $rekJabatan = 'ppl';
    public string $rekCari = '';
    public bool $rekSembunyikanMerah = true;

    public function mount(Kegiatan $kegiatan): void
    {
        $this->kegiatan = $kegiatan;
    }

    // =====================================================================
    // Upload Excel → pratinjau hasil kalkulasi → simpan
    // =====================================================================

    public function pratinjauUpload(PenugasanExcelReader $reader): void
    {
        $this->validate([
            'file' => 'required|file|extensions:xlsx,xls,csv|max:10240',
        ], [], ['file' => 'file Excel']);

        $hasil = $reader->baca($this->file->getRealPath(), $this->kegiatan);
        $this->uploadErrors = $hasil['errors'];
        $this->uploadWarnings = $hasil['warnings'];
        $this->pratinjau = $hasil['rows'];
        $this->hitungPratinjau();
    }

    /**
     * Hitung status setiap baris pratinjau:
     *   total per bulan = honor berjalan (dari Excel) + honor kegiatan ini (honor PPL/PML form kegiatan)
     */
    private function hitungPratinjau(): void
    {
        if (empty($this->pratinjau)) {
            return;
        }
        $svc = app(HonorService::class);
        $bulans = $this->kegiatan->bulans();
        $mitra = Mitra::whereIn('sobat_id', array_column($this->pratinjau, 'sobat_id'))->get()->keyBy('sobat_id');
        $sudah = $this->kegiatan->penugasans()->pluck('mitra_id')->flip();

        foreach ($this->pratinjau as $i => $r) {
            $m = $mitra[$r['sobat_id']] ?? null;
            $honor = array_fill_keys($bulans, $this->kegiatan->honorDefault($r['jabatan']));
            $berjalan = [];
            foreach ($bulans as $b) {
                $berjalan[$b] = ['total' => (int) ($r['berjalan'][$b] ?? 0), 'limit' => 0, 'rincian' => []];
            }
            $this->pratinjau[$i]['honor'] = $honor;
            $this->pratinjau[$i]['mitra_id'] = $m?->id;
            $this->pratinjau[$i]['nama_db'] = $m?->nama ?? ($r['nama'] !== '' ? $r['nama'] : '(belum terdaftar)');
            $this->pratinjau[$i]['mitra_baru'] = $m === null;
            $this->pratinjau[$i]['sudah'] = $m && isset($sudah[$m->id]);
            $this->pratinjau[$i]['calc'] = $svc->hitung($berjalan, $honor, $svc->limitFor($this->kegiatan->jenis, $r['jabatan']), $bulans);
        }

        // Urutkan: merah → oranye → hijau, lalu % tertinggi
        usort($this->pratinjau, fn ($a, $b) => [HonorService::URUTAN[$b['calc']['status']], $b['calc']['persen_maks']]
            <=> [HonorService::URUTAN[$a['calc']['status']], $a['calc']['persen_maks']]);
    }

    public function buangDariPratinjau(int $i): void
    {
        unset($this->pratinjau[$i]);
        $this->pratinjau = array_values($this->pratinjau);
    }

    public function buangMerahDariPratinjau(): void
    {
        $this->pratinjau = array_values(array_filter($this->pratinjau, fn ($r) => $r['calc']['status'] !== 'merah'));
    }

    public function batalPratinjau(): void
    {
        $this->reset(['file', 'pratinjau', 'uploadErrors', 'uploadWarnings']);
    }

    public function simpanPratinjau(PenugasanService $service): void
    {
        if (empty($this->pratinjau)) {
            return;
        }
        $rows = array_map(fn ($r) => [
            'sobat_id' => $r['sobat_id'],
            'nama' => $r['nama'],
            'jabatan' => $r['jabatan'],
            'berjalan' => $r['berjalan'],
            'honor' => $r['honor'],
        ], $this->pratinjau);

        $stat = $service->simpanUpload($this->kegiatan, $rows);
        $this->batalPratinjau();
        session()->flash('sukses', "Tersimpan: {$stat['baru']} petugas baru, {$stat['diperbarui']} diperbarui"
            .($stat['mitra_baru'] ? ", {$stat['mitra_baru']} mitra baru ditambahkan ke master" : '').'.');
    }

    // =====================================================================
    // Pengelolaan petugas
    // =====================================================================

    public function atur(int $penugasanId): void
    {
        if ($this->aturId === $penugasanId) {
            $this->aturId = null;

            return;
        }
        $p = Penugasan::with('honors')->where('kegiatan_id', $this->kegiatan->id)->findOrFail($penugasanId);
        $this->aturId = $p->id;
        $this->editJabatan = $p->jabatan;
        $ada = $p->honorPerBulan();
        $rekap = HonorBerjalan::where('mitra_id', $p->mitra_id)->whereIn('bulan', $this->kegiatan->bulans())->pluck('jumlah', 'bulan');
        $this->editHonor = [];
        $this->editBerjalan = [];
        foreach ($this->kegiatan->bulans() as $b) {
            $this->editHonor[$b] = $ada[$b] ?? 0;
            $this->editBerjalan[$b] = (int) ($rekap[$b] ?? 0);
        }
        $this->berjalanAwal = $this->editBerjalan;
        $this->resetValidation();
    }

    public function simpanEdit(PenugasanService $service): void
    {
        $this->validate([
            'editJabatan' => 'required|in:ppl,pml',
            'editHonor.*' => 'required|integer|min:0',
            'editBerjalan.*' => 'required|integer|min:0',
        ], [], ['editHonor.*' => 'honor', 'editBerjalan.*' => 'honor berjalan']);

        $p = Penugasan::with('mitra')->where('kegiatan_id', $this->kegiatan->id)->findOrFail($this->aturId);
        $service->simpan($this->kegiatan, $p->mitra, $this->editJabatan, array_map('intval', $this->editHonor));

        // Simpan honor berjalan hanya untuk bulan yang benar-benar diubah
        $berubah = [];
        foreach ($this->editBerjalan as $b => $v) {
            if ((int) $v !== (int) ($this->berjalanAwal[$b] ?? -1)) {
                $berubah[$b] = (int) $v;
            }
        }
        if ($berubah) {
            $service->simpanBerjalan($p->mitra, $this->kegiatan, $berubah, 'Diubah manual di kegiatan: '.$this->kegiatan->nama);
            $this->berjalanAwal = array_map('intval', $this->editBerjalan);
        }
        session()->flash('sukses', "Honor {$p->mitra->nama} diperbarui.");
    }

    public function isiDefault(): void
    {
        foreach ($this->editHonor as $b => $v) {
            $this->editHonor[$b] = $this->kegiatan->honorDefault($this->editJabatan);
        }
    }

    /**
     * Lepas penugasan — bisa penugasan di kegiatan ini, ATAU penugasan mitra
     * di kegiatan lain (agar limitnya longgar untuk kegiatan ini).
     */
    public function lepas(int $penugasanId, PenugasanService $service): void
    {
        $p = $service->lepas($penugasanId);
        if (! $p) {
            return;
        }
        if ($p->kegiatan_id === $this->kegiatan->id && $this->aturId === $p->id) {
            $this->aturId = null;
        }
        session()->flash('sukses', "{$p->mitra->nama} dilepas dari kegiatan \"{$p->kegiatan->nama}\".");
    }

    public function tambahPetugas(int $mitraId, string $jabatan, PenugasanService $service): void
    {
        abort_unless(in_array($jabatan, ['ppl', 'pml'], true), 422);
        $m = Mitra::findOrFail($mitraId);
        $service->tambahDefault($this->kegiatan, $m, $jabatan);
        session()->flash('sukses', "{$m->nama} ditambahkan sebagai ".strtoupper($jabatan).'.');
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

        $ringkas = ['merah' => 0, 'oranye' => 0, 'hijau' => 0, 'kosong' => 0];
        $petugas = [];
        foreach ($penugasans as $p) {
            $bm = $bebanLain[$p->mitra_id] ?? [];
            $calc = $svc->hitung($bm, $p->honorPerBulan(), $svc->limitFor($this->kegiatan->jenis, $p->jabatan), $bulans);
            $ringkas[$calc['status']]++;

            if ($this->filterStatus !== '' && $calc['status'] !== $this->filterStatus) {
                continue;
            }
            if ($this->cari !== '' && ! str_contains(strtolower($p->mitra->nama.' '.$p->mitra->sobat_id), strtolower($this->cari))) {
                continue;
            }
            $petugas[] = ['p' => $p, 'calc' => $calc, 'lain' => HonorService::kegiatanLain($bm)];
        }
        usort($petugas, fn ($a, $b) => [HonorService::URUTAN[$b['calc']['status']], $b['calc']['persen_maks']]
            <=> [HonorService::URUTAN[$a['calc']['status']], $a['calc']['persen_maks']]);

        return view('livewire.sibeban.kegiatan.show', [
            'bulans' => $bulans,
            'petugas' => $petugas,
            'ringkas' => $ringkas,
            'jumlahPetugas' => $penugasans->count(),
            'totalHonor' => $penugasans->sum(fn ($p) => $p->honors->sum('jumlah')),
            'limitPpl' => $svc->limitFor($this->kegiatan->jenis, 'ppl'),
            'limitPml' => $svc->limitFor($this->kegiatan->jenis, 'pml'),
            'rekomendasi' => $this->rekTampil
                ? $svc->rekomendasi($this->kegiatan, $this->rekJabatan, $this->rekCari, 25, $this->rekSembunyikanMerah)
                : [],
        ])->title($this->kegiatan->nama);
    }
}
