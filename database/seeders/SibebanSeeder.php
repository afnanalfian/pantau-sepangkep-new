<?php

namespace Database\Seeders;

use App\Models\Sibeban\LimitHonor;
use App\Models\Sibeban\Pengaturan;
use Illuminate\Database\Seeder;

class SibebanSeeder extends Seeder
{
    /**
     * Limit honor mitra (nonorganik) per bulan — sesuai sheet LIMIT pada
     * Matrix Honor Mitra 2026 (Perka BPS No. 115/2024 jo. No. 165/2024).
     * PPL = Pendataan lapangan, PML = Pemeriksaan lapangan.
     */
    public function run(): void
    {
        $limits = [
            ['jenis' => 'Survei', 'jabatan' => 'ppl', 'limit' => 3_910_000],
            ['jenis' => 'Survei', 'jabatan' => 'pml', 'limit' => 3_910_000],
            ['jenis' => 'Sensus', 'jabatan' => 'ppl', 'limit' => 4_418_000],
            ['jenis' => 'Sensus', 'jabatan' => 'pml', 'limit' => 4_891_000],
        ];

        foreach ($limits as $l) {
            LimitHonor::updateOrCreate(
                ['jenis' => $l['jenis'], 'jabatan' => $l['jabatan']],
                ['limit' => $l['limit']]
            );
        }

        // Ambang warna: < ambang_oranye % = hijau, ambang_oranye..100 % = oranye, > 100 % = merah
        Pengaturan::setNilai('ambang_oranye', Pengaturan::nilai('ambang_oranye', '75'));
        // Limit cadangan bila jenis/jabatan tidak ditemukan
        Pengaturan::setNilai('limit_default', Pengaturan::nilai('limit_default', '3910000'));
    }
}
