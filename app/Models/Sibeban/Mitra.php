<?php

namespace App\Models\Sibeban;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mitra extends Model
{
    protected $table = 'sibeban_mitras';

    protected $fillable = ['sobat_id', 'nama', 'kecamatan', 'desa', 'posisi', 'email'];

    public function penugasans(): HasMany
    {
        return $this->hasMany(Penugasan::class);
    }

    /** 'Desa X, Kec. Y' */
    public function wilayah(): string
    {
        return trim(implode(', ', array_filter([$this->desa, $this->kecamatan ? 'Kec. '.$this->kecamatan : null])));
    }

    public function honorBerjalans(): HasMany
    {
        return $this->hasMany(HonorBerjalan::class);
    }
}
