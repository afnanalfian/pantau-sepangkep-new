<?php

namespace App\Services;

use App\Models\ActivityCatalog;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Notifications\PantauNotification;
use App\Support\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Seluruh aturan alur kerja tugas:
 * penugasan bertingkat, pengajuan mandiri, verifikasi berjenjang, revisi,
 * pengalihan approval saat cuti/DL, perpanjangan waktu, dan audit trail.
 */
class TaskService
{
    /* ============================================================ PEMBUATAN */

    /**
     * Membuat penugasan (pimpinan / ketua tim / admin) untuk satu atau beberapa pegawai.
     * Setiap penerima mendapat tugasnya sendiri agar poin tercatat per orang.
     *
     * @return Collection<int, Task>
     */
    public function assign(array $data, array $assigneeIds, User $actor, ?Task $parent = null): Collection
    {
        if (! $actor->canAssign() && ! $parent) {
            throw ValidationException::withMessages(['assignees' => 'Anda tidak memiliki wewenang memberi tugas.']);
        }
        $assigneeIds = array_values(array_unique(array_filter(array_map('intval', $assigneeIds))));
        if (! $assigneeIds) {
            throw ValidationException::withMessages(['assignees' => 'Pilih minimal satu pegawai.']);
        }

        $catalog = ! empty($data['catalog_id']) ? ActivityCatalog::find($data['catalog_id']) : null;
        $quantity = (float) ($data['quantity'] ?? 1);
        $complexity = $data['complexity'] ?? ($catalog->complexity ?? 'rendah');
        $points = isset($data['points']) && $data['points'] !== '' && $data['points'] !== null
            ? (float) $data['points']
            : Task::computePoints($catalog, $quantity, $complexity);

        $type = $actor->is_pimpinan ? 'disposisi' : 'penugasan';

        return DB::transaction(function () use ($data, $assigneeIds, $actor, $parent, $catalog, $quantity, $complexity, $points, $type) {
            $tasks = collect();
            foreach ($assigneeIds as $assigneeId) {
                $assignee = User::findOrFail($assigneeId);
                $teamId = $data['team_id'] ?? $parent?->team_id ?? $this->guessTeam($actor, $assignee);

                $task = Task::create([
                    'code' => Task::generateCode(),
                    'parent_id' => $parent?->id,
                    'team_id' => $teamId,
                    'catalog_id' => $catalog?->id,
                    'creator_id' => $actor->id,
                    'assignee_id' => $assignee->id,
                    'title' => $data['title'],
                    'description' => $data['description'] ?? null,
                    'type' => $type,
                    'status' => 'todo',
                    'priority' => $data['priority'] ?? 'sedang',
                    'complexity' => $complexity,
                    'quantity' => $quantity,
                    'points' => $points,
                    'start_date' => $data['start_date'] ?? now()->toDateString(),
                    'due_date' => $data['due_date'] ?? null,
                    'position' => 0,
                ]);
                $task->verifier_id = $this->resolveVerifier($task);
                $task->save();

                $this->log($task, $actor, 'created', $parent
                    ? "meneruskan tugas dari {$parent->code} kepada {$assignee->display_name}"
                    : "membuat tugas untuk {$assignee->display_name}");

                if ($assignee->id !== $actor->id) {
                    $assignee->notify(PantauNotification::forTask(
                        'assigned', $task,
                        ($type === 'disposisi' ? 'Disposisi baru dari ' : 'Tugas baru dari ').$actor->display_name,
                        $task->title.($task->due_date ? ' · Deadline '.$task->due_date->translatedFormat('d M Y') : ''),
                        $actor
                    ));
                }

                // Tembusan ke ketua tim asal (disposisi pimpinan / penugasan lintas tim)
                $this->sendCarbonCopy($task, $assignee, $actor);

                $tasks->push($task);
            }

            if ($parent && $parent->status === 'todo') {
                $this->transition($parent, 'in_progress', $actor, 'memulai tugas (diteruskan ke anggota)');
            }

            return $tasks;
        });
    }

