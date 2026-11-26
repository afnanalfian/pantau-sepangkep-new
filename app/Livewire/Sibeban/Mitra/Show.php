<?php

namespace App\Livewire\Sibeban\Mitra;

use App\Models\Sibeban\Mitra;
use App\Services\Sibeban\HonorService;
use App\Support\Sibeban\Periode;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.portal')]
class Show extends Component
{
    public Mitra $mitra;

    #[Url]
    public $tahun = null;

    public function mount(Mitra $mitra): void
    {
        $this->mitra = $mitra;
        $this->tahun = (int) ($this->tahun ?: now()->year);
    }

    public function render()
    {
        $svc = app(HonorService::class);
        $this->tahun = (int) $this->tahun;
        $bulans = Periode::bulanTahun($this->tahun);
        $beban = $svc->beban([$this->mitra->id], $bulans)[$this->mitra->id] ?? [];

        $sel = [];
        foreach ($bulans as $b) {
            $d = $beban[$b] ?? ['total' => 0, 'rincian' => []];
            $sel[$b] = ['total' => $d['total'], 'rincian' => $d['rincian']] + $svc->status($d['total']);
        }

        $pulsaBulan = [];
        foreach ($this->mitra->pulsas()->beririsan($bulans[0], end($bulans))->get() as $p) {
            foreach ($bulans as $b) {
                if ($b >= $p->bulan_mulai && $b <= $p->bulan_selesai) {
                    $pulsaBulan[$b] = $p;
                }
            }
        }

        return view('livewire.sibeban.mitra.show', [
            'pulsaBulan' => $pulsaBulan,
            'bulans' => $bulans,
            'sel' => $sel,
            'limit' => $svc->limit(),
            'bulanIni' => now()->format('Y-m'),
        ])->title($this->mitra->nama.' · SIBEBAN');
    }
}
