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
        'honor_ppl', 'honor_pml', 'keterangan', 'sumber',
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

    public function honorDefault(string $jabatan): int
    {
        return $jabatan === 'pml' ? (int) $this->honor_pml : (int) $this->honor_ppl;
    }
}
