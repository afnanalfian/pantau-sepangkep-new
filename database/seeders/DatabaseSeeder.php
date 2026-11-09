<?php

namespace Database\Seeders;

use App\Models\ActivityCatalog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Data awal: hanya 1 akun admin + 6 tim utama + katalog kegiatan standar (PRD).
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

        if (ActivityCatalog::count() === 0) {
            $catalog = [
                // Pranata Komputer
                ['Pranata Komputer', 'Troubleshooting perangkat / jaringan', 'tiket', 1, 'rendah'],
                ['Pranata Komputer', 'Backup basis data & pemeliharaan server', 'siklus', 2.5, 'sedang'],
                ['Pranata Komputer', 'Pengolahan peta digital / wilkerstat di SIG', 'kecamatan', 5, 'sedang'],
                ['Pranata Komputer', 'Tata letak & visualisasi publikasi daerah', 'buku', 10, 'sedang'],
                ['Pranata Komputer', 'Pembuatan script otomasi / fitur sistem baru', 'fitur teruji', 25, 'tinggi'],
                // Statistisi
                ['Statistisi', 'Pemeriksaan kelengkapan dokumen survei', 'paket 20 kuesioner', 2, 'rendah'],
                ['Statistisi', 'Supervisi lapangan / uji petik data', 'SLS', 3.5, 'sedang'],
                ['Statistisi', 'Validasi anomali data & analisis agregat', 'subsektor', 5, 'sedang'],
                ['Statistisi', 'Penyusunan naskah analisis / rilis data', 'naskah', 18, 'tinggi'],
                // Keuangan
                ['Keuangan', 'Penatausahaan kuitansi & kelengkapan SPJ', 'paket 5 berkas', 2, 'rendah'],
                ['Keuangan', 'Verifikasi kepatuhan anggaran & perpajakan', 'kegiatan', 5, 'sedang'],
                ['Keuangan', 'Input transaksi aplikasi perbendaharaan negara', 'paket', 2.5, 'rendah'],
                ['Keuangan', 'Rekonsiliasi rekening bank & LPJ bulanan', 'laporan', 15, 'tinggi'],
                // Tata Usaha
                ['Tata Usaha', 'Registrasi, agenda & distribusi surat dinas', 'paket 10 surat', 1, 'rendah'],
                ['Tata Usaha', 'Penyusunan draf surat dinas / nota dinas', 'surat', 1.5, 'rendah'],
                ['Tata Usaha', 'Inventarisasi & penatausahaan aset BMN', 'ruangan', 5, 'sedang'],
                ['Tata Usaha', 'Pengelolaan berkas usulan kepegawaian', 'berkas', 3.5, 'sedang'],
                ['Tata Usaha', 'Notulensi, presensi & logistik rapat', 'kegiatan', 3, 'rendah'],
                // Umum
                ['Umum', 'Rapat / koordinasi', 'jam', 1, 'rendah'],
                ['Umum', 'Pelatihan / bimbingan teknis (peserta)', 'hari', 6, 'rendah'],
                ['Umum', 'Kepanitiaan kegiatan kantor', 'kegiatan', 4, 'sedang'],
            ];
            foreach ($catalog as [$rumpun, $name, $unit, $points, $cx]) {
                ActivityCatalog::create(compact('rumpun', 'name', 'unit', 'points') + ['complexity' => $cx]);
            }
        }
    }
}
