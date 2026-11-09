<?php

use App\Support\WorkingDays;
use Illuminate\Support\Carbon;

if (! function_exists('poin')) {
    /** Format angka poin: 12.5 → "12,5", 10.0 → "10". */
    function poin(float|int|string|null $value): string
    {
        $s = number_format((float) $value, 2, ',', '.');

        return rtrim(rtrim($s, '0'), ',');
    }
}

if (! function_exists('bulan_tahun')) {
    function bulan_tahun(Carbon|string|null $date = null): string
    {
        return WorkingDays::monthName($date ? Carbon::parse($date) : now());
    }
}

if (! function_exists('tgl')) {
    /** Tanggal format Indonesia singkat: 9 Nov 2026. */
    function tgl(Carbon|string|null $date, string $format = 'j M Y'): string
    {
        return $date ? Carbon::parse($date)->locale('id')->translatedFormat($format) : '-';
    }
}
