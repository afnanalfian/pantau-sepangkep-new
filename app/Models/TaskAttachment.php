<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

class TaskAttachment extends Model
{
    protected $fillable = [
        'task_id', 'user_id', 'category', 'kind', 'name', 'url', 'folder_label', 'local_path',
        'mime', 'size', 'drive_file_id', 'sync_status', 'sync_error',
    ];

    public const CATEGORIES = [
        'surat_tugas' => ['label' => 'Surat tugas', 'folder' => '1. Surat Tugas'],
        'dokumen' => ['label' => 'Dokumen pendukung', 'folder' => '2. Dokumen Pendukung'],
        'gambar' => ['label' => 'Gambar / dokumentasi', 'folder' => '3. Gambar'],
        'output' => ['label' => 'Output / hasil kerja', 'folder' => '4. Output'],
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function isImage(): bool
    {
        return $this->kind === 'file' && Str::startsWith((string) $this->mime, 'image/');
    }

    public function extension(): string
    {
        return Str::lower(pathinfo($this->name, PATHINFO_EXTENSION));
    }

    public function iconType(): string
    {
        if ($this->kind === 'link') {
            return 'link';
        }
        $ext = $this->extension();

        return match (true) {
            $this->isImage() => 'image',
            $ext === 'pdf' => 'pdf',
            in_array($ext, ['doc', 'docx', 'odt', 'rtf']) => 'word',
            in_array($ext, ['xls', 'xlsx', 'csv', 'ods']) => 'excel',
            in_array($ext, ['ppt', 'pptx']) => 'ppt',
            in_array($ext, ['zip', 'rar', '7z']) => 'zip',
            default => 'file',
        };
    }

    public function humanSize(): string
    {
        return $this->size ? Number::fileSize($this->size, 1) : '';
    }

    /** URL untuk membuka: link eksternal langsung, file lewat route aplikasi. */
    public function openUrl(): string
    {
        return $this->kind === 'link' ? (string) $this->url : route('attachments.show', $this);
    }

    public function previewUrl(): string
    {
        return route('attachments.show', ['attachment' => $this, 'inline' => 1]);
    }
}
