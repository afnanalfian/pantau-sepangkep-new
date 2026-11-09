<?php

namespace App\Jobs;

use App\Models\TaskAttachment;
use App\Services\GoogleDriveService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class SyncAttachmentToDrive implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 900;

    public function __construct(public int $attachmentId) {}

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(GoogleDriveService $drive): void
    {
        $a = TaskAttachment::with('task.assignee')->find($this->attachmentId);
        if (! $a || $a->kind !== 'file' || $a->drive_file_id || ! $a->local_path || ! $drive->enabled()) {
            return;
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($a->local_path)) {
            $a->update(['sync_status' => 'failed', 'sync_error' => 'File lokal tidak ditemukan.']);

            return;
        }

        // Kunci per-tugas agar pembuatan folder tidak dobel ketika banyak file diunggah bersamaan.
        Cache::lock('drive-task-'.$a->task_id, 120)->block(60, function () use ($a, $drive) {
            $drive->categoryFolderId($a->task, $a->category, $a->folder_label);
        });

        $parent = $drive->categoryFolderId($a->task->fresh(), $a->category, $a->folder_label);
        $result = $drive->upload($disk->path($a->local_path), $a->name, $a->mime, $parent);

        $a->update([
            'drive_file_id' => $result['id'],
            'url' => $result['webViewLink'],
            'sync_status' => 'synced',
            'sync_error' => null,
        ]);

        if (! config('services.google_drive.keep_local_copy')) {
            $disk->delete($a->local_path);
            $a->update(['local_path' => null]);
        }
    }

    public function failed(\Throwable $e): void
    {
        TaskAttachment::whereKey($this->attachmentId)->update([
            'sync_status' => 'failed',
            'sync_error' => mb_substr($e->getMessage(), 0, 1000),
        ]);
    }
}
