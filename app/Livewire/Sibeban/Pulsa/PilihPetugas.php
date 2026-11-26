<?php

namespace App\Livewire\Sibeban\Pulsa;

use App\Livewire\Sibeban\Monitoring;
use App\Services\Sibeban\PulsaService;

/** Daftar centang petugas pulsa (dipakai form pulsa baru & tambah petugas). */
trait PilihPetugas
{
    public string $pCari = '';

    public string $pKecamatan = '';

    public string $pDesa = '';

    public array $pDipilih = [];

    public int $pBatas = 100;

    abstract protected function periodePilih(): array; // [mulai, selesai]

    public function updatedPKecamatan(): void
    {
        $this->pDesa = '';
    }

    protected function resetPilih(): void
    {
        $this->reset(['pCari', 'pKecamatan', 'pDesa', 'pDipilih', 'pBatas']);
        $this->resetErrorBag('pDipilih');
    }

    private function kandidatSemua(): array
    {
        [$mulai, $selesai] = $this->periodePilih();

        return app(PulsaService::class)->kandidat($mulai, $selesai, ['cari' => $this->pCari, 'kecamatan' => $this->pKecamatan, 'desa' => $this->pDesa]);
    }

    public function pilihSemua(): void
    {
        $ids = array_map(fn ($k) => (string) $k['mitra']->id, $this->kandidatSemua());
        $this->pDipilih = array_values(array_unique(array_merge($this->pDipilih, $ids)));
    }

    public function kosongkanPilihan(): void
    {
        $this->pDipilih = [];
    }

    /** Data untuk partial pilih-petugas. */
    protected function dataPilih(): array
    {
        $semua = $this->kandidatSemua();

        return [
            'kandidat' => array_slice($semua, 0, $this->pBatas),
            'jumlahKandidat' => count($semua),
            'opsi' => Monitoring::opsiWilayah($this->pKecamatan),
        ];
    }
}
