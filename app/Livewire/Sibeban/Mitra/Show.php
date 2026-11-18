<?php

namespace App\Livewire\Sibeban\Mitra;

use App\Models\Sibeban\HonorBerjalan;
use App\Models\Sibeban\Kegiatan;
use App\Models\Sibeban\Mitra;
use App\Models\Sibeban\Penugasan;
use App\Services\Sibeban\HonorService;
use App\Services\Sibeban\PenugasanService;
use App\Support\Sibeban\Periode;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.portal')]
class Show extends Component
{
    public Mitra $mitra;

    public int $tahun;

    // Tugaskan ke kegiatan
    public string $kegiatanId = '';
    public string $jabatan = 'ppl';

    public function mount(Mitra $mitra): void
    {
        $this->mitra = $mitra;
        $this->tahun = (int) now()->year;
    }

    public function lepas(int $penugasanId, PenugasanService $service): void
    {
        $p = Penugasan::with('kegiatan')->where('mitra_id', $this->mitra->id)->findOrFail($penugasanId);
        $nama = $p->kegiatan->nama;
        $service->lepas($p->id);
        session()->flash('sukses', "Dilepas dari kegiatan \"{$nama}\".");
    }

    public function tugaskan(PenugasanService $service): void
    {
        $this->validate(['kegiatanId' => 'required|exists:sibeban_kegiatans,id', 'jabatan' => 'required|in:ppl,pml'], [], ['kegiatanId' => 'kegiatan']);
        $k = Kegiatan::findOrFail($this->kegiatanId);
        $service->tambahDefault($k, $this->mitra, $this->jabatan);
        session()->flash('sukses', "{$this->mitra->nama} ditugaskan ke \"{$k->nama}\" sebagai ".strtoupper($this->jabatan).'.');
        $this->kegiatanId = '';
    }

    public function render()
    {
        $svc = app(HonorService::class);
        $bulans = Periode::bulanTahun($this->tahun);
        $beban = $svc->beban([$this->mitra->id], $bulans)[$this->mitra->id] ?? [];

        $sel = [];
        foreach ($bulans as $b) {
            $d = $beban[$b] ?? null;
            $st = $d ? $svc->status($d['total'], $d['limit']) : ['persen' => 0.0, 'status' => 'kosong'];
            $sel[$b] = ['total' => $d['total'] ?? 0, 'limit' => $d['limit'] ?? 0] + $st;
        }

        $penugasans = $this->mitra->penugasans()
            ->with(['kegiatan', 'honors'])
            ->get()
            ->sortByDesc(fn ($p) => $p->kegiatan->tanggal_mulai)
            ->values();

        // Simulasi bila ditugaskan ke kegiatan terpilih
        $simulasi = null;
        $kegiatanPilihan = null;
        if ($this->kegiatanId !== '' && ($kegiatanPilihan = Kegiatan::find($this->kegiatanId))) {
            $bk = $kegiatanPilihan->bulans();
            $bebanK = $svc->beban([$this->mitra->id], $bk, $kegiatanPilihan->id)[$this->mitra->id] ?? [];
            $simulasi = $svc->hitung(
                $bebanK,
                array_fill_keys($bk, $kegiatanPilihan->honorDefault($this->jabatan)),
                $svc->limitFor($kegiatanPilihan->jenis, $this->jabatan),
                $bk
            );
            $simulasi['lain'] = HonorService::kegiatanLain($bebanK);
        }

        $sudah = $penugasans->pluck('kegiatan_id')->all();

        return view('livewire.sibeban.mitra.show', [
            'bulans' => $bulans,
            'sel' => $sel,
            'penugasans' => $penugasans,
            'simulasi' => $simulasi,
            'rekap' => HonorBerjalan::where('mitra_id', $this->mitra->id)->whereIn('bulan', $bulans)->orderBy('bulan')->get(),
            'kegiatanPilihan' => $kegiatanPilihan,
            'pilihanKegiatan' => Kegiatan::query()
                ->whereNotIn('id', $sudah)
                ->whereDate('tanggal_selesai', '>=', now()->subMonths(2)->startOfMonth())
                ->orderBy('tanggal_mulai')
                ->get(['id', 'nama', 'tanggal_mulai', 'tanggal_selesai']),
        ])->title($this->mitra->nama);
    }
}
