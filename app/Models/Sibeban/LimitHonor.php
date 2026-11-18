<?php

namespace App\Models\Sibeban;

use Illuminate\Database\Eloquent\Model;

class LimitHonor extends Model
{
    protected $table = 'sibeban_limit_honors';

    protected $fillable = ['jenis', 'jabatan', 'limit'];

    protected $casts = ['limit' => 'integer'];
}