    /** Pengajuan tugas mandiri (bottom-up) – berstatus "Menunggu Persetujuan". */
    public function submitSelf(array $data, User $actor): Task
    {
        $catalog = ! empty($data['catalog_id']) ? ActivityCatalog::find($data['catalog_id']) : null;
        $quantity = (float) ($data['quantity'] ?? 1);
        $complexity = $data['complexity'] ?? ($catalog->complexity ?? 'rendah');

        return DB::transaction(function () use ($data, $actor, $catalog, $quantity, $complexity) {
            $task = Task::create([
                'code' => Task::generateCode(),
                'team_id' => $data['team_id'] ?? $actor->teams()->value('teams.id'),
                'catalog_id' => $catalog?->id,
                'creator_id' => $actor->id,
                'assignee_id' => $actor->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'type' => 'mandiri',
                'status' => 'pengajuan',
                'priority' => $data['priority'] ?? 'sedang',
                'complexity' => $complexity,
                'quantity' => $quantity,
                'points' => Task::computePoints($catalog, $quantity, $complexity),
                'start_date' => $data['start_date'] ?? now()->toDateString(),
                'due_date' => $data['due_date'] ?? null,
                'submitted_at' => now(),
            ]);
            $task->verifier_id = $this->resolveVerifier($task);
            $task->save();

            $this->log($task, $actor, 'submitted_self', 'mengajukan tugas mandiri');

            if ($task->verifier_id) {
                $task->verifier->notify(PantauNotification::forTask(
                    'submission', $task, 'Pengajuan tugas mandiri dari '.$actor->display_name, null, $actor
                ));
            } else {
                $this->approveSubmission($task, $actor, true);
            }

            return $task;
        });
    }

    /* ============================================================ TRANSISI STATUS */

    public function start(Task $task, User $actor): void
    {
        $this->ensureAssignee($task, $actor);
        if (! in_array($task->status, ['todo', 'revisi'])) {
            return;
        }
        $this->transition($task, 'in_progress', $actor, 'mulai mengerjakan tugas');
    }

    /** Ajukan penyelesaian → menunggu verifikasi (wajib ada bukti dukung/output). */
    public function submit(Task $task, User $actor, ?string $note = null): void
    {
        $this->ensureAssignee($task, $actor);
        if (! in_array($task->status, ['todo', 'in_progress', 'revisi'])) {
            throw ValidationException::withMessages(['status' => 'Tugas ini tidak dalam status yang bisa diajukan selesai.']);
        }
        if (Settings::get('require_output_on_submit', true) && ! $task->hasOutput()) {
            throw ValidationException::withMessages(['output' => 'Unggah minimal satu dokumen output / bukti dukung (file, gambar, atau link) sebelum menandai selesai.']);
        }

        // Pengalihan approval saat verifikator cuti / dinas luar
        $verifier = $task->verifier;
        if ($verifier && $verifier->isOutOfOffice() && ! $verifier->is_pimpinan) {
            $pimpinan = User::primaryPimpinan($task->assignee_id);
            if ($pimpinan) {
                $task->verifier_id = $pimpinan->id;
                $this->log($task, null, 'rerouted', "verifikasi dialihkan ke {$pimpinan->display_name} karena {$verifier->display_name} sedang {$verifier->currentLeave()->type_label}");
            }
        }

        $task->submitted_at = now();
        $task->progress = 100;
        $task->sla_escalated = false;
        $this->transition($task, 'review', $actor, 'mengajukan penyelesaian tugas'.($note ? ': '.$note : ''));

        if ($note) {
            $task->comments()->create(['user_id' => $actor->id, 'body' => $note]);
        }

        if (! $task->verifier_id || $task->verifier_id === $actor->id && ! $this->hasAlternativeVerifier($task)) {
            // Tidak ada verifikator (mis. pimpinan mengerjakan tugasnya sendiri) → otomatis selesai
            $this->complete($task, null, 'disetujui otomatis (tanpa verifikator)');

            return;
        }

        $task->verifier->notify(PantauNotification::forTask(
            'review', $task, $actor->display_name.' menyelesaikan tugas – mohon verifikasi', null, $actor
        ));
    }

    public function approve(Task $task, User $actor, ?string $note = null): void
    {
        $this->ensureVerifier($task, $actor);
        if ($task->status !== 'review') {
            throw ValidationException::withMessages(['status' => 'Tugas belum diajukan untuk diverifikasi.']);
        }
        if ($note) {
            $task->comments()->create(['user_id' => $actor->id, 'body' => $note]);
        }
        $this->complete($task, $actor, 'memverifikasi & menyetujui hasil kerja');
    }

