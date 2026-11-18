<?php

namespace App\Support\Sibeban;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class Periode
{
    public const NAMA_BULAN = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    /** Semua bulan 'YYYY-MM' dari tanggal $mulai s.d. $selesai (inklusif). */
    public static function bulanAntara(CarbonInterface|string|null $mulai, CarbonInterface|string|null $selesai): array
    {
        if (! $mulai || ! $selesai) {
            return [];
        }
        $a = Carbon::parse($mulai)->startOfMonth();
        $b = Carbon::parse($selesai)->startOfMonth();
        if ($b->lt($a)) {
            return [];
        }
        $hasil = [];
        while ($a->lte($b)) {
            $hasil[] = $a->format('Y-m');
            $a->addMonth();
        }

        return $hasil;
    }

    /** 12 bulan dalam satu tahun. */
    public static function bulanTahun(int $tahun): array
    {
        return array_map(fn ($m) => sprintf('%04d-%02d', $tahun, $m), range(1, 12));
    }

    /** '2026-03' -> 'Mar 2026' (pendek) atau 'Maret 2026'. */
    public static function label(string $bulan, bool $pendek = true): string
    {
        [$y, $m] = array_map('intval', explode('-', $bulan));
        $nama = self::NAMA_BULAN[$m] ?? $bulan;

        return ($pendek ? substr($nama, 0, 3) : $nama).' '.$y;
    }
}
