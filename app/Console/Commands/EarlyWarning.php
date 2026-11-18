<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\PantauNotification;
use App\Services\KinerjaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Early warning untuk pimpinan & ketua tim:
 *  - pegawai dorman (tanpa aktivitas > 4 hari kerja) / tanpa tugas aktif
 *  - Jumat sore: pegawai yang punya tugas lewat deadline bulan ini (berisiko potongan TK)
 */
class EarlyWarning extends Command
{
    protected $signature = 'pantau:early-warning {--weekly : Peringatan mingguan tugas lewat deadline (Jumat 17.00)}';

    protected $description = 'Peringatan dini pegawai underload / pasif';

    public function handle(KinerjaService $kinerja): int
    {
        $users = User::pegawai()->with('teams.leaders')->get();
        $summary = $kinerja->matriks($users);
        $pimpinan = User::pegawai()->where('is_pimpinan', true)->get();

        if ($this->option('weekly')) {
            $risk = $summary->filter(fn ($s) => ! $s['user']->is_pimpinan && $s['tugas_gagal'] > 0);
            if ($risk->isEmpty()) {
                return self::SUCCESS;
            }
            $this->notifyGrouped($risk->pluck('user'), $pimpinan, '🟡 Peringatan: ada tugas lewat deadline bulan ini',
                fn ($names) => $names.' punya tugas yang melewati deadline dan belum selesai. Bila tidak selesai sampai akhir bulan, penyelesaian tugasnya turun.');
            foreach ($risk as $r) {
                $r['user']->notify(new PantauNotification('overdue', 'Ada '.$r['tugas_gagal'].' tugas lewat deadline bulan ini',
                    'Selesaikan sebelum akhir bulan agar penyelesaian tugas Anda tidak berkurang.', route('tasks.index')));
            }

            return self::SUCCESS;
        }

        $idle = $summary->filter(fn ($s) => ! $s['user']->is_pimpinan && ($s['activity'] === 'dorman' || $s['aktif'] === 0));
        if ($idle->isEmpty()) {
            return self::SUCCESS;
        }
        $this->notifyGrouped($idle->pluck('user'), $pimpinan, 'Peringatan dini: pegawai tanpa tugas aktif / pasif',
            fn ($names) => $names.' perlu diberi tugas. Buka menu Beban Kerja untuk "Beri Tugas Langsung".');

        return self::SUCCESS;
    }

    private function notifyGrouped($users, $pimpinan, string $title, callable $body): void
    {
        $url = route('workload');
        // Ke pimpinan: ringkasan semua
        if ($pimpinan->isNotEmpty()) {
            Notification::send($pimpinan, new PantauNotification('escalation', $title, $body($users->take(8)->pluck('display_name')->join(', ').($users->count() > 8 ? ' dan '.($users->count() - 8).' lainnya' : '')), $url));
        }
        // Ke tiap ketua tim: anggota timnya saja
        $byLeader = [];
        foreach ($users as $u) {
            foreach ($u->teams as $team) {
                foreach ($team->leaders as $leader) {
                    if ($leader->id !== $u->id && ! $leader->is_pimpinan) {
                        $byLeader[$leader->id]['leader'] = $leader;
                        $byLeader[$leader->id]['names'][$u->id] = $u->display_name;
                    }
                }
            }
        }
        foreach ($byLeader as $row) {
            $row['leader']->notify(new PantauNotification('escalation', $title, $body(implode(', ', $row['names'])), $url));
        }
        $this->info('Peringatan dikirim untuk '.$users->count().' pegawai.');
    }
}
