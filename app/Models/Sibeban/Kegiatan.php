<?php

namespace App\Models\Sibeban;

use App\Support\Sibeban\Periode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kegiatan extends Model
{
    protected $table = 'sibeban_kegiatans';

    public const JENIS = ['Survei', 'Sensus'];

    protected $fillable = [
        'nama', 'tim', 'jenis', 'tanggal_mulai', 'tanggal_selesai',
        'honor_ppl', 'honor_pml', 'keterangan', 'sumber', 'user_id', 'pembuat',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'honor_ppl' => 'integer',
        'honor_pml' => 'integer',
    ];

    public function penugasans(): HasMany
    {
        return $this->hasMany(Penugasan::class);
    }

    /** Daftar bulan ('YYYY-MM') yang dicakup periode kegiatan. */
    public function bulans(): array
    {
        return Periode::bulanAntara($this->tanggal_mulai, $this->tanggal_selesai);
    }

    /** Admin atau pembuat kegiatan boleh memilih petugas & mengimpor honor. */
    public function bisaDiubah(?\App\Models\User $u = null): bool
    {
        $u ??= auth()->user();

        return $u && ($u->is_admin || ($this->user_id && $this->user_id === $u->id));
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class)->withTrashed();
    }
}
