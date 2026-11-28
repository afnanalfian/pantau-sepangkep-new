<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Permintaan alih tugas dari satu pegawai ke pegawai lain (bisa diterima / ditolak). */
class TaskDelegation extends Model
{
    protected $fillable = ['task_id', 'from_user_id', 'to_user_id', 'note', 'status', 'reply', 'decided_at'];

    protected $casts = ['decided_at' => 'datetime'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function from(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id')->withTrashed();
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id')->withTrashed();
    }
}
