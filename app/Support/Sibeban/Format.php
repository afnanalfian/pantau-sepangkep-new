<?php

namespace App\Support\Sibeban;

class Format
{
    public static function rp(int|float|null $n): string
    {
        return 'Rp '.number_format((float) ($n ?? 0), 0, ',', '.');
    }

    public static function angka(int|float|null $n): string
    {
        return number_format((float) ($n ?? 0), 0, ',', '.');
    }

    /** 3.910.000 -> '3,91 jt' untuk sel matriks yang sempit. */
    public static function jt(int|float|null $n): string
    {
        $n = (float) ($n ?? 0);
        if ($n <= 0) {
            return '–';
        }
        if ($n < 1_000_000) {
            return number_format($n / 1000, 0, ',', '.').' rb';
        }

        return number_format($n / 1_000_000, 2, ',', '.').' jt';
    }

    public static function persen(?float $p): string
    {
        return $p === null ? '–' : number_format($p, 1, ',', '.').'%';
    }
}
