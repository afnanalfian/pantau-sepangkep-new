<?php

namespace App\Support\Sibeban;

class Format
{
    /** Rupiah ditulis lengkap: Rp 3.910.000 */
    public static function rp(int|float|null $n): string
    {
        $n = (float) ($n ?? 0);

        return ($n < 0 ? '−Rp ' : 'Rp ').number_format(abs($n), 0, ',', '.');
    }

    public static function angka(int|float|null $n): string
    {
        return number_format((float) ($n ?? 0), 0, ',', '.');
    }

    public static function persen(?float $p): string
    {
        return $p === null ? '–' : number_format($p, 1, ',', '.').'%';
    }
}
