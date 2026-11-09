<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\PantauNotification;
use App\Services\WorkloadService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Early warning untuk pimpinan & ketua tim:
 *  - pegawai dorman (tanpa aktivitas > 4 hari kerja) / tanpa tugas aktif
 *  - Jumat sore: progres mingguan di bawah standar (Yellow Alert)
 */
class EarlyWarning extends Command
{
    protected $signature = 'pantau:early-warning {--weekly : Mode gerbang kecepatan mingguan (Jumat 17.00)}';

    protected $description = 'Peringatan dini pegawai underload / pasif';

    public function handle(WorkloadService $workload): int
    {
        $users = User::pegawai()->with('teams.leaders')->get();
        $summary = $workload->summarize($users);
        $pimpinan = User::pegawai()->where('is_pimpinan', true)->get();

        if ($this->option('weekly')) {
            $weekStart = now()->startOfWeek();
            $low = $users->filter(function ($u) use ($weekStart) {
                $pts = \App\Models\Task::where('assignee_id', $u->id)->where('status', 'selesai')
                    ->where('completed_at', '>=', $weekStart)->sum('points');

                return $pts < 20 && ! $u->is_pimpinan;
            });
            if ($low->isEmpty()) {
                return self::SUCCESS;
            }
            $this->notifyGrouped($low, $pimpinan, '🟡 Yellow Alert: progres mingguan di bawah 20 poin',
                fn ($names) => $names.' belum mencapai 20 poin terverifikasi minggu ini.');

            return self::SUCCESS;
        }

        $idle = $summary->filter(fn ($s) => ! $s['user']->is_pimpinan && ($s['activity'] === 'dorman' || $s['active_count'] === 0));
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
