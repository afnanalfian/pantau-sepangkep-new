<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Leave extends Model
{
    protected $fillable = ['user_id', 'type', 'start_date', 'end_date', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    public const TYPES = [
        'cuti' => 'Cuti',
        'dinas_luar' => 'Dinas Luar',
        'diklat' => 'Diklat / Pelatihan',
        'sakit' => 'Sakit',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
