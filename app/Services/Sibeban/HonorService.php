<?php

namespace App\Services\Sibeban;

use App\Models\Sibeban\HonorBerjalan;
use App\Models\Sibeban\HonorBulanan;
use App\Models\Sibeban\Kegiatan;
use App\Models\Sibeban\LimitHonor;
use App\Models\Sibeban\Mitra;
use App\Models\Sibeban\Pengaturan;

/**
 * Inti kalkulasi: total honor mitra per bulan dibandingkan limitnya.
 *
 * Aturan status (dapat diubah di menu Pengaturan):
 *   - hijau  : persentase <= ambang (default 75 %)  → masih jauh dari limit
 *   - oranye : ambang < persentase <= 100 %          → mendekati limit
 *   - merah  : persentase > 100 %                    → melebihi limit
 *   - kosong : belum ada honor di bulan itu
 *
 * Limit per mitra per bulan = limit terbesar di antara semua penugasannya
 * pada bulan tersebut (mengikuti kolom "Limit per Nama Bulan ini" di matriks).
 */
class HonorService
{
    public const URUTAN = ['kosong' => 0, 'hijau' => 1, 'oranye' => 2, 'merah' => 3];

    public const LABEL = [
        'kosong' => 'Belum ada honor',
        'hijau' => 'Aman',
        'oranye' => 'Mendekati limit',
        'merah' => 'Melebihi limit',
    ];

    private ?array $limitCache = null;

    private ?float $ambangCache = null;

    public function ambangOranye(): float
    {
        return $this->ambangCache ??= (float) Pengaturan::nilai('ambang_oranye', '75');
    }

    public function limitDefault(): int
    {
        return (int) Pengaturan::nilai('limit_default', '3910000');
    }

    public function limitFor(?string $jenis, ?string $jabatan): int
    {
        if ($this->limitCache === null) {
            $this->limitCache = LimitHonor::all()
                ->mapWithKeys(fn ($l) => [strtolower($l->jenis).'|'.$l->jabatan => (int) $l->limit])
                ->all();
        }

        return $this->limitCache[strtolower((string) $jenis).'|'.$jabatan] ?? $this->limitDefault();
    }

    /** @return array{persen: float, status: string} */
    public function status(int $total, int $limit): array
    {
        if ($total <= 0) {
            return ['persen' => 0.0, 'status' => 'kosong'];
        }
        $limit = $limit > 0 ? $limit : $this->limitDefault();
        $persen = $total / $limit * 100;

        $status = match (true) {
            $persen > 100 => 'merah',
            $persen > $this->ambangOranye() => 'oranye',
            default => 'hijau',
        };

        return ['persen' => round($persen, 2), 'status' => $status];
    }

    public static function terburuk(iterable $statuses): string
    {
        $hasil = 'kosong';
        foreach ($statuses as $s) {
            if ((self::URUTAN[$s] ?? 0) > self::URUTAN[$hasil]) {
                $hasil = $s;
            }
        }

        return $hasil;
    }

    /** Kelas Tailwind untuk sel/badge status. */
    public static function warna(string $status): string
    {
        return match ($status) {
            'merah' => 'bg-red-100 text-red-800 ring-red-300',
            'oranye' => 'bg-orange-100 text-orange-800 ring-orange-300',
            'hijau' => 'bg-emerald-100 text-emerald-800 ring-emerald-300',
            default => 'bg-slate-50 text-slate-400 ring-slate-200',
        };
    }

    public static function titik(string $status): string
    {
        return match ($status) {
            'merah' => 'bg-red-500',
            'oranye' => 'bg-orange-500',
            'hijau' => 'bg-emerald-500',
            default => 'bg-slate-300',
        };
    }

