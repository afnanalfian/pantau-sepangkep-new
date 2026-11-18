<?php

namespace Database\Seeders;

use App\Models\Sibeban\Pengaturan;
use Illuminate\Database\Seeder;

class SibebanSeeder extends Seeder
{
    /**
     * Pengaturan awal SIBEBAN. Nilai yang sudah diatur admin tidak ditimpa.
     *  - limit_bulanan : batas honor per mitra per bulan (bisa diubah admin di menu Limit honor)
     *  - ambang_oranye : persen dari limit untuk status "mendekati limit"
     */
    public function run(): void
    {
        Pengaturan::setNilai('limit_bulanan', Pengaturan::nilai('limit_bulanan') ?? Pengaturan::nilai('limit_default', '3910000'));
        Pengaturan::setNilai('ambang_oranye', Pengaturan::nilai('ambang_oranye', '75'));
    }
}
