<?php

namespace App\Models;

use App\Support\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Surat extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'jenis', 'tahun', 'nomor_urut', 'nomor_surat', 'tanggal', 'tanggal_mulai', 'tanggal_selesai', 'sifat', 'klasifikasi', 'perihal',
        'pihak', 'tempat', 'pegawai', 'nomor_asal', 'keterangan', 'file_path', 'file_name', 'link', 'user_id', 'pembuat',
    ];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'tanggal_mulai' => 'date', 'tanggal_selesai' => 'date', 'pegawai' => 'array'];
    }

    public const SIFAT = ['B' => 'Biasa', 'P' => 'Penting', 'T' => 'Terbatas', 'R' => 'Rahasia'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** Definisi semua jenis nomor (config/sicatur.php) dengan format yang sudah diubah admin. */
    public static function jenisList(): array
    {
        $list = [];
        foreach (config('sicatur.jenis') as $kode => $j) {
            $j['format'] = (string) (Settings::get('sicatur_format_'.$kode) ?: $j['format']);
            $j['kode'] = $kode;
            $list[$kode] = $j;
        }

        return $list;
    }

    public static function jenis(string $kode): ?array
    {
        return self::jenisList()[$kode] ?? null;
    }

    public static function labelJenis(string $kode): string
    {
        return config("sicatur.jenis.$kode.singkat") ?? config("sicatur.lama.$kode") ?? strtoupper($kode);
    }

    /** Nomor terakhir yang sudah terbit di luar aplikasi (diisi admin) untuk jenis & tahun tsb. */
    public static function nomorAwal(string $kode, int $tahun): int
    {
        return (int) Settings::get("sicatur_mulai_{$kode}_{$tahun}", 0);
    }

    /** Nomor urut berikutnya & nomor lengkap sesuai format jenis. */
    public static function buatNomor(string $kode, Carbon $tanggal, ?string $klasifikasi, ?string $sifat = 'B'): array
    {
        $j = self::jenis($kode) ?? ['format' => '{nomor}/{tahun}', 'digit' => 3, 'klasifikasi' => null];
        $tahun = (int) $tanggal->format('Y');
        $terakhir = max((int) static::withTrashed()->where('jenis', $kode)->where('tahun', $tahun)->max('nomor_urut'), self::nomorAwal($kode, $tahun));
        $urut = $terakhir + 1;

        return [$tahun, $urut, self::rangkai($j, $urut, $tanggal, $klasifikasi, $sifat)];
    }

    public static function rangkai(array $j, int $urut, Carbon $tanggal, ?string $klasifikasi, ?string $sifat = 'B'): string
    {
        $romawi = ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][(int) $tanggal->format('n')];

        return strtr($j['format'], [
            '{nomor}' => (int) $j['digit'] > 0 ? str_pad((string) $urut, (int) $j['digit'], '0', STR_PAD_LEFT) : (string) $urut,
            '{klasifikasi}' => $klasifikasi ?: ($j['klasifikasi'] ?? ''),
            '{sifat}' => $sifat ?: 'B',
            '{hari}' => $tanggal->format('d'),
            '{bulan}' => $tanggal->format('m'),
            '{bulan_romawi}' => $romawi,
            '{tahun}' => (string) $tanggal->format('Y'),
        ]);
    }
}