    public function requestRevision(Task $task, User $actor, string $note): void
    {
        $this->ensureVerifier($task, $actor);
        if ($task->status !== 'review') {
            throw ValidationException::withMessages(['status' => 'Hanya tugas yang menunggu verifikasi yang bisa diminta revisi.']);
        }
        $task->comments()->create(['user_id' => $actor->id, 'type' => 'revisi', 'body' => $note]);
        $task->progress = min($task->progress, 80);
        $this->transition($task, 'revisi', $actor, 'meminta revisi');

        $task->assignee->notify(PantauNotification::forTask('revision', $task, 'Tugas perlu revisi', $task->title.' — '.$note, $actor));
    }

    public function approveSubmission(Task $task, User $actor, bool $auto = false): void
    {
        if (! $auto) {
            $this->ensureVerifier($task, $actor);
        }
        if ($task->status !== 'pengajuan') {
            return;
        }
        $task->submitted_at = null;
        $this->transition($task, 'todo', $auto ? null : $actor, $auto ? 'pengajuan disetujui otomatis' : 'menyetujui pengajuan tugas mandiri');

        if (! $auto) {
            $task->assignee->notify(PantauNotification::forTask('approved', $task, 'Pengajuan tugas mandiri disetujui', null, $actor));
        }
    }

    public function reject(Task $task, User $actor, string $note): void
    {
        $this->ensureVerifier($task, $actor);
        if ($task->status !== 'pengajuan') {
            throw ValidationException::withMessages(['status' => 'Hanya pengajuan yang bisa ditolak.']);
        }
        $task->comments()->create(['user_id' => $actor->id, 'type' => 'revisi', 'body' => $note]);
        $this->transition($task, 'ditolak', $actor, 'menolak pengajuan tugas');
        $task->assignee->notify(PantauNotification::forTask('rejected', $task, 'Pengajuan tugas ditolak', $task->title.' — '.$note, $actor));
    }

    public function cancel(Task $task, User $actor, ?string $reason = null): void
    {
        if (! $this->canManage($task, $actor)) {
            abort(403, 'Anda tidak berhak membatalkan tugas ini.');
        }
        $this->transition($task, 'batal', $actor, 'membatalkan tugas'.($reason ? ': '.$reason : ''));
        if ($task->assignee_id !== $actor->id) {
            $task->assignee->notify(PantauNotification::forTask('rejected', $task, 'Tugas dibatalkan oleh '.$actor->display_name, $task->title.($reason ? ' — '.$reason : ''), $actor));
        }
    }

    public function reopen(Task $task, User $actor): void
    {
        if (! $this->canManage($task, $actor) && ! $this->canVerify($task, $actor)) {
            abort(403);
        }
        $task->completed_at = null;
        $task->approved_by = null;
        $this->transition($task, 'in_progress', $actor, 'membuka kembali tugas');
    }

    /** Dipakai papan Kanban (drag & drop). */
    public function moveTo(Task $task, string $status, User $actor): void
    {
        if ($task->status === $status) {
            return;
        }

        match ($status) {
            'in_progress' => $task->status === 'review' && $this->canVerify($task, $actor)
                ? throw ValidationException::withMessages(['status' => 'Gunakan tombol "Minta Revisi" untuk mengembalikan tugas.'])
                : $this->moveByAssignee($task, $status, $actor),
            'todo', 'revisi' => $this->moveByAssignee($task, $status, $actor),
            'review' => $this->submit($task, $actor),
            'selesai' => $this->approve($task, $actor),
            default => throw ValidationException::withMessages(['status' => 'Status tidak dikenal.']),
        };
    }

    private function moveByAssignee(Task $task, string $status, User $actor): void
    {
        $this->ensureAssignee($task, $actor);
        if (! in_array($task->status, ['todo', 'in_progress', 'revisi'])) {
            throw ValidationException::withMessages(['status' => 'Tugas yang sudah diajukan/selesai tidak bisa dipindah ke kolom ini.']);
        }
        if ($status === 'revisi' && $task->status !== 'revisi') {
            throw ValidationException::withMessages(['status' => 'Kolom revisi hanya untuk tugas yang dikembalikan verifikator.']);
        }
        $this->transition($task, $status, $actor, 'memindahkan tugas ke "'.Task::STATUSES[$status]['label'].'"');
    }

