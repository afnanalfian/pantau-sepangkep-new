<?php

namespace App\Models\Sibeban;

use App\Models\User;
use App\Support\Sibeban\Periode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Pulsa extends Model
{
    protected $table = 'sibeban_pulsas';

    protected $fillable = ['nama', 'bulan_mulai', 'bulan_selesai', 'jumlah', 'user_id', 'pembuat'];

    protected $casts = ['jumlah' => 'integer'];

    public function mitras(): BelongsToMany
    {
        return $this->belongsToMany(Mitra::class, 'sibeban_pulsa_petugas')->withTimestamps();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** Pulsa yang periodenya beririsan dengan bulan $mulai s.d. $selesai. */
    public function scopeBeririsan(Builder $q, string $mulai, string $selesai): Builder
    {
        return $q->where('bulan_mulai', '<=', $selesai)->where('bulan_selesai', '>=', $mulai);
    }

    /** "Nov 2026" atau "Nov 2026 – Jan 2027" */
    public function periode(bool $pendek = true): string
    {
        return $this->bulan_mulai === $this->bulan_selesai
            ? Periode::label($this->bulan_mulai, $pendek)
            : Periode::label($this->bulan_mulai, $pendek).' – '.Periode::label($this->bulan_selesai, $pendek);
    }

    public function bisaDiubah(?User $u = null): bool
    {
        $u ??= auth()->user();

        return $u && ($u->is_admin || ($this->user_id && $this->user_id === $u->id));
    }
}
