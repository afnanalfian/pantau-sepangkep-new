<?php

use Illuminate\Support\Facades\Schedule;

// Pengingat deadline, tugas terlambat & eskalasi SLA verifikasi
Schedule::command('pantau:reminders')->everyFifteenMinutes()->withoutOverlapping();

// Early warning pegawai pasif/tanpa tugas: hari kerja pukul 09.00
Schedule::command('pantau:early-warning')->weekdays()->at('09:00');

// Gerbang kecepatan mingguan (Yellow Alert): Jumat 17.00
Schedule::command('pantau:early-warning --weekly')->fridays()->at('17:00');

// Bersihkan notifikasi lama (> 120 hari) & job gagal lama
Schedule::call(fn () => \Illuminate\Support\Facades\DB::table('notifications')
    ->whereNotNull('read_at')->where('created_at', '<', now()->subDays(120))->delete())->dailyAt('02:00');
