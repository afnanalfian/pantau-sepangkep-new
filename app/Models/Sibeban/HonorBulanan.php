<?php

namespace App\Models\Sibeban;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HonorBulanan extends Model
{
    protected $table = 'sibeban_honor_bulanans';

    protected $fillable = ['penugasan_id', 'bulan', 'jumlah'];

    protected $casts = ['jumlah' => 'integer'];

    public function penugasan(): BelongsTo
    {
        return $this->belongsTo(Penugasan::class);
    }
}
