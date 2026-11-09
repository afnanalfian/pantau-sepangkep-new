<?php

namespace App\Models;

use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use Notifiable, SoftDeletes;

    protected $fillable = [
        'username', 'name', 'nip', 'jabatan', 'email', 'phone', 'avatar_path', 'password',
        'is_admin', 'is_pimpinan', 'is_active', 'must_change_password', 'monthly_target',
        'password_changed_at', 'last_login_at', 'last_seen_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_pimpinan' => 'boolean',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /* ---------------------------------------------------------------- Relasi */

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)->withPivot(['role', 'sort_order'])->withTimestamps();
    }

    public function ledTeams(): BelongsToMany
    {
        return $this->teams()->wherePivot('role', 'ketua');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    /* ---------------------------------------------------------------- Scope */

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** Pegawai aktif (tanpa akun sistem "admin") – untuk daftar beban kerja & laporan. */
    public function scopePegawai(Builder $q): Builder
    {
        return $q->where('is_active', true)->where('username', '!=', 'admin');
    }

    /* ---------------------------------------------------------------- Atribut */

    public function getDisplayNameAttribute(): string
    {
        return $this->name ?: $this->username;
    }

    public function getFirstNameAttribute(): string
    {
        $skip = ['dr', 'drs', 'dra', 'ir', 'h', 'hj', 'prof', 'muh', 'muhammad', 'm', 'a', 'andi'];
        $parts = preg_split('/\s+/', trim(Str::before($this->display_name, ','))) ?: [];
        foreach ($parts as $p) {
            if (! in_array(Str::lower(rtrim($p, '.')), $skip)) {
                return $p;
            }
        }

        return $parts[0] ?? $this->username;
    }

    public function getInitialsAttribute(): string
    {
        $parts = preg_split('/[\s._]+/', trim($this->display_name)) ?: [];
        $skip = ['dr', 'drs', 'dra', 'ir', 'h', 'hj', 'prof'];
        $parts = array_values(array_filter($parts, fn ($p) => $p !== '' && ! str_contains($p, ',') && ! in_array(Str::lower(rtrim($p, '.')), $skip)));

        return Str::upper(Str::substr($parts[0] ?? '?', 0, 1).Str::substr($parts[1] ?? '', 0, 1));
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }

    /** Warna avatar deterministik dari palet BPS. */
    public function getAvatarColorAttribute(): string
    {
        $palette = ['bg-bps-blue', 'bg-bps-orange', 'bg-bps-green', 'bg-sky-700', 'bg-amber-600', 'bg-lime-700', 'bg-cyan-600', 'bg-indigo-500'];

        return $palette[crc32($this->username) % count($palette)];
    }

    /* ---------------------------------------------------------------- Peran */

    public function isLeaderOf(Team|int|null $team): bool
    {
        if (! $team) {
            return false;
        }
        $id = $team instanceof Team ? $team->id : $team;

        return $this->ledTeamIds()->contains($id);
    }

    public function ledTeamIds(): Collection
    {
        return once(fn () => $this->ledTeams()->pluck('teams.id'));
    }

    public function isLeader(): bool
    {
        return $this->ledTeamIds()->isNotEmpty();
    }

    /** Boleh membuat penugasan untuk orang lain. */
    public function canAssign(): bool
    {
        return $this->is_pimpinan || $this->is_admin || $this->isLeader();
    }

    public function canMonitor(): bool
    {
        return $this->is_pimpinan || $this->is_admin || $this->isLeader();
    }

    /** Daftar pegawai yang dapat diberi tugas oleh user ini. */
    public function assignableUsers(): Collection
    {
        if ($this->is_pimpinan || $this->is_admin) {
            return User::active()->orderBy('name')->get();
        }

        // Ketua tim: anggota timnya sendiri + lintas tim (dengan tembusan ke ketua asal)
        return User::active()->orderBy('name')->get();
    }

    /** Ketua tim dari user ini (untuk tim tertentu atau tim pertamanya). */
    public function leaderFor(?int $teamId = null): ?User
    {
        $teams = $this->teams()->with('leaders')->get();
        $team = $teamId ? $teams->firstWhere('id', $teamId) : $teams->first();

        return $team?->leaders->first(fn ($l) => $l->id !== $this->id);
    }

    public function roleLabels(): array
    {
        $labels = [];
        if ($this->is_admin) {
            $labels[] = 'Administrator';
        }
        if ($this->is_pimpinan) {
            $labels[] = 'Pimpinan';
        }
        if ($this->isLeader()) {
            $labels[] = 'Ketua Tim';
        }
        if (! $labels) {
            $labels[] = 'Pegawai';
        }

        return $labels;
    }

    /* ---------------------------------------------------------------- Cuti / Dinas Luar */

    public function currentLeave(?Carbon $date = null): ?Leave
    {
        $date ??= now();

        return $this->leaves()
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();
    }

    public function isOutOfOffice(?Carbon $date = null): bool
    {
        return (bool) $this->currentLeave($date);
    }

    public function monthlyTarget(): int
    {
        return (int) ($this->monthly_target ?: Settings::get('default_monthly_target', 100));
    }

    /** Pimpinan utama (penerima eskalasi & pengalihan approval). */
    public static function primaryPimpinan(?int $exceptId = null): ?User
    {
        $preferred = (int) Settings::get('primary_pimpinan_id');
        $q = static::active()->where('is_pimpinan', true);
        if ($exceptId) {
            $q->where('id', '!=', $exceptId);
        }
        $all = $q->get();

        return $all->firstWhere('id', $preferred)
            ?? $all->first(fn ($u) => ! $u->isOutOfOffice())
            ?? $all->first();
    }
}
