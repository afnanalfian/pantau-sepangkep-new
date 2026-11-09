<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Carbon\CarbonPeriod;

class WorkingDays
{
    /** Jumlah hari kerja (Senin–Jumat) di antara dua tanggal, inklusif. */
    public static function between(Carbon $start, Carbon $end): int
    {
        if ($end->lt($start)) {
            return 0;
        }
        $n = 0;
        foreach (CarbonPeriod::create($start->copy()->startOfDay(), $end->copy()->startOfDay()) as $d) {
            if (! $d->isWeekend()) {
                $n++;
            }
        }

        return $n;
    }

    public static function inMonth(Carbon $month): int
    {
        return self::between($month->copy()->startOfMonth(), $month->copy()->endOfMonth());
    }

    /** Tambah N jam kerja (melewati Sabtu–Minggu). */
    public static function addWorkingHours(Carbon $from, int $hours): Carbon
    {
        $t = $from->copy();
        while ($hours > 0) {
            $t->addHour();
            if (! $t->isWeekend()) {
                $hours--;
            }
        }

        return $t;
    }

    public static function monthName(Carbon $date): string
    {
        $bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        return $bulan[(int) $date->format('n')].' '.$date->format('Y');
    }
}