    public function updateProgress(Task $task, User $actor, int $progress): void
    {
        $this->ensureAssignee($task, $actor);
        $progress = max(0, min(100, $progress));
        $old = $task->progress;
        $task->progress = $progress;
        if ($task->status === 'todo' && $progress > 0) {
            $task->status = 'in_progress';
            $task->started_at ??= now();
        }
        $task->save();
        $this->log($task, $actor, 'progress', "memperbarui progres {$old}% → {$progress}%");
    }

    /* ============================================================ PERPANJANGAN */

    public function requestExtension(Task $task, User $actor, string $until, string $reason): void
    {
        $this->ensureAssignee($task, $actor);
        $task->extension_reason = '[DIAJUKAN] '.$until.' | '.$reason;
        $task->save();
        $this->log($task, $actor, 'extension_requested', 'mengajukan perpanjangan waktu sampai '.$until, ['until' => $until, 'reason' => $reason]);

        $target = $task->creator_id !== $actor->id ? $task->creator : $task->verifier;
        $target?->notify(PantauNotification::forTask('deadline', $task, $actor->display_name.' mengajukan perpanjangan waktu', $task->title.' → '.$until.' · '.$reason, $actor));
    }

    public function decideExtension(Task $task, User $actor, bool $approve): void
    {
        if (! $this->canManage($task, $actor) && ! $this->canVerify($task, $actor)) {
            abort(403);
        }
        $req = $this->pendingExtension($task);
        if (! $req) {
            return;
        }
        if ($approve) {
            $task->extension_until = $req['until'];
            $task->overdue_notified = false;
            $task->reminder_sent = false;
        }
        $task->extension_reason = ($approve ? '[DISETUJUI] ' : '[DITOLAK] ').$req['until'].' | '.$req['reason'];
        $task->save();
        $this->log($task, $actor, 'extension_'.($approve ? 'approved' : 'rejected'), ($approve ? 'menyetujui' : 'menolak').' perpanjangan waktu sampai '.$req['until']);
        $task->assignee->notify(PantauNotification::forTask($approve ? 'approved' : 'rejected', $task,
            'Perpanjangan waktu '.($approve ? 'disetujui' : 'ditolak'), $task->title, $actor));
    }

    public function pendingExtension(Task $task): ?array
    {
        if (! $task->extension_reason || ! str_starts_with($task->extension_reason, '[DIAJUKAN] ')) {
            return null;
        }
        [$until, $reason] = array_pad(explode(' | ', substr($task->extension_reason, 11), 2), 2, '');

        return ['until' => $until, 'reason' => $reason];
    }

    /* ============================================================ KOMENTAR */

    public function comment(Task $task, User $actor, string $body): void
    {
        $task->comments()->create(['user_id' => $actor->id, 'body' => $body]);

        $recipients = collect([$task->assignee, $task->creator, $task->verifier])
            ->filter()->unique('id')->reject(fn ($u) => $u->id === $actor->id);
        Notification::send($recipients, PantauNotification::forTask('comment', $task,
            $actor->display_name.' mengomentari tugas', \Illuminate\Support\Str::limit($body, 140), $actor));
    }

    /* ============================================================ OTORISASI */

    public function canVerify(Task $task, User $user): bool
    {
        if ($task->assignee_id === $user->id && $task->type !== 'mandiri' && ! $user->is_pimpinan) {
            return false; // segregation of duties
        }
        if ($task->assignee_id === $user->id && $this->hasAlternativeVerifier($task)) {
            return false;
        }

        return $task->verifier_id === $user->id || $user->is_pimpinan;
    }

    public function canManage(Task $task, User $user): bool
    {
        return $task->creator_id === $user->id || $user->is_pimpinan || $user->is_admin
            || ($task->team_id && $user->isLeaderOf($task->team_id) && $task->assignee_id !== $user->id);
    }

    public function canView(Task $task, User $user): bool
    {
        return Task::visibleTo($user)->whereKey($task->id)->exists();
    }

    public function isAssignee(Task $task, User $user): bool
    {
        return $task->assignee_id === $user->id;
    }

    /* ============================================================ INTERNAL */