    /**
     * Beban honor mitra per bulan.
     *
     * Aturan (disepakati): rekap Excel "honor berjalan" adalah acuan utama.
     *   total = honor berjalan (rekap terbaru)
     *         + honor kegiatan di aplikasi yang BELUM tercakup rekap itu, yaitu:
     *             - kegiatan yang unggahannya menghasilkan rekap tersebut, atau
     *             - penugasan yang dibuat SETELAH rekap diunggah.
     *   Penugasan aplikasi yang lebih lama dari rekap dianggap sudah termasuk di dalamnya
     *   (tidak dihitung lagi agar tidak dobel).
     *   Bila belum ada rekap untuk mitra-bulan itu, semua honor kegiatan aplikasi dijumlahkan.
     *
     * @param  array<int>|null  $mitraIds  null = semua mitra
     * @param  array<string>  $bulans  daftar 'YYYY-MM'
     * @return array<int, array<string, array{total:int, berjalan:int, limit:int, rincian: array<int, array>}>>
     */
    public function beban(?array $mitraIds, array $bulans, ?int $kecualiKegiatanId = null): array
    {
        if (empty($bulans) || (is_array($mitraIds) && empty($mitraIds))) {
            return [];
        }

        $hasil = [];
        $rekap = [];

        // 1) Honor berjalan dari rekap Excel
        $qb = HonorBerjalan::query()->whereIn('bulan', $bulans)
            ->when($mitraIds !== null, fn ($q) => $q->whereIn('mitra_id', $mitraIds));
        foreach ($qb->toBase()->cursor() as $r) {
            $rekap[$r->mitra_id][$r->bulan] = ['waktu' => (string) $r->updated_at, 'kegiatan_id' => $r->kegiatan_id ? (int) $r->kegiatan_id : null];
            $hasil[$r->mitra_id][$r->bulan] = [
                'total' => (int) $r->jumlah,
                'berjalan' => (int) $r->jumlah,
                'limit' => 0,
                'rincian' => [[
                    'penugasan_id' => null,
                    'kegiatan_id' => null,
                    'kegiatan' => 'Honor berjalan (rekap Excel)',
                    'jabatan' => '',
                    'jumlah' => (int) $r->jumlah,
                    'keterangan' => trim(($r->sumber ?? '').' · per '.substr((string) $r->updated_at, 0, 16), ' ·'),
                ]],
            ];
        }

        // 2) Honor kegiatan yang dikelola di aplikasi
        $q = HonorBulanan::query()
            ->join('sibeban_penugasans', 'sibeban_penugasans.id', '=', 'sibeban_honor_bulanans.penugasan_id')
            ->join('sibeban_kegiatans', 'sibeban_kegiatans.id', '=', 'sibeban_penugasans.kegiatan_id')
            ->whereIn('sibeban_honor_bulanans.bulan', $bulans)
            ->select([
                'sibeban_penugasans.mitra_id', 'sibeban_penugasans.id as penugasan_id', 'sibeban_penugasans.jabatan',
                'sibeban_penugasans.created_at as dibuat',
                'sibeban_kegiatans.id as kegiatan_id', 'sibeban_kegiatans.nama as kegiatan', 'sibeban_kegiatans.jenis',
                'sibeban_honor_bulanans.bulan', 'sibeban_honor_bulanans.jumlah',
            ])
            ->orderBy('sibeban_kegiatans.tanggal_mulai');

        if ($mitraIds !== null) {
            $q->whereIn('sibeban_penugasans.mitra_id', $mitraIds);
        }
        if ($kecualiKegiatanId) {
            $q->where('sibeban_penugasans.kegiatan_id', '!=', $kecualiKegiatanId);
        }

        foreach ($q->toBase()->cursor() as $r) {
            $rk = $rekap[$r->mitra_id][$r->bulan] ?? null;
            if ($rk && $rk['kegiatan_id'] !== (int) $r->kegiatan_id && (string) $r->dibuat < $rk['waktu']) {
                continue; // sudah tercakup dalam rekap Excel
            }
            $sel = &$hasil[$r->mitra_id][$r->bulan];
            $sel ??= ['total' => 0, 'berjalan' => 0, 'limit' => 0, 'rincian' => []];
            $sel['total'] += (int) $r->jumlah;
            $sel['limit'] = max($sel['limit'], $this->limitFor($r->jenis, $r->jabatan));
            $sel['rincian'][] = [
                'penugasan_id' => (int) $r->penugasan_id,
                'kegiatan_id' => (int) $r->kegiatan_id,
                'kegiatan' => $r->kegiatan,
                'jabatan' => $r->jabatan,
                'jumlah' => (int) $r->jumlah,
                'keterangan' => null,
            ];
            unset($sel);
        }

        return $hasil;
    }

