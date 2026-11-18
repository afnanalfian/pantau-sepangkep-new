<?php

namespace App\Services\Sibeban;

use App\Models\Sibeban\HonorBulanan;
use App\Models\Sibeban\Kegiatan;
use App\Models\Sibeban\Penugasan;
use Illuminate\Support\Facades\DB;

/** Operasi tulis petugas kegiatan: memilih, melepas, dan menyimpan jabatan & honor. */
class PenugasanService
{
    /** Tambahkan mitra terpilih ke kegiatan (jabatan & honor diisi kemudian lewat template). */
    public function pilih(Kegiatan $kegiatan, array $mitraIds): int
    {
        $sudah = $kegiatan->penugasans()->pluck('mitra_id')->all();
        $baru = array_values(array_diff(array_map('intval', $mitraIds), $sudah));
        foreach ($baru as $id) {
            Penugasan::create(['kegiatan_id' => $kegiatan->id, 'mitra_id' => $id]);
        }

        return count($baru);
    }

    public function lepas(Kegiatan $kegiatan, int $penugasanId): ?Penugasan
    {
        $p = Penugasan::with('mitra')->where('kegiatan_id', $kegiatan->id)->find($penugasanId);
        $p?->delete();

        return $p;
    }

    /**
     * Simpan jabatan & honor per bulan satu petugas. Bulan di luar periode kegiatan dihapus.
     *
     * @param  array<string,int>  $honor  [bulan => jumlah]
     */
    public function simpan(Kegiatan $kegiatan, int $mitraId, ?string $jabatan, array $honor): Penugasan
    {
        return DB::transaction(function () use ($kegiatan, $mitraId, $jabatan, $honor) {
            $p = Penugasan::updateOrCreate(
                ['kegiatan_id' => $kegiatan->id, 'mitra_id' => $mitraId],
                ['jabatan' => $jabatan !== null && trim($jabatan) !== '' ? trim($jabatan) : null]
            );
            $bulans = $kegiatan->bulans();
            $p->honors()->whereNotIn('bulan', $bulans)->delete();
            foreach ($bulans as $b) {
                $jml = max(0, (int) ($honor[$b] ?? 0));
                if ($jml > 0) {
                    HonorBulanan::updateOrCreate(['penugasan_id' => $p->id, 'bulan' => $b], ['jumlah' => $jml]);
                } else {
                    $p->honors()->where('bulan', $b)->delete();
                }
            }

            return $p;
        });
    }

    /**
     * Simpan hasil impor template.
     *
     * @param  array  $rows  [mitra_id, jabatan, honor[bulan=>int]]
     * @return array{baru:int, diperbarui:int}
     */
    public function simpanImpor(Kegiatan $kegiatan, array $rows): array
    {
        $stat = ['baru' => 0, 'diperbarui' => 0];
        DB::transaction(function () use ($kegiatan, $rows, &$stat) {
            $sudah = $kegiatan->penugasans()->pluck('mitra_id')->flip();
            foreach ($rows as $r) {
                $this->simpan($kegiatan, (int) $r['mitra_id'], $r['jabatan'], $r['honor']);
                $stat[isset($sudah[$r['mitra_id']]) ? 'diperbarui' : 'baru']++;
            }
        });

        return $stat;
    }

    /** Setelah periode kegiatan diubah: buang honor di luar periode. */
    public function sinkronPeriode(Kegiatan $kegiatan): void
    {
        HonorBulanan::query()
            ->whereIn('penugasan_id', $kegiatan->penugasans()->select('id'))
            ->whereNotIn('bulan', $kegiatan->bulans())
            ->delete();
    }
}
