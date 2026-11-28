<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskDelegation;
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
 * penugasan bertingkat (perorangan, banyak orang, ke tim), tugas bersama (kolaborator),
 * alih tugas antar pegawai (terima/tolak), pengajuan mandiri, verifikasi berjenjang, revisi,
 * wewenang ketua tim utama atas subtim, pengalihan approval saat cuti/DL, perpanjangan waktu, dan audit trail.
 *
 * "Pelaksana" (worker) = pelaksana utama (assignee_id) + kolaborator aktif tugas bersama.
 */
class TaskService
{
    /* ============================================================ PEMBUATAN */

    /**
     * Membuat penugasan (pimpinan / ketua tim / admin) untuk satu atau beberapa pegawai.
     * Setiap penerima mendapat tugasnya sendiri agar penyelesaiannya tercatat per orang.
     *
     * @return Collection<int, Task>
     */
    public function assign(array $data, array $assigneeIds, User $actor, ?Task $parent = null, bool $bersama = false): Collection
    {
        if (! $actor->canAssign() && ! $parent) {
            throw ValidationException::withMessages(['assignees' => 'Anda tidak memiliki wewenang memberi tugas.']);
        }
        $assigneeIds = array_values(array_unique(array_filter(array_map('intval', $assigneeIds))));
        if (! $assigneeIds) {
            throw ValidationException::withMessages(['assignees' => 'Pilih minimal satu pegawai.']);
        }

        $type = $actor->is_pimpinan ? 'disposisi' : 'penugasan';
        if ($bersama && count($assigneeIds) > 1) {
            return collect([$this->assignBersama($data, $assigneeIds, $actor, $parent, $type)]);
        }

        return DB::transaction(function () use ($data, $assigneeIds, $actor, $parent, $type) {
            $tasks = collect();
            foreach ($assigneeIds as $assigneeId) {
                $assignee = User::findOrFail($assigneeId);
                $teamId = $data['team_id'] ?? $parent?->team_id ?? $this->guessTeam($actor, $assignee);

                $task = Task::create([
                    'code' => Task::generateCode(),
                    'parent_id' => $parent?->id,
                    'team_id' => $teamId,
                    'creator_id' => $actor->id,
                    'assignee_id' => $assignee->id,
                    'title' => $data['title'],
                    'description' => $data['description'] ?? null,
                    'type' => $type,
                    'status' => 'todo',
                    'priority' => $data['priority'] ?? 'sedang',
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
                $this->notifyMainLeaders($task, $actor, 'cc', 'Tugas baru di '.($task->team?->name ?? 'tim').': '.$assignee->display_name);

                $tasks->push($task);
            }

            if ($parent && $parent->status === 'todo') {
                $this->transition($parent, 'in_progress', $actor, 'memulai tugas (diteruskan ke anggota)');
            }

            return $tasks;
        });
    }

    /** Satu tugas dikerjakan bersama beberapa pegawai (pelaksana utama = penerima pertama). */
    private function assignBersama(array $data, array $ids, User $actor, ?Task $parent, string $type): Task
    {
        return DB::transaction(function () use ($data, $ids, $actor, $parent, $type) {
            $users = User::whereIn('id', $ids)->get()->sortBy(fn ($u) => array_search($u->id, $ids))->values();
            $lead = $users->first();
            $task = Task::create([
                'code' => Task::generateCode(),
                'parent_id' => $parent?->id,
                'team_id' => $data['team_id'] ?? $parent?->team_id ?? $this->guessTeam($actor, $lead),
                'creator_id' => $actor->id,
                'assignee_id' => $lead->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'type' => $type,
                'status' => 'todo',
                'priority' => $data['priority'] ?? 'sedang',
                'start_date' => $data['start_date'] ?? now()->toDateString(),
                'due_date' => $data['due_date'] ?? null,
            ]);
            foreach ($users->slice(1) as $u) {
                $task->collaborators()->attach($u->id, ['status' => 'aktif', 'added_by' => $actor->id]);
            }
            $task->verifier_id = $this->resolveVerifier($task);
            $task->save();

            $this->log($task, $actor, 'created', 'membuat tugas bersama untuk '.$users->pluck('display_name')->join(', ', ' dan '));
            $this->notifyWorkers($task, PantauNotification::forTask('assigned', $task,
                'Tugas bersama dari '.$actor->display_name,
                $task->title.' · dikerjakan bersama '.$users->count().' orang'.($task->due_date ? ' · deadline '.$task->due_date->translatedFormat('d M Y') : ''), $actor), $actor->id);
            $this->notifyMainLeaders($task, $actor, 'cc', 'Tugas bersama baru di '.($task->team?->name ?? 'tim'));

            return $task;
        });
    }

    /**
     * Tugas untuk tim: diterima ketua tim tersebut, yang dapat mengerjakannya sendiri
     * atau meneruskannya ke anggota. Pimpinan/admin: tim mana pun; ketua tim utama: subtimnya.
     *
     * @return Collection<int, Task>
     */
    public function assignToTeams(array $data, array $teamIds, User $actor): Collection
    {
        $teams = Team::with('leaders')->whereIn('id', array_map('intval', $teamIds))->get();
        if ($teams->isEmpty()) {
            throw ValidationException::withMessages(['assignees' => 'Pilih minimal satu tim.']);
        }
        foreach ($teams as $team) {
            if (! $this->canAssignToTeam($actor, $team)) {
                throw ValidationException::withMessages(['assignees' => 'Anda tidak berwenang memberi tugas ke '.$team->name.'.']);
            }
            if ($team->leaders->isEmpty()) {
                throw ValidationException::withMessages(['assignees' => $team->name.' belum punya ketua tim.']);
            }
        }
        $type = $actor->is_pimpinan ? 'disposisi' : 'penugasan';

        return DB::transaction(function () use ($data, $teams, $actor, $type) {
            $out = collect();
            foreach ($teams as $team) {
                $leader = $team->leaders->first();
                $task = Task::create([
                    'code' => Task::generateCode(),
                    'team_id' => $team->id,
                    'creator_id' => $actor->id,
                    'assignee_id' => $leader->id,
                    'title' => $data['title'],
                    'description' => $data['description'] ?? null,
                    'type' => $type,
                    'status' => 'todo',
                    'priority' => $data['priority'] ?? 'sedang',
                    'start_date' => $data['start_date'] ?? now()->toDateString(),
                    'due_date' => $data['due_date'] ?? null,
                ]);
                $task->verifier_id = $this->resolveVerifier($task);
                $task->save();
                $this->log($task, $actor, 'created', "memberi tugas untuk {$team->fullName()} (diterima ketua tim {$leader->display_name})");
                if ($leader->id !== $actor->id) {
                    $leader->notify(PantauNotification::forTask('assigned', $task,
                        'Tugas untuk '.$team->name.' dari '.$actor->display_name,
                        $task->title.' · kerjakan sendiri atau teruskan ke anggota'.($task->due_date ? ' · deadline '.$task->due_date->translatedFormat('d M Y') : ''), $actor));
                }
                $this->notifyMainLeaders($task, $actor, 'cc', 'Tugas baru untuk '.$team->name);
                $out->push($task);
            }

            return $out;
        });
    }

    public function canAssignToTeam(User $actor, Team $team): bool
    {
        if ($actor->is_pimpinan || $actor->is_admin) {
            return true;
        }

        // Ketua tim utama → subtim di bawahnya
        return $team->parent_id && $actor->ledMainTeamIds()->contains($team->parent_id);
    }

    /** Tim yang boleh diberi tugas oleh user ini. */
    public function assignableTeams(User $actor): Collection
    {
        $q = Team::with(['parent', 'leaders'])->where('is_active', true)->orderByRaw('COALESCE(parent_id, id)')->orderByRaw('parent_id IS NOT NULL')->orderBy('sort_order');
        if (! $actor->is_pimpinan && ! $actor->is_admin) {
            $q->whereIn('parent_id', $actor->ledMainTeamIds());
        }

        return $q->get();
    }

    /** Pengajuan tugas mandiri (bottom-up) – berstatus "Menunggu Persetujuan". */
    public function submitSelf(array $data, User $actor): Task
    {
        return DB::transaction(function () use ($data, $actor) {
            $task = Task::create([
                'code' => Task::generateCode(),
                'team_id' => $data['team_id'] ?? $actor->teams()->value('teams.id'),
                'creator_id' => $actor->id,
                'assignee_id' => $actor->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'type' => 'mandiri',
                'status' => 'pengajuan',
                'priority' => $data['priority'] ?? 'sedang',
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
        $this->ensureWorker($task, $actor);
        if (! in_array($task->status, ['todo', 'revisi'])) {
            return;
        }
        $this->transition($task, 'in_progress', $actor, 'mulai mengerjakan tugas');
    }

    /** Ajukan penyelesaian → menunggu verifikasi (wajib ada bukti dukung/output). */
    public function submit(Task $task, User $actor, ?string $note = null): void
    {
        $this->ensureWorker($task, $actor);
        if (! in_array($task->status, ['todo', 'in_progress', 'revisi'])) {
            throw ValidationException::withMessages(['status' => 'Tugas ini tidak dalam status yang bisa diajukan selesai.']);
        }
        if (Settings::get('require_output_on_submit', true) && ! $task->hasOutput()) {
            throw ValidationException::withMessages(['output' => 'Unggah minimal satu dokumen output / bukti dukung (file, gambar, atau link) sebelum menandai selesai.']);
        }

        // Pengalihan approval saat verifikator cuti / dinas luar
        $verifier = $task->verifier;
        if ($verifier && $verifier->isOutOfOffice() && ! $verifier->is_pimpinan) {
            $pimpinan = User::primaryPimpinan($actor->id);
            if ($pimpinan && ! $task->isWorker($pimpinan)) {
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

        if (! $task->verifier_id || $task->isWorker($task->verifier_id) && ! $this->hasAlternativeVerifier($task)) {
            // Tidak ada verifikator (mis. pimpinan mengerjakan tugasnya sendiri) → otomatis selesai
            $this->complete($task, null, 'disetujui otomatis (tanpa verifikator)');

            return;
        }

        $task->verifier->notify(PantauNotification::forTask(
            'review', $task, $actor->display_name.' menyelesaikan tugas – mohon verifikasi', null, $actor
        ));
        if ($task->isBersama()) {
            $this->notifyWorkers($task, PantauNotification::forTask('info', $task, $actor->display_name.' mengirim tugas bersama untuk diverifikasi', $task->title, $actor), $actor->id);
        }
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

        $this->notifyWorkers($task, PantauNotification::forTask('revision', $task, 'Tugas perlu revisi', $task->title.' — '.$note, $actor));
    }

    /**
     * Ketua tim utama / pimpinan meminta revisi atas tugas yang SUDAH selesai.
     * Tugas kembali ke "Perlu revisi" dan tidak lagi dihitung selesai.
     */
    public function reviseCompleted(Task $task, User $actor, string $note): void
    {
        if (! $this->canOverride($task, $actor)) {
            abort(403, 'Hanya ketua tim utama atau pimpinan yang bisa meminta revisi tugas yang sudah selesai.');
        }
        if ($task->status !== 'selesai') {
            throw ValidationException::withMessages(['status' => 'Tugas belum selesai.']);
        }
        $task->comments()->create(['user_id' => $actor->id, 'type' => 'revisi', 'body' => $note]);
        $task->completed_at = null;
        $task->approved_by = null;
        $task->progress = min($task->progress, 80);
        $this->transition($task, 'revisi', $actor, 'meminta revisi atas tugas yang sudah selesai');
        $this->notifyWorkers($task, PantauNotification::forTask('revision', $task, 'Tugas yang sudah selesai diminta revisi oleh '.$actor->display_name, $task->title.' — '.$note, $actor));
        if ($task->verifier && $task->verifier_id !== $actor->id) {
            $task->verifier->notify(PantauNotification::forTask('info', $task, $actor->display_name.' meminta revisi tugas yang sudah Anda setujui', $task->title, $actor));
        }
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
        $this->notifyMainLeaders($task, $actor, 'cc', 'Tugas mandiri disetujui: '.$task->assignee->display_name);
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
        $wasDone = $task->status === 'selesai';
        if ($wasDone ? ! $this->canOverride($task, $actor) : ! $this->canManage($task, $actor)) {
            abort(403, 'Anda tidak berhak membatalkan tugas ini.');
        }
        if ($wasDone) {
            $task->completed_at = null;
            $task->approved_by = null;
        }
        $this->transition($task, 'batal', $actor, ($wasDone ? 'membatalkan tugas yang sudah selesai' : 'membatalkan tugas').($reason ? ': '.$reason : ''));
        TaskDelegation::where('task_id', $task->id)->where('status', 'menunggu')->update(['status' => 'dibatalkan', 'decided_at' => now()]);
        $this->notifyWorkers($task, PantauNotification::forTask('rejected', $task, 'Tugas dibatalkan oleh '.$actor->display_name, $task->title.($reason ? ' — '.$reason : ''), $actor), $actor->id);
    }

    /** Hapus tugas (soft delete). */
    public function delete(Task $task, User $actor): void
    {
        if (! $this->canDelete($task, $actor)) {
            abort(403, 'Anda tidak berhak menghapus tugas ini.');
        }
        $this->log($task, $actor, 'deleted', 'menghapus tugas');
        TaskDelegation::where('task_id', $task->id)->where('status', 'menunggu')->update(['status' => 'dibatalkan', 'decided_at' => now()]);
        if ($task->status !== 'batal') {
            $this->notifyWorkers($task, PantauNotification::forTask('rejected', $task, 'Tugas dihapus oleh '.$actor->display_name, $task->title, $actor), $actor->id);
        }
        $task->delete();
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
        $this->ensureWorker($task, $actor);
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
        $this->ensureWorker($task, $actor);
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
        $this->ensureWorker($task, $actor);
        $task->extension_reason = '[DIAJUKAN] '.$until.' | '.$reason;
        $task->save();
        $this->log($task, $actor, 'extension_requested', 'mengajukan perpanjangan waktu sampai '.$until, ['until' => $until, 'reason' => $reason]);

        $target = ! $task->isWorker($task->creator_id) ? $task->creator : $task->verifier;
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
        $this->notifyWorkers($task, PantauNotification::forTask($approve ? 'approved' : 'rejected', $task,
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

        $recipients = $task->workers()->concat([$task->creator, $task->verifier])
            ->filter()->unique('id')->reject(fn ($u) => $u->id === $actor->id);
        Notification::send($recipients, PantauNotification::forTask('comment', $task,
            $actor->display_name.' mengomentari tugas', \Illuminate\Support\Str::limit($body, 140), $actor));
    }

    /* ============================================================ ALIH TUGAS (DELEGASI) */

    /** Pelaksana meminta pegawai lain mengambil alih bagiannya. Penerima bisa menerima atau menolak. */
    public function requestDelegation(Task $task, User $actor, int $toUserId, ?string $note = null): TaskDelegation
    {
        $this->ensureWorker($task, $actor);
        if (! in_array($task->status, ['todo', 'in_progress', 'revisi'])) {
            throw ValidationException::withMessages(['delegateTo' => 'Tugas yang sudah diajukan/selesai tidak bisa dialihkan.']);
        }
        $to = User::active()->find($toUserId);
        if (! $to || $to->id === $actor->id) {
            throw ValidationException::withMessages(['delegateTo' => 'Pilih pegawai penerima.']);
        }
        if ($task->isWorker($to)) {
            throw ValidationException::withMessages(['delegateTo' => $to->display_name.' sudah menjadi pelaksana tugas ini.']);
        }
        if (TaskDelegation::where('task_id', $task->id)->where('from_user_id', $actor->id)->where('status', 'menunggu')->exists()) {
            throw ValidationException::withMessages(['delegateTo' => 'Masih ada permintaan alih tugas yang menunggu jawaban.']);
        }
        $d = TaskDelegation::create(['task_id' => $task->id, 'from_user_id' => $actor->id, 'to_user_id' => $to->id, 'note' => $note ?: null, 'status' => 'menunggu']);
        $this->log($task, $actor, 'delegation', 'meminta '.$to->display_name.' mengambil alih tugas'.($note ? ': '.$note : ''));
        $to->notify(PantauNotification::forTask('assigned', $task, $actor->display_name.' mengalihkan tugas kepada Anda',
            $task->title.' — buka tugas untuk menerima atau menolak'.($note ? ' · '.$note : ''), $actor));

        return $d;
    }

    public function acceptDelegation(TaskDelegation $d, User $actor): void
    {
        $this->ensurePendingFor($d, $actor);
        $task = $d->task;
        DB::transaction(function () use ($d, $task, $actor) {
            if ((int) $task->assignee_id === (int) $d->from_user_id) {
                $task->assignee_id = $actor->id;
            } else {
                $task->collaborators()->detach($d->from_user_id);
            }
            if ((int) $task->assignee_id !== $actor->id) {
                DB::table('task_collaborators')->updateOrInsert(['task_id' => $task->id, 'user_id' => $actor->id],
                    ['status' => 'aktif', 'added_by' => $d->from_user_id, 'updated_at' => now(), 'created_at' => now()]);
            }
            // verifikator tidak boleh menjadi pelaksana
            if ($task->verifier_id === $actor->id) {
                $task->verifier_id = $this->resolveVerifier($task->setRelation('assignee', User::find($task->assignee_id)));
            }
            $task->save();
            $d->update(['status' => 'diterima', 'decided_at' => now()]);
            $this->log($task, $actor, 'delegation', 'menerima alih tugas dari '.$d->from->display_name);
        });
        $note = PantauNotification::forTask('approved', $task, $actor->display_name.' menerima alih tugas', $task->title, $actor);
        collect([$d->from, $task->creator])->filter()->unique('id')->reject(fn ($u) => $u->id === $actor->id)->each->notify($note);
    }

    public function rejectDelegation(TaskDelegation $d, User $actor, ?string $reply = null): void
    {
        $this->ensurePendingFor($d, $actor);
        $d->update(['status' => 'ditolak', 'reply' => $reply ?: null, 'decided_at' => now()]);
        $this->log($d->task, $actor, 'delegation', 'menolak alih tugas dari '.$d->from->display_name.($reply ? ': '.$reply : ''));
        $d->from->notify(PantauNotification::forTask('rejected', $d->task, $actor->display_name.' menolak alih tugas', $d->task->title.($reply ? ' — '.$reply : ''), $actor));
    }

    public function cancelDelegation(TaskDelegation $d, User $actor): void
    {
        if ($d->status !== 'menunggu' || $d->from_user_id !== $actor->id) {
            abort(403);
        }
        $d->update(['status' => 'dibatalkan', 'decided_at' => now()]);
        $this->log($d->task, $actor, 'delegation', 'membatalkan permintaan alih tugas ke '.$d->to->display_name);
    }

    private function ensurePendingFor(TaskDelegation $d, User $actor): void
    {
        if ($d->to_user_id !== $actor->id) {
            abort(403, 'Permintaan ini bukan untuk Anda.');
        }
        if ($d->status !== 'menunggu' || ! $d->task || ! in_array($d->task->status, ['todo', 'in_progress', 'revisi'])) {
            throw ValidationException::withMessages(['status' => 'Permintaan alih tugas ini sudah tidak berlaku.']);
        }
    }

    /* ============================================================ TUGAS BERSAMA */

    /**
     * Tambah kolaborator. Pemberi tugas / pengelola: langsung aktif.
     * Pelaksana: diajukan dulu, menunggu persetujuan pemberi tugas.
     *
     * @return string 'aktif' | 'diajukan'
     */
    public function addCollaborators(Task $task, User $actor, array $userIds): string
    {
        $manage = $this->canManage($task, $actor);
        if (! $manage && ! $task->isWorker($actor)) {
            abort(403);
        }
        if (in_array($task->status, ['selesai', 'batal', 'ditolak'])) {
            throw ValidationException::withMessages(['collab' => 'Tugas ini sudah ditutup.']);
        }
        $ids = collect($userIds)->map(fn ($i) => (int) $i)->unique()->reject(fn ($i) => $task->isWorker($i))->values();
        $users = User::active()->whereIn('id', $ids)->get();
        if ($users->isEmpty()) {
            throw ValidationException::withMessages(['collab' => 'Pilih minimal satu pegawai.']);
        }
        if ($users->contains('id', $task->verifier_id)) {
            throw ValidationException::withMessages(['collab' => $task->verifier->display_name.' adalah verifikator tugas ini, tidak bisa menjadi pelaksana.']);
        }
        $status = $manage ? 'aktif' : 'diajukan';
        foreach ($users as $u) {
            DB::table('task_collaborators')->updateOrInsert(['task_id' => $task->id, 'user_id' => $u->id],
                ['status' => $status, 'added_by' => $actor->id, 'updated_at' => now(), 'created_at' => now()]);
        }
        $names = $users->pluck('display_name')->join(', ', ' dan ');
        if ($status === 'aktif') {
            $this->log($task, $actor, 'collaborator', 'menjadikan tugas bersama dengan '.$names);
            Notification::send($users, PantauNotification::forTask('assigned', $task, 'Anda diajak mengerjakan tugas bersama', $task->title, $actor));
        } else {
            $this->log($task, $actor, 'collaborator', 'mengusulkan '.$names.' sebagai kolaborator (menunggu persetujuan)');
            $approver = $this->collaboratorApprover($task);
            $approver?->notify(PantauNotification::forTask('submission', $task, $actor->display_name.' mengusulkan kolaborator tugas', $names.' · '.$task->title, $actor));
        }

        return $status;
    }

    public function decideCollaborator(Task $task, User $actor, int $userId, bool $approve): void
    {
        if (! $this->canManage($task, $actor)) {
            abort(403);
        }
        $row = DB::table('task_collaborators')->where('task_id', $task->id)->where('user_id', $userId)->where('status', 'diajukan')->first();
        if (! $row) {
            return;
        }
        $u = User::find($userId);
        if ($approve) {
            DB::table('task_collaborators')->where('id', $row->id)->update(['status' => 'aktif', 'updated_at' => now()]);
            $this->log($task, $actor, 'collaborator', 'menyetujui '.$u->display_name.' sebagai kolaborator');
            $u->notify(PantauNotification::forTask('assigned', $task, 'Anda diajak mengerjakan tugas bersama', $task->title, $actor));
        } else {
            DB::table('task_collaborators')->where('id', $row->id)->delete();
            $this->log($task, $actor, 'collaborator', 'menolak usulan kolaborator '.$u->display_name);
        }
        if ($row->added_by && $row->added_by !== $actor->id) {
            User::find($row->added_by)?->notify(PantauNotification::forTask($approve ? 'approved' : 'rejected', $task,
                'Usulan kolaborator '.$u->display_name.' '.($approve ? 'disetujui' : 'ditolak'), $task->title, $actor));
        }
    }

    /** Keluarkan kolaborator (pemberi tugas / pengelola). Bila tak tersisa, tugas kembali menjadi tugas perorangan. */
    public function removeCollaborator(Task $task, User $actor, int $userId): void
    {
        if (! $this->canManage($task, $actor)) {
            abort(403);
        }
        if ((int) $task->assignee_id === $userId) {
            // keluarkan pelaksana utama: kolaborator pertama naik menjadi pelaksana utama
            $next = $task->collaborators()->orderBy('task_collaborators.id')->first();
            if (! $next) {
                throw ValidationException::withMessages(['collab' => 'Tugas harus punya minimal satu pelaksana.']);
            }
            $task->collaborators()->detach($next->id);
            $task->assignee_id = $next->id;
            $task->save();
        } else {
            DB::table('task_collaborators')->where('task_id', $task->id)->where('user_id', $userId)->delete();
        }
        $u = User::find($userId);
        $this->log($task, $actor, 'collaborator', 'mengeluarkan '.$u?->display_name.' dari tugas'.($task->isBersama() ? ' bersama' : ' (kembali menjadi tugas perorangan)'));
        $u?->notify(PantauNotification::forTask('info', $task, 'Anda tidak lagi menjadi pelaksana tugas', $task->title, $actor));
    }

    /**
     * Pecah tugas bersama menjadi tugas masing-masing: setiap kolaborator mendapat salinan tugasnya sendiri
     * (status & progres ikut), surat tugas dan dokumen pendukung ikut disalin.
     *
     * @return Collection<int, Task> tugas baru
     */
    public function splitBersama(Task $task, User $actor): Collection
    {
        if (! $this->canManage($task, $actor)) {
            abort(403);
        }
        $collabs = $task->collaborators()->get();
        if ($collabs->isEmpty()) {
            return collect();
        }

        return DB::transaction(function () use ($task, $actor, $collabs) {
            $out = collect();
            $status = in_array($task->status, ['in_progress', 'revisi', 'review']) ? 'in_progress' : 'todo';
            foreach ($collabs as $u) {
                $copy = $task->replicate(['code', 'assignee_id', 'status', 'submitted_at', 'completed_at', 'approved_by', 'drive_folder_id', 'drive_folder_url', 'sla_escalated']);
                $copy->code = Task::generateCode();
                $copy->assignee_id = $u->id;
                $copy->status = $status;
                $copy->save();
                $copy->verifier_id = $this->resolveVerifier($copy->setRelation('assignee', $u));
                $copy->save();
                foreach ($task->attachments()->whereIn('category', ['surat_tugas', 'dokumen'])->get() as $a) {
                    $b = $a->replicate();
                    $b->task_id = $copy->id;
                    if ($a->local_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($a->local_path)) {
                        $path = dirname($a->local_path).'/'.\Illuminate\Support\Str::random(6).'_'.basename($a->local_path);
                        \Illuminate\Support\Facades\Storage::disk('local')->copy($a->local_path, $path);
                        $b->local_path = $path;
                    }
                    $b->save();
                }
                $this->log($copy, $actor, 'created', 'membuat tugas perorangan untuk '.$u->display_name.' (pecahan dari tugas bersama '.$task->code.')');
                $u->notify(PantauNotification::forTask('assigned', $copy, 'Tugas bersama dipecah: kini tugas Anda sendiri', $copy->title, $actor));
                $out->push($copy);
            }
            $task->collaborators()->detach();
            $this->log($task, $actor, 'collaborator', 'memecah tugas bersama menjadi tugas masing-masing ('.$out->pluck('code')->join(', ').')');

            return $out;
        });
    }

    /** Yang menyetujui usulan kolaborator: pemberi tugas, atau verifikator bila pemberi tugas adalah pelaksana. */
    public function collaboratorApprover(Task $task): ?User
    {
        return ! $task->isWorker($task->creator_id) ? $task->creator : $task->verifier;
    }

    /* ============================================================ OTORISASI */

    public function canVerify(Task $task, User $user): bool
    {
        if ($task->isWorker($user)) {
            return false; // pelaksana tidak memverifikasi tugasnya sendiri
        }

        return $task->verifier_id === $user->id || $user->is_pimpinan
            // ketua tim utama dapat mengintervensi tugas subtimnya
            || ($task->team_id && $task->team?->parent_id && $user->leadsMainOf($task->team_id));
    }

    public function canManage(Task $task, User $user): bool
    {
        if ($task->creator_id === $user->id || $user->is_pimpinan || $user->is_admin) {
            return true;
        }

        return $task->team_id && $user->supervisedTeamIds()->contains($task->team_id) && ! $task->isWorker($user);
    }

    /**
     * Wewenang tambahan ketua tim utama (dan pimpinan/admin) atas tugas di timnya & subtimnya:
     * membatalkan atau meminta revisi tugas yang SUDAH selesai, dan menghapus tugas.
     */
    public function canOverride(Task $task, User $user): bool
    {
        if ($user->is_pimpinan || $user->is_admin) {
            return true;
        }

        return $task->team_id && $user->leadsMainOf($task->team_id) && ! $task->isWorker($user);
    }

    public function canDelete(Task $task, User $user): bool
    {
        return $this->canOverride($task, $user)
            || ($task->creator_id === $user->id && in_array($task->status, ['todo', 'pengajuan', 'batal', 'ditolak']));
    }

    public function canView(Task $task, User $user): bool
    {
        return Task::visibleTo($user)->whereKey($task->id)->exists();
    }

    public function isAssignee(Task $task, User $user): bool
    {
        return $task->isWorker($user);
    }

    /* ============================================================ INTERNAL */

    public function resolveVerifier(Task $task): ?int
    {
        $assignee = $task->assignee ?? User::find($task->assignee_id);
        $verifier = null;

        $team = $task->team_id ? Team::with(['leaders', 'parent.leaders'])->find($task->team_id) : null;
        // Atasan langsung ketua: ketua tim utama bila subtim, selain itu pimpinan
        $upper = fn () => $team?->parent?->leaders->first(fn ($l) => $l->id !== $assignee->id) ?? User::primaryPimpinan($assignee->id);

        if ($task->type === 'mandiri') {
            $leader = $team?->leaders->first(fn ($l) => $l->id !== $assignee->id);
            $verifier = ($assignee->isLeaderOf($task->team_id) || ! $leader) ? $upper() : $leader;
        } elseif ($task->creator_id !== $task->assignee_id) {
            $verifier = User::find($task->creator_id);
        } else {
            // Ketua menugaskan dirinya sendiri → diverifikasi ketua tim utama (subtim) atau pimpinan
            $verifier = $upper();
        }
        if ($verifier && in_array($verifier->id, $task->exists ? $task->workerIds() : [$assignee->id])) {
            $verifier = $upper();
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

        if ($actor) {
            $this->notifyWorkers($task, PantauNotification::forTask('approved', $task,
                'Tugas disetujui & dinyatakan selesai', $task->title, $actor), $actor->id);
        }
        $this->notifyMainLeaders($task, $actor, 'approved', 'Tugas selesai: '.$task->workers()->pluck('display_name')->join(', '));

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

    private function ensureWorker(Task $task, User $actor): void
    {
        if (! $task->isWorker($actor)) {
            throw ValidationException::withMessages(['status' => 'Hanya pelaksana tugas yang dapat melakukan aksi ini.']);
        }
    }

    /** Kirim notifikasi ke seluruh pelaksana (pelaksana utama + kolaborator). */
    public function notifyWorkers(Task $task, PantauNotification $n, ?int $exceptId = null): void
    {
        $users = $task->workers()->reject(fn ($u) => $u->id === $exceptId);
        if ($users->isNotEmpty()) {
            Notification::send($users, $n);
        }
    }

    /**
     * Ketua tim utama wajib mengetahui tugas yang dibuat & diselesaikan di timnya dan subtimnya.
     * Tidak dikirim ke pelaku, pelaksana, pemberi tugas, atau verifikator (mereka sudah mendapat notifikasi sendiri).
     */
    private function notifyMainLeaders(Task $task, ?User $actor, string $kind, string $title): void
    {
        if (! $task->team_id) {
            return;
        }
        $team = Team::with(['parent.leaders', 'leaders'])->find($task->team_id);
        $main = $team?->parent ?? $team;
        if (! $main) {
            return;
        }
        $skip = array_filter(array_merge($task->workerIds(), [$actor?->id, $task->creator_id, $task->verifier_id]));
        $to = $main->leaders->reject(fn ($l) => in_array($l->id, $skip));
        if ($to->isNotEmpty()) {
            Notification::send($to, PantauNotification::forTask($kind, $task, $title, $task->code.' · '.$task->title, $actor));
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
            $task->title.($task->due_date ? ' · deadline '.$task->due_date->format('d/m/Y') : ''), $actor));
    }
}
