<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PresensiKegiatan extends Model
{
    protected $fillable = ['jenis', 'nama', 'tanggal', 'jam', 'tempat', 'keterangan', 'created_by'];

    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }

    public const JENIS = [
        'apel' => ['label' => 'Apel', 'class' => 'bg-bps-orange-50 text-bps-orange-700 ring-bps-orange/30', 'dot' => 'bg-bps-orange'],
        'rapat' => ['label' => 'Rapat', 'class' => 'bg-bps-blue-50 text-bps-blue-700 ring-bps-blue/30', 'dot' => 'bg-bps-blue'],
    ];

    public const STATUS = [
        'hadir' => 'Hadir',
        'izin' => 'Izin / DL / Cuti',
        'absen' => 'Tidak hadir',
    ];

    public function kehadirans(): HasMany
    {
        return $this->hasMany(PresensiKehadiran::class);
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function jenisLabel(): string
    {
        return self::JENIS[$this->jenis]['label'] ?? $this->jenis;
    }
}
