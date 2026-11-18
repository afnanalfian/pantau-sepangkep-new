<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PortalInovator extends Model
{
    protected $fillable = ['nama', 'inovasi', 'keterangan', 'foto_path', 'urutan'];

    public function fotoUrl(): ?string
    {
        return $this->foto_path ? Storage::disk('public')->url($this->foto_path) : null;
    }
}
