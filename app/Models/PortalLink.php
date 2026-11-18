<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortalLink extends Model
{
    protected $fillable = ['grup', 'judul', 'url', 'keterangan', 'urutan'];
}
