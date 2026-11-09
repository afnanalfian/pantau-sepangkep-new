<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use App\Notifications\PantauNotification;
use App\Services\TaskService;
use App\Support\Settings;
use App\Support\WorkingDays;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Dijalankan scheduler tiap 15 menit:
 *  - Pengingat H-1 deadline
 *  - Notifikasi tugas terlambat (poin dibekukan)
 *  - SLA review 2x24 jam kerja → eskalasi ke pimpinan / auto-approve
 */
class RunReminders extends Command
{
    protected $signature = 'pantau:reminders';

    protected $description = 'Pengingat deadline, tugas terlambat, dan eskalasi SLA verifikasi';

    public function handle(TaskService $tasks): int
    {
        $this->deadlines();
        $this->overdue();
        $this->sla($tasks);

        return self::SUCCESS;
    }

    private function deadlines(): void
    {
        $hours = (int) Settings::get('reminder_hours', 24);
        $limit = now()->addHours($hours)->toDateString();

        Task::with('assignee')->whereIn('status', ['todo', 'in_progress', 'revisi'])
            ->where('reminder_sent', false)->whereNotNull('due_date')
            ->whereRaw('COALESCE(extension_until, due_date) <= ?', [$limit])
            ->whereRaw('COALESCE(extension_until, due_date) >= ?', [now()->toDateString()])
            ->each(function (Task $t) {
                $t->assignee?->notify(PantauNotification::forTask('deadline', $t,
                    '⏰ Deadline '.($t->effectiveDueDate()->isToday() ? 'hari ini' : 'besok').': '.$t->title,
                    $t->code.' · jangan lupa unggah output & ajukan selesai.'));
                $t->forceFill(['reminder_sent' => true])->saveQuietly();
                $this->line('Reminder: '.$t->code);
            });
    }

    private function overdue(): void
    {
        Task::with(['assignee', 'creator'])->overdue()->where('overdue_notified', false)
            ->each(function (Task $t) {
                $t->assignee?->notify(PantauNotification::forTask('overdue', $t, 'Tugas melewati deadline – poin dibekukan',
                    $t->title.' · ajukan perpanjangan waktu atau segera selesaikan.'));
                if ($t->creator && $t->creator_id !== $t->assignee_id) {
                    $t->creator->notify(PantauNotification::forTask('overdue', $t, 'Tugas '.$t->assignee?->display_name.' terlambat', $t->title));
                }
                $t->forceFill(['overdue_notified' => true])->saveQuietly();
                $this->line('Overdue: '.$t->code);
            });
    }

    private function sla(TaskService $svc): void
    {
        $hours = (int) Settings::get('sla_hours', 48);
        $action = Settings::get('sla_action', 'escalate');

        Task::with(['verifier', 'assignee'])->whereIn('status', ['review', 'pengajuan'])
            ->where('sla_escalated', false)->whereNotNull('submitted_at')
            ->each(function (Task $t) use ($hours, $action, $svc) {
                if (WorkingDays::addWorkingHours($t->submitted_at, $hours)->isFuture()) {
                    return;
                }
                $verifier = $t->verifier;
                $pimpinan = User::primaryPimpinan($t->assignee_id);

                // Catat pelanggaran SLA (memengaruhi poin manajerial ketua tim)
                TaskActivity::create([
                    'task_id' => $t->id, 'action' => 'sla_breach',
                    'description' => 'batas SLA '.$hours.' jam kerja terlewati oleh '.($verifier?->display_name ?? '-'),
                    'meta' => ['verifier_id' => $verifier?->id],
                ]);

                $verifierOoo = $verifier?->isOutOfOffice();
                if ($action === 'auto_approve' && ! $verifierOoo && $t->status === 'review') {
                    // Persetujuan otomatis (dibekukan bila verifikator cuti/DL)
                    $t->forceFill(['sla_escalated' => true])->saveQuietly();
                    $t->completed_at = now();
                    $t->progress = 100;
                    $svc->transition($t, 'selesai', null, 'disetujui otomatis oleh sistem (SLA terlewati)');
                    $t->assignee?->notify(PantauNotification::forTask('approved', $t, 'Tugas disetujui otomatis (SLA)', $t->title));
                } elseif ($pimpinan && $verifier?->id !== $pimpinan->id) {
                    $t->forceFill(['verifier_id' => $pimpinan->id, 'sla_escalated' => true])->saveQuietly();
                    $svc->log($t, null, 'escalated', 'dieskalasi ke '.$pimpinan->display_name.' karena SLA verifikasi terlewati');
                    $pimpinan->notify(PantauNotification::forTask('escalation', $t,
                        'Eskalasi SLA: '.$t->assignee?->display_name.' menunggu verifikasi', $t->title.' · verifikator awal: '.$verifier?->display_name));
                    $verifier?->notify(PantauNotification::forTask('escalation', $t, 'SLA verifikasi terlewati – dialihkan ke pimpinan', $t->title));
                } else {
                    $t->forceFill(['sla_escalated' => true])->saveQuietly();
                    $verifier?->notify(PantauNotification::forTask('escalation', $t, 'Pengingat: tugas menunggu verifikasi Anda', $t->title));
                }
                $this->line('SLA: '.$t->code);
            });
    }
}
