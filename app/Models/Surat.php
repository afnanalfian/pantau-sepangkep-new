<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Surat extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'jenis', 'tahun', 'grup', 'nomor_urut', 'sisip', 'no_urut', 'nomor_surat', 'lengkap', 'tanggal', 'sifat', 'klasifikasi',
        'perihal', 'tujuan', 'tim_teknis', 'jenis_fp', 'tanggal_fp', 'nama_petugas', 'honor', 'tanggal_mulai', 'tanggal_selesai',
        'link_surat', 'status', 'link_oleh', 'link_at', 'pihak', 'tempat', 'pegawai', 'nomor_asal', 'keterangan',
        'file_path', 'file_name', 'link', 'user_id', 'pembuat',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date', 'tanggal_mulai' => 'date', 'tanggal_selesai' => 'date', 'tanggal_fp' => 'date',
            'link_at' => 'datetime', 'pegawai' => 'array', 'lengkap' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function logs(): HasMany
    {
        return $this->hasMany(SuratLog::class)->latest();
    }

    public static function cfg(string $jenis): ?array
    {
        return config("sicatur.jenis.$jenis");
    }

    public static function labelJenis(string $jenis): string
    {
        return config("sicatur.jenis.$jenis.label") ?? config("sicatur.lama.$jenis") ?? strtoupper($jenis);
    }

    /** Nomor untuk ditampilkan; baris lama yang Format Nomor-nya belum tersusun memakai No Urut. */
    public function nomorTampil(): string
    {
        return $this->lengkap && $this->nomor_surat !== '' ? $this->nomor_surat : 'No Urut '.($this->no_urut ?: $this->nomor_urut).' (data belum lengkap)';
    }

    /** Nilai sebuah isian form sebagai teks (Y-m-d untuk tanggal). */
    public function nilai(string $attr): string
    {
        $v = $this->{$attr};
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d');
        }

        return (string) ($v ?? '');
    }

    /** Status SK: menunggu | selesai | lama (baris lama tanpa status). */
    public function kunciStatus(): string
    {
        return match ($this->status) {
            config('sicatur.lengkapi.selesai') => 'selesai',
            config('sicatur.lengkapi.proses') => 'menunggu',
            default => 'lama',
        };
    }
}