    /**
     * Gabungkan beban lain dengan honor baru dari kegiatan yang sedang diatur.
     *
     * @param  array<string, array>  $bebanMitra  hasil beban()[mitra_id]
     * @param  array<string, int>  $honorBaru  [bulan => jumlah]
     */
    public function hitung(array $bebanMitra, array $honorBaru, int $limitBaru, array $bulans): array
    {
        $perBulan = [];
        $maks = 0.0;
        foreach ($bulans as $b) {
            $lain = (int) ($bebanMitra[$b]['total'] ?? 0);
            $baru = (int) ($honorBaru[$b] ?? 0);
            $limit = max((int) ($bebanMitra[$b]['limit'] ?? 0), $limitBaru);
            $total = $lain + $baru;
            $st = $this->status($total, $limit);
            $perBulan[$b] = [
                'lain' => $lain,
                'baru' => $baru,
                'total' => $total,
                'limit' => $limit,
                'sisa' => $limit - $total,
                'persen' => $st['persen'],
                'status' => $st['status'],
            ];
            $maks = max($maks, $st['persen']);
        }

        return [
            'bulan' => $perBulan,
            'status' => self::terburuk(array_column($perBulan, 'status')),
            'persen_maks' => $maks,
        ];
    }

    /**
     * Ringkas rincian beban satu mitra per kegiatan lain
     * (dipakai panel "Atur" untuk melepas mitra dari kegiatan sebelumnya).
     *
     * @return array<int, array{penugasan_id:int, kegiatan_id:int, kegiatan:string, jabatan:string, honor: array<string,int>, total:int}>
     */
    public static function kegiatanLain(array $bebanMitra): array
    {
        $hasil = [];
        foreach ($bebanMitra as $bulan => $sel) {
            foreach ($sel['rincian'] as $r) {
                $h = &$hasil[$r['penugasan_id'] ?? 'berjalan'];
                $h ??= [
                    'penugasan_id' => $r['penugasan_id'],
                    'kegiatan_id' => $r['kegiatan_id'],
                    'kegiatan' => $r['kegiatan'],
                    'jabatan' => $r['jabatan'],
                    'keterangan' => $r['keterangan'] ?? null,
                    'honor' => [],
                    'total' => 0,
                ];
                $h['honor'][$bulan] = ($h['honor'][$bulan] ?? 0) + $r['jumlah'];
                $h['total'] += $r['jumlah'];
                unset($h);
            }
        }
        // rekap honor berjalan selalu di urutan pertama
        usort($hasil, fn ($a, $b) => [$a['penugasan_id'] !== null, -$a['total']] <=> [$b['penugasan_id'] !== null, -$b['total']]);

        return $hasil;
    }

    /**
     * Rekomendasi mitra untuk kegiatan: diurutkan dari yang paling longgar limitnya
     * bila ditambahkan dengan honor default jabatan tersebut.
     */
    public function rekomendasi(Kegiatan $kegiatan, string $jabatan, string $cari = '', int $jumlah = 20, bool $sembunyikanMerah = true): array
    {
        $bulans = $kegiatan->bulans();
        $sudah = $kegiatan->penugasans()->pluck('mitra_id')->all();

        $mitras = Mitra::query()
            ->whereNotIn('id', $sudah)
            ->when($cari !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('nama', 'like', "%{$cari}%")
                ->orWhere('sobat_id', 'like', "%{$cari}%")))
            ->get(['id', 'sobat_id', 'nama', 'posisi']);

        $beban = $this->beban($mitras->pluck('id')->all(), $bulans);
        $honor = array_fill_keys($bulans, $kegiatan->honorDefault($jabatan));
        $limit = $this->limitFor($kegiatan->jenis, $jabatan);

        $hasil = [];
        foreach ($mitras as $m) {
            $calc = $this->hitung($beban[$m->id] ?? [], $honor, $limit, $bulans);
            if ($sembunyikanMerah && $calc['status'] === 'merah') {
                continue;
            }
            $hasil[] = [
                'mitra' => $m->only(['id', 'sobat_id', 'nama', 'posisi']),
                'jumlah_kegiatan_lain' => collect($beban[$m->id] ?? [])
                    ->flatMap(fn ($s) => array_column($s['rincian'], 'kegiatan_id'))->filter()->unique()->count(),
                'calc' => $calc,
            ];
        }

        usort($hasil, fn ($a, $b) => [$a['calc']['persen_maks'], $a['jumlah_kegiatan_lain']]
            <=> [$b['calc']['persen_maks'], $b['jumlah_kegiatan_lain']]);

        return array_slice($hasil, 0, $jumlah);
    }
}
