<?php

namespace App\Models\Sibeban;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Honor berjalan (rekap Excel) seorang mitra pada satu bulan. */
class HonorBerjalan extends Model
{
    protected $table = 'sibeban_honor_berjalans';

    protected $fillable = ['mitra_id', 'bulan', 'jumlah', 'kegiatan_id', 'sumber'];

    protected $casts = ['jumlah' => 'integer'];

    public function mitra(): BelongsTo
    {
        return $this->belongsTo(Mitra::class);
    }

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class);
    }

    /**
     * Simpan rekap; timestamp selalu diperbarui walau nilainya sama,
     * karena updated_at menandai "per kapan" rekap ini berlaku.
     */
    public static function rekam(int $mitraId, string $bulan, int $jumlah, ?int $kegiatanId, ?string $sumber): self
    {
        $r = static::updateOrCreate(
            ['mitra_id' => $mitraId, 'bulan' => $bulan],
            ['jumlah' => max(0, $jumlah), 'kegiatan_id' => $kegiatanId, 'sumber' => $sumber]
        );
        if (! $r->wasRecentlyCreated && ! $r->wasChanged()) {
            $r->touch();
        }

        return $r;
    }
}
