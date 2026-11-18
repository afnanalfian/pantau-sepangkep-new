<?php

namespace App\Models\Sibeban;

use Illuminate\Database\Eloquent\Model;

class Pengaturan extends Model
{
    protected $table = 'sibeban_pengaturans';

    protected $primaryKey = 'kunci';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['kunci', 'nilai'];

    public static function nilai(string $kunci, ?string $default = null): ?string
    {
        return static::query()->where('kunci', $kunci)->value('nilai') ?? $default;
    }

    public static function setNilai(string $kunci, ?string $nilai): void
    {
        static::query()->updateOrCreate(['kunci' => $kunci], ['nilai' => $nilai]);
    }
}
