<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    protected $fillable = ['name', 'slug', 'color', 'icon', 'description', 'is_core', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_core' => 'boolean', 'is_active' => 'boolean'];
    }

    public const COLORS = [
        'blue' => ['bg' => 'bg-bps-blue', 'soft' => 'bg-bps-blue/10 text-bps-blue-700', 'ring' => 'ring-bps-blue/30', 'hex' => '#00A3E0'],
        'orange' => ['bg' => 'bg-bps-orange', 'soft' => 'bg-bps-orange/10 text-bps-orange-700', 'ring' => 'ring-bps-orange/30', 'hex' => '#F5951F'],
        'green' => ['bg' => 'bg-bps-green', 'soft' => 'bg-bps-green/10 text-bps-green-700', 'ring' => 'ring-bps-green/30', 'hex' => '#8CC63F'],
        'navy' => ['bg' => 'bg-navy-700', 'soft' => 'bg-navy-700/10 text-navy-700', 'ring' => 'ring-navy-700/30', 'hex' => '#123A63'],
        'purple' => ['bg' => 'bg-violet-500', 'soft' => 'bg-violet-500/10 text-violet-700', 'ring' => 'ring-violet-500/30', 'hex' => '#8B5CF6'],
        'rose' => ['bg' => 'bg-rose-500', 'soft' => 'bg-rose-500/10 text-rose-700', 'ring' => 'ring-rose-500/30', 'hex' => '#F43F5E'],
        'teal' => ['bg' => 'bg-teal-500', 'soft' => 'bg-teal-500/10 text-teal-700', 'ring' => 'ring-teal-500/30', 'hex' => '#14B8A6'],
        'slate' => ['bg' => 'bg-slate-500', 'soft' => 'bg-slate-500/10 text-slate-700', 'ring' => 'ring-slate-500/30', 'hex' => '#64748B'],
    ];

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot(['role', 'sort_order'])->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function leaders(): BelongsToMany
    {
        return $this->members()->wherePivot('role', 'ketua');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function color(string $key = 'bg'): string
    {
        return self::COLORS[$this->color][$key] ?? self::COLORS['blue'][$key];
    }
}
