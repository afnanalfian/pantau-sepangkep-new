<?php

namespace App\Services;

use App\Jobs\SyncAttachmentToDrive;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachmentService
{
    public function __construct(private GoogleDriveService $drive) {}

    public function storeFile(Task $task, UploadedFile $file, string $category, User $user, ?string $folderLabel = null): TaskAttachment
    {
        $original = $file->getClientOriginalName() ?: ('file.'.$file->extension());
        $ext = Str::lower(pathinfo($original, PATHINFO_EXTENSION) ?: $file->extension());
        $path = $file->storeAs('attachments/'.$task->id, Str::uuid().($ext ? '.'.$ext : ''), 'local');

        $attachment = $task->attachments()->create([
            'user_id' => $user->id,
            'category' => $category,
            'kind' => 'file',
            'name' => Str::limit($original, 250, ''),
            'folder_label' => $folderLabel,
            'local_path' => $path,
            'mime' => $file->getMimeType() ?: $file->getClientMimeType(),
            'size' => $file->getSize() ?: 0,
            'sync_status' => $this->drive->enabled() ? 'pending' : 'local',
        ]);

        if ($attachment->sync_status === 'pending') {
            SyncAttachmentToDrive::dispatch($attachment->id);
        }

        return $attachment;
    }

    public function addLink(Task $task, string $url, ?string $name, string $category, User $user): TaskAttachment
    {
        if (! Str::startsWith($url, ['http://', 'https://'])) {
            $url = 'https://'.$url;
        }

        return $task->attachments()->create([
            'user_id' => $user->id,
            'category' => $category,
            'kind' => 'link',
            'name' => $name ?: Str::limit(preg_replace('#^https?://#', '', $url), 80),
            'url' => $url,
            'sync_status' => 'synced',
        ]);
    }

    public function delete(TaskAttachment $attachment): void
    {
        if ($attachment->local_path) {
            Storage::disk('local')->delete($attachment->local_path);
        }
        if ($attachment->drive_file_id && $this->drive->enabled()) {
            try {
                $this->drive->delete($attachment->drive_file_id);
            } catch (\Throwable $e) {
                report($e);
            }
        }
        $attachment->delete();
    }
}
