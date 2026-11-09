<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'parent_id', 'team_id', 'catalog_id', 'creator_id', 'assignee_id', 'verifier_id',
        'title', 'description', 'type', 'status', 'priority', 'complexity', 'quantity', 'points', 'progress', 'position',
        'start_date', 'due_date', 'started_at', 'submitted_at', 'completed_at', 'approved_by',
        'extension_until', 'extension_reason', 'sla_escalated', 'reminder_sent', 'overdue_notified',
        'drive_folder_id', 'drive_folder_url',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_date' => 'date',
            'extension_until' => 'date',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
            'quantity' => 'float',
            'points' => 'float',
            'sla_escalated' => 'boolean',
            'reminder_sent' => 'boolean',
            'overdue_notified' => 'boolean',
        ];
    }

    /* ------------------------------------------------------------ Konstanta */

    public const STATUSES = [
        'pengajuan' => ['label' => 'Menunggu persetujuan', 'color' => 'violet', 'icon' => 'inbox'],
        'todo' => ['label' => 'Belum dikerjakan', 'color' => 'slate', 'icon' => 'circle'],
        'in_progress' => ['label' => 'Sedang dikerjakan', 'color' => 'blue', 'icon' => 'play'],
        'revisi' => ['label' => 'Perlu revisi', 'color' => 'orange', 'icon' => 'rotate'],
        'review' => ['label' => 'Menunggu verifikasi', 'color' => 'amber', 'icon' => 'eye'],
        'selesai' => ['label' => 'Selesai', 'color' => 'green', 'icon' => 'check'],
        'ditolak' => ['label' => 'Ditolak', 'color' => 'rose', 'icon' => 'x'],
        'batal' => ['label' => 'Dibatalkan', 'color' => 'slate', 'icon' => 'x'],
    ];

    public const BOARD_COLUMNS = ['todo', 'in_progress', 'revisi', 'review', 'selesai'];

    public const ACTIVE_STATUSES = ['todo', 'in_progress', 'revisi', 'review'];

    public const PRIORITIES = [
        'tinggi' => ['label' => 'Tinggi', 'class' => 'bg-rose-50 text-rose-700 ring-rose-600/20'],
        'sedang' => ['label' => 'Sedang', 'class' => 'bg-amber-50 text-amber-700 ring-amber-600/20'],
        'rendah' => ['label' => 'Rendah', 'class' => 'bg-slate-50 text-slate-600 ring-slate-500/20'],
    ];

    public const COMPLEXITY = [
        'rendah' => ['label' => 'Rendah', 'coef' => 1.0],
        'sedang' => ['label' => 'Sedang', 'coef' => 1.2],
        'tinggi' => ['label' => 'Tinggi / kritis', 'coef' => 1.5],
    ];

    public const TYPES = [
        'penugasan' => 'Penugasan',
        'disposisi' => 'Disposisi pimpinan',
        'mandiri' => 'Pengajuan mandiri',
    ];

    /* ------------------------------------------------------------ Relasi */

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(ActivityCatalog::class, 'catalog_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id')->withTrashed();
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id')->withTrashed();
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verifier_id')->withTrashed();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class)->latest();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->oldest();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TaskActivity::class)->latest('created_at')->latest('id');
    }

    /* ------------------------------------------------------------ Scope */

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function scopeOverdue(Builder $q): Builder
    {
        return $q->whereIn('status', ['todo', 'in_progress', 'revisi'])
            ->whereNotNull('due_date')
            ->whereRaw('COALESCE(extension_until, due_date) < ?', [now()->toDateString()]);
    }

    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if ($user->is_pimpinan || $user->is_admin) {
            return $q;
        }
        $teamIds = $user->ledTeamIds();

        return $q->where(function ($w) use ($user, $teamIds) {
            $w->where('assignee_id', $user->id)
                ->orWhere('creator_id', $user->id)
                ->orWhere('verifier_id', $user->id);
            if ($teamIds->isNotEmpty()) {
                $w->orWhereIn('team_id', $teamIds)
                    ->orWhereIn('assignee_id', function ($sub) use ($teamIds) {
                        $sub->select('user_id')->from('team_user')->whereIn('team_id', $teamIds);
                    });
            }
        });
    }

    /* ------------------------------------------------------------ Helper */

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status]['label'] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status]['color'] ?? 'slate';
    }

    public function effectiveDueDate()
    {
        return $this->extension_until ?? $this->due_date;
    }

    public function isOverdue(): bool
    {
        $due = $this->effectiveDueDate();

        return $due && in_array($this->status, ['todo', 'in_progress', 'revisi'])
            && $due->copy()->endOfDay()->isPast();
    }

    public function isDueSoon(): bool
    {
        $due = $this->effectiveDueDate();

        return $due && ! $this->isOverdue() && in_array($this->status, ['todo', 'in_progress', 'revisi'])
            && $due->copy()->endOfDay()->diffInHours(now(), true) <= 48;
    }

    public function hasOutput(): bool
    {
        return $this->attachments()->where('category', 'output')->exists();
    }

    public function url(): string
    {
        return route('tasks.show', $this);
    }

    public static function generateCode(): string
    {
        $prefix = 'TGS-'.now()->format('ym').'-';
        $last = static::withTrashed()->where('code', 'like', $prefix.'%')->orderByDesc('code')->value('code');
        $n = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    public static function computePoints(?ActivityCatalog $catalog, float $quantity, string $complexity): float
    {
        if ($catalog) {
            return round($catalog->points * max($quantity, 0), 2);
        }

        return round(max($quantity, 0) * (self::COMPLEXITY[$complexity]['coef'] ?? 1), 2);
    }
}
