<?php

namespace App\Livewire\Sibeban;

use App\Models\Sibeban\Mitra;
use App\Services\Sibeban\HonorService;
use App\Support\Sibeban\Periode;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.portal')]
#[Title('SIBEBAN · Ketersediaan Mitra')]
class Monitoring extends Component
{
    use WithPagination;

    #[Url]
    public string $bulan = '';            // 'YYYY-MM'

    #[Url(except: 'tersedia')]
    public string $ketersediaan = 'tersedia'; // semua | tersedia | mendekati | penuh

    #[Url(except: '')]
    public string $cari = '';

    #[Url(except: '')]
    public string $kecamatan = '';

    #[Url(except: '')]
    public string $desa = '';

    #[Url(except: '')]
    public string $posisi = '';

    #[Url(except: 'sisa')]
    public string $urut = 'sisa';

    public int $perHalaman = 25;

    public function mount(): void
    {
        $this->bulan = preg_match('/^\d{4}-\d{2}$/', $this->bulan) ? $this->bulan : now()->format('Y-m');
    }

    public function updated(string $prop): void
    {
        if ($prop === 'kecamatan') {
            $this->desa = '';
        }
        if ($prop === 'bulan' && ! preg_match('/^\d{4}-\d{2}$/', $this->bulan)) {
            $this->bulan = now()->format('Y-m');
        }
        $this->resetPage();
    }

    public function geserBulan(int $n): void
    {
        $this->bulan = \Carbon\Carbon::createFromFormat('Y-m-d', $this->bulan.'-01')->addMonthsNoOverflow($n)->format('Y-m');
        $this->resetPage();
    }

    public function setKetersediaan(string $k): void
    {
        $this->ketersediaan = $k;
        $this->resetPage();
    }

    /**
     * Baris monitoring satu bulan untuk semua mitra yang lolos filter wilayah/cari.
     * Dipakai juga oleh ekspor Excel.
     */
    public static function data(string $bulan, array $f): array
    {
        $svc = app(HonorService::class);
        $mitras = Mitra::query()
            ->when($f['cari'] ?? '', fn ($q, $c) => $q->where(fn ($w) => $w->where('nama', 'like', "%{$c}%")->orWhere('sobat_id', 'like', "%{$c}%")))
            ->when($f['kecamatan'] ?? '', fn ($q, $v) => $q->where('kecamatan', $v))
            ->when($f['desa'] ?? '', fn ($q, $v) => $q->where('desa', $v))
            ->when($f['posisi'] ?? '', fn ($q, $v) => $q->where('posisi', $v))
            ->orderBy('nama')
            ->get(['id', 'sobat_id', 'nama', 'kecamatan', 'desa', 'posisi']);

        $beban = $svc->beban($mitras->pluck('id')->all(), [$bulan]);
        $ringkas = ['semua' => 0, 'tersedia' => 0, 'mendekati' => 0, 'penuh' => 0];
        $baris = [];
        foreach ($mitras as $m) {
            $d = $beban[$m->id][$bulan] ?? ['total' => 0, 'rincian' => []];
            $st = $svc->status($d['total']);
            $kelompok = $st['sisa'] <= 0 ? 'penuh' : ($st['status'] === 'oranye' ? 'mendekati' : 'tersedia');
            $ringkas['semua']++;
            $ringkas[$kelompok]++;
            if ($kelompok === 'mendekati') {
                $ringkas['tersedia']++; // mendekati limit tetap masih bisa dipakai
            }
            $baris[] = [
                'mitra' => $m,
                'total' => $d['total'],
                'kegiatan' => collect($d['rincian'])->pluck('kegiatan')->unique()->values()->all(),
                'kelompok' => $kelompok,
            ] + $st;
        }

        $k = $f['ketersediaan'] ?? 'semua';
        $baris = array_values(array_filter($baris, fn ($b) => match ($k) {
            'tersedia' => $b['kelompok'] !== 'penuh',
            'mendekati' => $b['kelompok'] === 'mendekati',
            'penuh' => $b['kelompok'] === 'penuh',
            default => true,
        }));

        usort($baris, match ($f['urut'] ?? 'sisa') {
            'nama' => fn ($a, $b) => strcasecmp($a['mitra']->nama, $b['mitra']->nama),
            'honor' => fn ($a, $b) => [$b['total'], $a['mitra']->nama] <=> [$a['total'], $b['mitra']->nama],
            'wilayah' => fn ($a, $b) => [(string) $a['mitra']->kecamatan, (string) $a['mitra']->desa, $a['mitra']->nama] <=> [(string) $b['mitra']->kecamatan, (string) $b['mitra']->desa, $b['mitra']->nama],
            default => fn ($a, $b) => [$b['sisa'], $a['mitra']->nama] <=> [$a['sisa'], $b['mitra']->nama],
        });

        return ['baris' => $baris, 'ringkas' => $ringkas, 'limit' => $svc->limit()];
    }

    public static function opsiWilayah(string $kecamatan = ''): array
    {
        return [
            'kecamatan' => Mitra::query()->whereNotNull('kecamatan')->where('kecamatan', '!=', '')->distinct()->orderBy('kecamatan')->pluck('kecamatan'),
            'desa' => $kecamatan === '' ? collect() : Mitra::query()->where('kecamatan', $kecamatan)->whereNotNull('desa')->distinct()->orderBy('desa')->pluck('desa'),
            'posisi' => Mitra::query()->whereNotNull('posisi')->where('posisi', '!=', '')->distinct()->orderBy('posisi')->pluck('posisi'),
        ];
    }

    public function render()
    {
        $filter = $this->only(['cari', 'kecamatan', 'desa', 'posisi', 'ketersediaan', 'urut']);
        $hasil = self::data($this->bulan, $filter);
        $total = count($hasil['baris']);
        $jumlahHalaman = max(1, (int) ceil($total / $this->perHalaman));
        $halaman = min(max(1, (int) $this->getPage()), $jumlahHalaman);

        $barisHalaman = array_slice($hasil['baris'], ($halaman - 1) * $this->perHalaman, $this->perHalaman);

        return view('livewire.sibeban.monitoring', [
            'baris' => $barisHalaman,
            // Info pulsa (terpisah dari honor; tidak memengaruhi sisa limit)
            'pulsaMitra' => app(\App\Services\Sibeban\PulsaService::class)->perMitraBulan(array_map(fn ($b) => $b['mitra']->id, $barisHalaman), $this->bulan),
            'ringkas' => $hasil['ringkas'],
            'limit' => $hasil['limit'],
            'total' => $total,
            'halaman' => $halaman,
            'jumlahHalaman' => $jumlahHalaman,
            'opsi' => self::opsiWilayah($this->kecamatan),
            'labelBulan' => Periode::label($this->bulan, false),
            'filter' => $filter,
        ]);
    }
}
