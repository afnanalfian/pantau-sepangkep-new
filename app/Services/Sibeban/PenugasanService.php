<?php

namespace App\Services\Sibeban;

use App\Models\Sibeban\HonorBerjalan;
use App\Models\Sibeban\HonorBulanan;
use App\Models\Sibeban\Kegiatan;
use App\Models\Sibeban\Mitra;
use App\Models\Sibeban\Penugasan;
use Illuminate\Support\Facades\DB;

/** Operasi tulis: menyimpan, memperbarui, dan melepas penugasan. */
class PenugasanService
{
    /**
     * Simpan/perbarui satu penugasan beserta honor per bulannya.
     * Honor bulan yang tidak ada di $honor akan dihapus.
     */
    public function simpan(Kegiatan $kegiatan, Mitra $mitra, string $jabatan, array $honor): Penugasan
    {
        return DB::transaction(function () use ($kegiatan, $mitra, $jabatan, $honor) {
            $p = Penugasan::updateOrCreate(
                ['kegiatan_id' => $kegiatan->id, 'mitra_id' => $mitra->id],
                ['jabatan' => $jabatan]
            );
            $p->honors()->whereNotIn('bulan', array_keys($honor))->delete();
            foreach ($honor as $bulan => $jumlah) {
                HonorBulanan::updateOrCreate(
                    ['penugasan_id' => $p->id, 'bulan' => $bulan],
                    ['jumlah' => max(0, (int) $jumlah)]
                );
            }

            return $p;
        });
    }

    /**
     * Simpan hasil pratinjau upload:
     *   1. rekap honor berjalan dari Excel → tabel honor_berjalans (menimpa rekap lama untuk bulan itu)
     *   2. penugasan di kegiatan ini dengan honor = honor PPL/PML kegiatan per bulan
     * Mitra yang belum terdaftar dibuat otomatis.
     *
     * @param  array  $rows  [sobat_id, nama, jabatan, berjalan[bulan=>int], honor[bulan=>int]]
     * @return array{baru: int, diperbarui: int, mitra_baru: int}
     */
    public function simpanUpload(Kegiatan $kegiatan, array $rows): array
    {
        $statistik = ['baru' => 0, 'diperbarui' => 0, 'mitra_baru' => 0];
        $sumber = 'Upload kegiatan: '.$kegiatan->nama;

        DB::transaction(function () use ($kegiatan, $rows, $sumber, &$statistik) {
            foreach ($rows as $r) {
                $mitra = Mitra::firstWhere('sobat_id', $r['sobat_id']);
                if (! $mitra) {
                    $mitra = Mitra::create([
                        'sobat_id' => $r['sobat_id'],
                        'nama' => $r['nama'] !== '' ? $r['nama'] : '(nama belum diisi)',
                    ]);
                    $statistik['mitra_baru']++;
                } elseif ($r['nama'] !== '' && str_starts_with($mitra->nama, '(nama')) {
                    $mitra->update(['nama' => $r['nama']]);
                }

                $this->simpanBerjalan($mitra, $kegiatan, $r['berjalan'], $sumber);

                $ada = Penugasan::where('kegiatan_id', $kegiatan->id)->where('mitra_id', $mitra->id)->exists();
                $this->simpan($kegiatan, $mitra, $r['jabatan'], $r['honor']);
                $statistik[$ada ? 'diperbarui' : 'baru']++;
            }
        });

        return $statistik;
    }

    /** Catat honor berjalan mitra per bulan (rekap terbaru menimpa rekap lama). */
    public function simpanBerjalan(Mitra $mitra, ?Kegiatan $kegiatan, array $berjalan, ?string $sumber): void
    {
        foreach ($berjalan as $bulan => $jumlah) {
            HonorBerjalan::rekam($mitra->id, $bulan, (int) $jumlah, $kegiatan?->id, $sumber);
        }
    }

    /** Tambah mitra ke kegiatan dengan honor default jabatan untuk setiap bulan. */
    public function tambahDefault(Kegiatan $kegiatan, Mitra $mitra, string $jabatan): Penugasan
    {
        return $this->simpan($kegiatan, $mitra, $jabatan, array_fill_keys($kegiatan->bulans(), $kegiatan->honorDefault($jabatan)));
    }

    /** Lepas mitra dari kegiatan (hapus penugasan & seluruh honornya). */
    public function lepas(int $penugasanId): ?Penugasan
    {
        $p = Penugasan::with(['kegiatan', 'mitra'])->find($penugasanId);
        $p?->delete();

        return $p;
    }

    /**
     * Setelah periode kegiatan diubah: hapus honor di luar periode
     * dan tambahkan bulan baru dengan honor default jabatan.
     */
    public function sinkronPeriode(Kegiatan $kegiatan): void
    {
        $bulans = $kegiatan->bulans();
        DB::transaction(function () use ($kegiatan, $bulans) {
            foreach ($kegiatan->penugasans()->with('honors')->get() as $p) {
                $p->honors()->whereNotIn('bulan', $bulans)->delete();
                $ada = $p->honors->pluck('bulan')->all();
                foreach (array_diff($bulans, $ada) as $b) {
                    $p->honors()->create(['bulan' => $b, 'jumlah' => $kegiatan->honorDefault($p->jabatan)]);
                }
            }
        });
    }
}
