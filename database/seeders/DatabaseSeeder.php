<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Data awal: hanya 1 akun admin + 6 tim utama + limit honor SIBEBAN.
     * Akun pegawai lain dibuat lewat menu Admin › Pengguna › Import Akun (Excel).
     */
    public function run(): void
    {
        User::firstOrCreate(['username' => 'admin'], [
            'name' => 'Administrator',
            'password' => env('ADMIN_PASSWORD', 'admin12345'),
            'is_admin' => true,
            'is_active' => true,
            'must_change_password' => true,
            'is_pegawai' => false,
        ]);

        $teams = [
            ['Tim Distribusi', 'blue', 'Statistik distribusi, harga, dan perdagangan.'],
            ['Tim Neraca', 'orange', 'Neraca wilayah dan analisis statistik.'],
            ['Tim Produksi', 'green', 'Statistik pertanian, industri, pertambangan, dan energi.'],
            ['Tim Sosial', 'purple', 'Statistik kependudukan, kesejahteraan rakyat, dan ketahanan sosial.'],
            ['Tim IPDS', 'navy', 'Integrasi Pengolahan dan Diseminasi Statistik.'],
            ['Tim Umum', 'teal', 'Kesekretariatan, keuangan, kepegawaian, dan perlengkapan.'],
        ];
        foreach ($teams as $i => [$name, $color, $desc]) {
            Team::firstOrCreate(['slug' => \Illuminate\Support\Str::slug($name)], [
                'name' => $name, 'color' => $color, 'description' => $desc, 'is_core' => true, 'sort_order' => $i + 1,
            ]);
        }

        // Limit honor awal SIBEBAN (Portal Inovasi)
        $this->call(SibebanSeeder::class);
    }
}
