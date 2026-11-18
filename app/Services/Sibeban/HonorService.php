<?php

namespace App\Services\Sibeban;

use App\Models\Sibeban\HonorBerjalan;
use App\Models\Sibeban\HonorBulanan;
use App\Models\Sibeban\Pengaturan;

/**
 * Inti SIBEBAN: total honor mitra per BULAN dibandingkan limit honor bulanan.
 *
 *   total bulan B = honor semua kegiatan SIBEBAN di bulan B
 *                 + honor di luar SIBEBAN (hasil impor Matriks Honor oleh admin, bila ada)
 *   sisa          = limit bulanan − total
 *   mitra "masih tersedia" bila sisa > 0
 *
 * Status warna:
 *   kosong : belum ada honor bulan itu
 *   hijau  : ≤ ambang (default 75 %) dari limit
 *   oranye : di atas ambang s.d. 100 %
 *   merah  : melebihi limit
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

    private ?int $limitCache = null;

    private ?float $ambangCache = null;

    /** Limit honor per mitra per bulan (diatur admin). */
    public function limit(): int
    {
        return $this->limitCache ??= (int) (Pengaturan::nilai('limit_bulanan') ?? Pengaturan::nilai('limit_default', '3910000'));
    }

    public function ambangOranye(): float
    {
        return $this->ambangCache ??= (float) Pengaturan::nilai('ambang_oranye', '75');
    }

    /** @return array{persen: float, status: string, sisa: int} */
    public function status(int $total): array
    {
        $limit = max(1, $this->limit());
        $persen = $total / $limit * 100;
        $status = match (true) {
            $total <= 0 => 'kosong',
            $persen > 100 => 'merah',
            $persen > $this->ambangOranye() => 'oranye',
            default => 'hijau',
        };

        return ['persen' => round($persen, 2), 'status' => $status, 'sisa' => $limit - $total];
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

    /** Kelas chip status. */
    public static function warna(string $status): string
    {
        return match ($status) {
            'merah' => 'bg-rose-50 text-rose-700 ring-rose-200',
            'oranye' => 'bg-bps-orange-50 text-bps-orange-700 ring-bps-orange-100',
            'hijau' => 'bg-bps-green-50 text-bps-green-700 ring-bps-green-100',
            default => 'bg-navy-50 text-navy-500 ring-navy-100',
        };
    }

    public static function titik(string $status): string
    {
        return match ($status) {
            'merah' => 'bg-rose-500',
            'oranye' => 'bg-bps-orange',
            'hijau' => 'bg-bps-green',
            default => 'bg-navy-100',
        };
    }

    /**
     * Honor mitra per bulan beserta rinciannya.
     *
     * @param  array<int>|null  $mitraIds  null = semua mitra
     * @param  array<string>  $bulans  daftar 'YYYY-MM'
     * @param  int|null  $kecualiKegiatanId  abaikan honor kegiatan ini (untuk menghitung "honor dari kegiatan lain")
     * @return array<int, array<string, array{total:int, rincian: array<int, array{kegiatan_id:?int, kegiatan:string, jabatan:?string, jumlah:int}>}>>
     */
    public function beban(?array $mitraIds, array $bulans, ?int $kecualiKegiatanId = null): array
    {
        if (empty($bulans) || (is_array($mitraIds) && empty($mitraIds))) {
            return [];
        }
        $hasil = [];

        // Honor di luar SIBEBAN (impor Matriks Honor)
        $qb = HonorBerjalan::query()->whereIn('bulan', $bulans)->where('jumlah', '>', 0)
            ->when($mitraIds !== null, fn ($q) => $q->whereIn('mitra_id', $mitraIds));
        foreach ($qb->toBase()->cursor() as $r) {
            $sel = &$hasil[$r->mitra_id][$r->bulan];
            $sel ??= ['total' => 0, 'rincian' => []];
            $sel['total'] += (int) $r->jumlah;
            $sel['rincian'][] = ['kegiatan_id' => null, 'kegiatan' => 'Honor di luar SIBEBAN (impor matriks)', 'jabatan' => null, 'jumlah' => (int) $r->jumlah];
            unset($sel);
        }

        // Honor kegiatan SIBEBAN
        $q = HonorBulanan::query()
            ->join('sibeban_penugasans', 'sibeban_penugasans.id', '=', 'sibeban_honor_bulanans.penugasan_id')
            ->join('sibeban_kegiatans', 'sibeban_kegiatans.id', '=', 'sibeban_penugasans.kegiatan_id')
            ->whereIn('sibeban_honor_bulanans.bulan', $bulans)
            ->where('sibeban_honor_bulanans.jumlah', '>', 0)
            ->when($mitraIds !== null, fn ($q) => $q->whereIn('sibeban_penugasans.mitra_id', $mitraIds))
            ->when($kecualiKegiatanId, fn ($q) => $q->where('sibeban_penugasans.kegiatan_id', '!=', $kecualiKegiatanId))
            ->select([
                'sibeban_penugasans.mitra_id', 'sibeban_penugasans.jabatan',
                'sibeban_kegiatans.id as kegiatan_id', 'sibeban_kegiatans.nama as kegiatan',
                'sibeban_honor_bulanans.bulan', 'sibeban_honor_bulanans.jumlah',
            ])
            ->orderBy('sibeban_kegiatans.tanggal_mulai');

        foreach ($q->toBase()->cursor() as $r) {
            $sel = &$hasil[$r->mitra_id][$r->bulan];
            $sel ??= ['total' => 0, 'rincian' => []];
            $sel['total'] += (int) $r->jumlah;
            $sel['rincian'][] = ['kegiatan_id' => (int) $r->kegiatan_id, 'kegiatan' => $r->kegiatan, 'jabatan' => $r->jabatan, 'jumlah' => (int) $r->jumlah];
            unset($sel);
        }

        return $hasil;
    }

    /**
     * Gabungkan honor dari kegiatan lain dengan honor kegiatan yang sedang diatur.
     *
     * @param  array<string, array>  $bebanLain  hasil beban(..., kecuali)[mitra_id]
     * @param  array<string, int>  $honorIni  [bulan => jumlah]
     */
    public function hitung(array $bebanLain, array $honorIni, array $bulans): array
    {
        $perBulan = [];
        $maks = 0.0;
        $sisaMin = PHP_INT_MAX;
        foreach ($bulans as $b) {
            $lain = (int) ($bebanLain[$b]['total'] ?? 0);
            $ini = (int) ($honorIni[$b] ?? 0);
            $st = $this->status($lain + $ini);
            $perBulan[$b] = ['lain' => $lain, 'ini' => $ini, 'total' => $lain + $ini] + $st;
            $maks = max($maks, $st['persen']);
            $sisaMin = min($sisaMin, $st['sisa']);
        }

        return [
            'bulan' => $perBulan,
            'status' => self::terburuk(array_column($perBulan, 'status')),
            'persen_maks' => $maks,
            'sisa_min' => $sisaMin === PHP_INT_MAX ? $this->limit() : $sisaMin,
        ];
    }
}