    public function resolveVerifier(Task $task): ?int
    {
        $assignee = $task->assignee ?? User::find($task->assignee_id);
        $verifier = null;

        if ($task->type === 'mandiri') {
            $team = $task->team_id ? Team::with('leaders')->find($task->team_id) : null;
            $leader = $team?->leaders->first(fn ($l) => $l->id !== $assignee->id);
            $verifier = ($assignee->isLeaderOf($task->team_id) || ! $leader)
                ? User::primaryPimpinan($assignee->id)
                : $leader;
        } elseif ($task->creator_id !== $task->assignee_id) {
            $verifier = User::find($task->creator_id);
        } else {
            // Ketua tim menugaskan dirinya sendiri → wajib diverifikasi pimpinan
            $verifier = User::primaryPimpinan($assignee->id);
        }

        if ($verifier && ! $verifier->is_pimpinan && $verifier->isOutOfOffice()) {
            $verifier = User::primaryPimpinan($assignee->id) ?? $verifier;
        }

        return $verifier?->id === $assignee->id ? null : $verifier?->id;
    }

    private function hasAlternativeVerifier(Task $task): bool
    {
        return (bool) User::primaryPimpinan($task->assignee_id);
    }

    private function complete(Task $task, ?User $actor, string $message): void
    {
        $task->completed_at = now();
        $task->approved_by = $actor?->id;
        $task->progress = 100;
        $this->transition($task, 'selesai', $actor, $message);

        if ($actor && $task->assignee_id !== $actor->id) {
            $task->assignee->notify(PantauNotification::forTask('approved', $task,
                'Tugas disetujui · +'.rtrim(rtrim(number_format($task->points, 2, ',', '.'), '0'), ',').' poin', $task->title, $actor));
        }

        // Jika semua sub-tugas selesai, beri tahu pemegang tugas induk
        if ($task->parent_id) {
            $parent = $task->parent;
            $open = $parent->children()->whereNotIn('status', ['selesai', 'batal', 'ditolak'])->count();
            if ($open === 0 && $parent->assignee) {
                $parent->assignee->notify(PantauNotification::forTask('info', $parent,
                    'Semua sub-tugas telah selesai', $parent->title.' — silakan ajukan penyelesaian tugas induk.'));
            }
        }
    }

    public function transition(Task $task, string $status, ?User $actor, string $description, array $meta = []): void
    {
        $from = $task->status;
        $task->status = $status;
        if ($status === 'in_progress') {
            $task->started_at ??= now();
        }
        $task->save();
        $this->log($task, $actor, 'status', $description, array_merge(['from' => $from, 'to' => $status], $meta));
    }

    public function log(Task $task, ?User $actor, string $action, string $description, array $meta = []): void
    {
        $task->activities()->create([
            'user_id' => $actor?->id,
            'action' => $action,
            'description' => $description,
            'meta' => $meta ?: null,
        ]);
        if ($actor) {
            $actor->forceFill(['last_seen_at' => now()])->saveQuietly();
        }
    }

    private function ensureAssignee(Task $task, User $actor): void
    {
        if ($task->assignee_id !== $actor->id) {
            throw ValidationException::withMessages(['status' => 'Hanya pelaksana tugas yang dapat melakukan aksi ini.']);
        }
    }

    private function ensureVerifier(Task $task, User $actor): void
    {
        if (! $this->canVerify($task, $actor)) {
            throw ValidationException::withMessages(['status' => 'Anda bukan verifikator tugas ini.']);
        }
    }

    private function guessTeam(User $actor, User $assignee): ?int
    {
        $assigneeTeams = $assignee->teams()->pluck('teams.id');
        $shared = $actor->ledTeamIds()->intersect($assigneeTeams)->first();

        return $shared ?? $actor->ledTeamIds()->first() ?? $assigneeTeams->first();
    }

    private function sendCarbonCopy(Task $task, User $assignee, User $actor): void
    {
        if ($assignee->id === $actor->id) {
            return;
        }
        $leaders = $assignee->teams()->with('leaders')->get()
            ->flatMap->leaders
            ->unique('id')
            ->reject(fn ($l) => in_array($l->id, [$actor->id, $assignee->id]));

        // Hanya kirim tembusan jika pemberi tugas bukan ketua tim dari pegawai tsb.
        $actorLeadsAssignee = $assignee->teams()->pluck('teams.id')->intersect($actor->ledTeamIds())->isNotEmpty();
        if ($actorLeadsAssignee || $leaders->isEmpty()) {
            return;
        }

        Notification::send($leaders, PantauNotification::forTask('cc', $task,
            'Tembusan: '.$actor->display_name.' memberi tugas ke '.$assignee->display_name,
            $task->title.' · '.rtrim(rtrim(number_format($task->points, 2, ',', '.'), '0'), ',').' poin', $actor));
    }
}
