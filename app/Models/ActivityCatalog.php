<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityCatalog extends Model
{
    protected $fillable = ['team_id', 'rumpun', 'name', 'unit', 'points', 'complexity', 'rhk', 'is_active'];

    protected function casts(): array
    {
        return ['points' => 'float', 'is_active' => 'boolean'];
    }

    public const RUMPUN = ['Pranata Komputer', 'Statistisi', 'Keuangan', 'Tata Usaha', 'Umum'];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
