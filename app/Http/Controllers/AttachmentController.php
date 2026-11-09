<?php

namespace App\Http\Controllers;

use App\Models\TaskAttachment;
use App\Services\GoogleDriveService;
use App\Services\TaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public function show(Request $request, TaskAttachment $attachment, TaskService $tasks, GoogleDriveService $drive)
    {
        abort_unless($attachment->task && $tasks->canView($attachment->task, $request->user()), 403);

        if ($attachment->kind === 'link') {
            return redirect()->away($attachment->url);
        }

        $disposition = $request->boolean('inline') ? 'inline' : 'attachment';
        $headers = [
            'Content-Type' => $attachment->mime ?: 'application/octet-stream',
            'Content-Disposition' => $disposition.'; filename="'.addslashes($attachment->name).'"; filename*=UTF-8\'\''.rawurlencode($attachment->name),
            'Cache-Control' => 'private, max-age=3600',
        ];

        if ($attachment->local_path && Storage::disk('local')->exists($attachment->local_path)) {
            return response()->file(Storage::disk('local')->path($attachment->local_path), $headers);
        }

        if ($attachment->drive_file_id && $drive->enabled()) {
            $body = $drive->stream($attachment->drive_file_id);

            return response()->stream(function () use ($body) {
                while (! $body->eof()) {
                    echo $body->read(1024 * 256);
                    flush();
                }
            }, 200, $headers);
        }

        if ($attachment->url) {
            return redirect()->away($attachment->url);
        }

        abort(404, 'File tidak ditemukan.');
    }
}
