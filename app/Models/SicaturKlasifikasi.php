<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SicaturKlasifikasi extends Model
{
    protected $fillable = ['kode', 'uraian', 'sumber', 'urutan'];
}
