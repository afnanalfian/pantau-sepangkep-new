<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuratLog extends Model
{
    protected $fillable = ['surat_id', 'kolom', 'nilai_lama', 'nilai_baru', 'user_id', 'oleh', 'alasan'];
}
