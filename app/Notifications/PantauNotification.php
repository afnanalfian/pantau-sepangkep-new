<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Satu kelas notifikasi generik untuk seluruh event aplikasi.
 * Tersimpan di database (lonceng di web) + dikirim sebagai push notification ke HP.
 */
class PantauNotification extends Notification
{
    public const KINDS = [
        'assigned' => ['icon' => 'clipboard', 'color' => 'blue'],
        'submission' => ['icon' => 'inbox', 'color' => 'violet'],
        'review' => ['icon' => 'eye', 'color' => 'amber'],
        'approved' => ['icon' => 'check', 'color' => 'green'],
        'revision' => ['icon' => 'rotate', 'color' => 'orange'],
        'rejected' => ['icon' => 'x', 'color' => 'rose'],
        'comment' => ['icon' => 'chat', 'color' => 'blue'],
        'deadline' => ['icon' => 'clock', 'color' => 'amber'],
        'overdue' => ['icon' => 'alert', 'color' => 'rose'],
        'escalation' => ['icon' => 'alert', 'color' => 'rose'],
        'cc' => ['icon' => 'mail', 'color' => 'slate'],
        'info' => ['icon' => 'bell', 'color' => 'blue'],
    ];

    public function __construct(
        public string $kind,
        public string $title,
        public string $body,
        public ?string $url = null,
        public ?int $taskId = null,
        public ?int $actorId = null,
    ) {}

    public static function forTask(string $kind, Task $task, string $title, ?string $body = null, ?User $actor = null): self
    {
        return new self($kind, $title, $body ?? ($task->code.' · '.$task->title), route('tasks.show', $task), $task->id, $actor?->id);
    }

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'task_id' => $this->taskId,
            'actor_id' => $this->actorId,
        ];
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => Str::limit($this->body, 180),
            'url' => $this->url ?? url('/'),
            'tag' => $this->taskId ? 'task-'.$this->taskId : 'pantau-'.$this->kind,
            'icon' => asset('icons/icon-192.png'),
            'badge' => asset('icons/badge-72.png'),
        ];
    }
}
