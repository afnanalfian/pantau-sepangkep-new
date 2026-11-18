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
        'jenis', 'tahun', 'nomor_urut', 'nomor_surat', 'tanggal', 'sifat', 'klasifikasi', 'perihal',
        'pihak', 'nomor_asal', 'keterangan', 'file_path', 'file_name', 'link', 'user_id', 'pembuat',
    ];

    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }

    public const SIFAT = ['B' => 'Biasa', 'P' => 'Penting', 'T' => 'Terbatas', 'R' => 'Rahasia'];

    /** Kode klasifikasi arsip yang umum dipakai (bisa diketik bebas). */
    public const KLASIFIKASI = [
        'KP.320' => 'Kepegawaian – pengembangan pegawai',
        'KP.650' => 'Kepegawaian – cuti',
        'KU.010' => 'Keuangan – pelaksanaan anggaran',
        'KU.100' => 'Keuangan – SPJ',
        'HM.340' => 'Hubungan masyarakat – publikasi',
        'PL.200' => 'Perlengkapan – BMN',
        'PR.300' => 'Perencanaan – rapat koordinasi',
        'SS.100' => 'Statistik sosial',
        'PS.100' => 'Statistik produksi',
        'DS.100' => 'Statistik distribusi',
        'NW.100' => 'Neraca wilayah',
        'TI.100' => 'Teknologi informasi',
        'UM.100' => 'Umum',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** Ambil nomor urut berikutnya & rangkai nomor surat sesuai format di pengaturan. */
    public static function buatNomor(string $jenis, Carbon $tanggal, ?string $sifat, ?string $klasifikasi): array
    {
        $tahun = (int) $tanggal->format('Y');
        $urut = (int) static::withTrashed()->where('jenis', $jenis)->where('tahun', $tahun)->max('nomor_urut') + 1;
        $romawi = ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][(int) $tanggal->format('n')];

        $format = $jenis === 'keluar'
            ? (string) Settings::get('format_surat_keluar', '{sifat}-{nomor}/73090/{klasifikasi}/{tahun}')
            : (string) Settings::get('format_surat_masuk', 'SM-{nomor}/{tahun}');

        $nomor = strtr($format, [
            '{sifat}' => $sifat ?: 'B',
            '{nomor}' => str_pad((string) $urut, 3, '0', STR_PAD_LEFT),
            '{klasifikasi}' => $klasifikasi ?: 'UM.100',
            '{bulan}' => $tanggal->format('m'),
            '{bulan_romawi}' => $romawi,
            '{tahun}' => (string) $tahun,
        ]);

        return [$tahun, $urut, $nomor];
    }
}
