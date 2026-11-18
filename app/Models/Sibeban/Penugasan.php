<?php

namespace App\Models\Sibeban;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Penugasan extends Model
{
    protected $table = 'sibeban_penugasans';

    public const JABATAN = ['ppl' => 'PPL', 'pml' => 'PML'];

    protected $fillable = ['kegiatan_id', 'mitra_id', 'jabatan'];

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class);
    }

    public function mitra(): BelongsTo
    {
        return $this->belongsTo(Mitra::class);
    }

    public function honors(): HasMany
    {
        return $this->hasMany(HonorBulanan::class);
    }

    /** [bulan => jumlah] */
    public function honorPerBulan(): array
    {
        return $this->honors->pluck('jumlah', 'bulan')->map(fn ($v) => (int) $v)->all();
    }
}
