<?php

namespace App\Livewire\Sibeban\Pulsa;

use App\Models\Sibeban\Pulsa;
use App\Services\Sibeban\PulsaService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.portal')]
class Show extends Component
{
    use PilihPetugas;

    public Pulsa $pulsa;

    public string $cari = '';

    public bool $pilihTampil = false;

    public bool $ubahTampil = false;

    public string $jumlah = '';

    public string $nama = '';

    public ?int $lepasId = null;

    public function mount(Pulsa $pulsa): void
    {
        $this->pulsa = $pulsa;
    }

    protected function periodePilih(): array
    {
        return [$this->pulsa->bulan_mulai, $this->pulsa->bulan_selesai];
    }

    public function bukaPilih(): void
    {
        abort_unless($this->pulsa->bisaDiubah(), 403);
        $this->resetPilih();
        $this->pilihTampil = true;
    }

    public function simpanPilihan(PulsaService $svc): void
    {
        abort_unless($this->pulsa->bisaDiubah(), 403);
        $n = $svc->tambahPetugas($this->pulsa, $this->pDipilih);
        $this->pilihTampil = false;
        $this->resetPilih();
        session()->flash('sukses', "{$n} petugas ditambahkan.");
    }

    public function bukaUbah(): void
    {
        abort_unless($this->pulsa->bisaDiubah(), 403);
        $this->jumlah = (string) $this->pulsa->jumlah;
        $this->nama = (string) $this->pulsa->nama;
        $this->resetValidation();
        $this->ubahTampil = true;
    }

    public function simpanUbah(): void
    {
        abort_unless($this->pulsa->bisaDiubah(), 403);
        $this->jumlah = preg_replace('/\D/', '', $this->jumlah);
        $d = $this->validate(['jumlah' => 'required|integer|min:1000|max:10000000', 'nama' => 'nullable|string|max:150'], [], ['jumlah' => 'jumlah pulsa', 'nama' => 'keterangan']);
        $this->pulsa->update(['jumlah' => (int) $d['jumlah'], 'nama' => $d['nama'] ?: null]);
        $this->ubahTampil = false;
        session()->flash('sukses', 'Pengaturan pulsa diperbarui.');
    }

    public function lepas(): void
    {
        abort_unless($this->pulsa->bisaDiubah(), 403);
        $m = $this->pulsa->mitras()->find($this->lepasId);
        $this->pulsa->mitras()->detach($this->lepasId);
        $this->lepasId = null;
        session()->flash('sukses', ($m?->nama ?? 'Petugas').' dikeluarkan dari pulsa ini.');
    }

    public function render(PulsaService $svc)
    {
        $p = $this->pulsa;
        $mitras = $p->mitras()
            ->when(trim($this->cari) !== '', fn ($q) => $q->where(fn ($w) => $w->where('nama', 'like', '%'.trim($this->cari).'%')->orWhere('sobat_id', 'like', '%'.trim($this->cari).'%')))
            ->orderBy('nama')->get();
        $total = $p->mitras()->count();

        return view('livewire.sibeban.pulsa.show', [
            'mitras' => $mitras,
            'totalPetugas' => $total,
            'kegiatan' => $svc->kegiatanPerMitra($p->bulan_mulai, $p->bulan_selesai, $mitras->pluck('id')->all()),
        ] + ($this->pilihTampil ? $this->dataPilih() : ['kandidat' => [], 'jumlahKandidat' => 0, 'opsi' => null]))
            ->title('Pulsa '.$p->periode(false).' · SIBEBAN');
    }
}
