<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresensiKehadiran extends Model
{
    protected $fillable = ['presensi_kegiatan_id', 'user_id', 'status', 'catatan', 'dicatat_oleh'];

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(PresensiKegiatan::class, 'presensi_kegiatan_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
