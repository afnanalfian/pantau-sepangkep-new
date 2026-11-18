<?php

namespace App\Livewire\Sibeban;

use App\Models\Sibeban\HonorBulanan;
use App\Models\Sibeban\Mitra;
use App\Services\Sibeban\HonorService;
use App\Support\Sibeban\Periode;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.portal')]
#[Title('Monitoring Honor Mitra')]
class Monitoring extends Component
{
    use WithPagination;

    #[Url]
    public $tahun = null;

    #[Url(except: '')]
    public string $bulan = '';      // '' = status terburuk sepanjang tahun

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $cari = '';

    #[Url(except: '')]
    public string $posisi = '';

    #[Url(except: 'persen')]
    public string $urut = 'persen';

    public $perHalaman = 25;

    public function mount(): void
    {
        $this->tahun = (int) ($this->tahun ?: now()->year);
    }

    public function updated(string $prop): void
    {
        if (in_array($prop, ['tahun', 'bulan', 'status', 'cari', 'posisi', 'urut', 'perHalaman'], true)) {
            $this->resetPage();
        }
        if ($prop === 'tahun') {
            $this->bulan = '';
        }
    }

    public function filterStatus(string $s): void
    {
        $this->status = $this->status === $s ? '' : $s;
        $this->resetPage();
    }

    public function render()
    {
        $svc = app(HonorService::class);
        $this->tahun = (int) $this->tahun;
        $this->perHalaman = max(1, (int) $this->perHalaman);
        $bulans = Periode::bulanTahun($this->tahun);
        $beban = $svc->beban(null, $bulans);

        $mitras = Mitra::query()
            ->when($this->cari !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('nama', 'like', "%{$this->cari}%")
                ->orWhere('sobat_id', 'like', "%{$this->cari}%")))
            ->when($this->posisi !== '', fn ($q) => $q->where('posisi', $this->posisi))
            ->orderBy('nama')
            ->get(['id', 'sobat_id', 'nama', 'posisi']);

        $ringkas = ['merah' => 0, 'oranye' => 0, 'hijau' => 0, 'kosong' => 0];
        $baris = [];
        foreach ($mitras as $m) {
            $sel = [];
            foreach ($bulans as $b) {
                $d = $beban[$m->id][$b] ?? null;
                $st = $d ? $svc->status($d['total'], $d['limit']) : ['persen' => 0.0, 'status' => 'kosong'];
                $sel[$b] = [
                    'total' => $d['total'] ?? 0,
                    'limit' => $d['limit'] ?? 0,
                    'persen' => $st['persen'],
                    'status' => $st['status'],
                    'kegiatan' => $d ? collect($d['rincian'])->pluck('kegiatan')->unique()->values()->all() : [],
                ];
            }

            if ($this->bulan !== '' && isset($sel[$this->bulan])) {
                $fokus = $sel[$this->bulan]['status'];
                $persen = $sel[$this->bulan]['persen'];
            } else {
                $fokus = HonorService::terburuk(array_column($sel, 'status'));
                $persen = max(array_column($sel, 'persen'));
            }
            $ringkas[$fokus]++;

            if ($this->status !== '' && $fokus !== $this->status) {
                continue;
            }
            $baris[] = [
                'mitra' => $m,
                'sel' => $sel,
                'fokus' => $fokus,
                'persen' => $persen,
                'total_tahun' => array_sum(array_column($sel, 'total')),
            ];
        }

        usort($baris, match ($this->urut) {
            'nama' => fn ($a, $b) => strcasecmp($a['mitra']->nama, $b['mitra']->nama),
            'total' => fn ($a, $b) => $b['total_tahun'] <=> $a['total_tahun'],
            'sisa' => fn ($a, $b) => [$a['persen'], $a['total_tahun']] <=> [$b['persen'], $b['total_tahun']],
            default => fn ($a, $b) => [$b['persen'], $b['total_tahun']] <=> [$a['persen'], $a['total_tahun']],
        });

        $total = count($baris);
        $jumlahHalaman = max(1, (int) ceil($total / $this->perHalaman));
        $halaman = min(max(1, (int) $this->getPage()), $jumlahHalaman);

        $daftarTahun = HonorBulanan::query()->distinct()->pluck('bulan')
            ->map(fn ($b) => (int) substr($b, 0, 4))
            ->push((int) now()->year, (int) $this->tahun)
            ->unique()->sortDesc()->values();

        return view('livewire.sibeban.monitoring', [
            'bulans' => $bulans,
            'baris' => array_slice($baris, ($halaman - 1) * $this->perHalaman, $this->perHalaman),
            'total' => $total,
            'halaman' => $halaman,
            'jumlahHalaman' => $jumlahHalaman,
            'ringkas' => $ringkas,
            'daftarTahun' => $daftarTahun,
            'daftarPosisi' => Mitra::query()->whereNotNull('posisi')->distinct()->orderBy('posisi')->pluck('posisi'),
        ]);
    }
}
