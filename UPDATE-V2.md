# Pembaruan PANTAU SEPANGKEP v2

Isi pembaruan:

1. **Tanpa pembobotan poin.** Kinerja tugas = persentase penyelesaian tugas yang deadline-nya jatuh di bulan itu,
   ditambah tunggakan bulan sebelumnya yang belum selesai.
2. **Presensi apel & rapat**, dengan peran baru *Petugas presensi* (ditunjuk admin atau pimpinan).
3. **Matriks kinerja & potongan TK** (tugas, apel, rapat), bisa diekspor ke Excel dan PDF.
4. **Cuti & dinas luar** kini hanya dikelola admin.
5. **Profil** menampilkan matriks bulan berjalan dan riwayatnya (bisa diunduh PDF).
6. **Portal Inovasi**: SICATUR (penomoran surat), SIKU, Kepegawaian, dan SIBEBAN (monitoring honor mitra).
7. Halaman login baru dengan foto kantor, dan tampilan beranda yang diperbarui.

---

## A. Di komputer Anda (repo GitHub)

1. Ekstrak `pantau-v2-update.zip` ke folder repo, lalu **timpa** semua file.
2. Hapus file lama yang sudah tidak dipakai:

   ```bash
   git rm app/Livewire/Admin/Catalog.php \
          app/Livewire/Monitoring/Reports.php \
          app/Services/WorkloadService.php \
          resources/views/livewire/admin/catalog.blade.php \
          resources/views/livewire/monitoring/reports.blade.php \
          resources/views/components/points-bar.blade.php \
          resources/views/components/level.blade.php
   ```

3. Commit & push:

   ```bash
   git add .
   git commit -m "PANTAU v2: matriks TK, presensi, Portal Inovasi"
   git push origin main
   ```

> Kalau tidak memakai `git rm`, hapus ketujuh file itu secara manual lalu `git add -A`.

## B. Di VPS

```bash
cd /var/www/pantau-sepangkep
php artisan down
git pull origin main

composer install --no-dev --optimize-autoloader     # memasang paket PDF (barryvdh/laravel-dompdf)
php artisan migrate --force                         # tabel presensi, surat, inovator, SIBEBAN + kolom peran baru
php artisan db:seed --class=SibebanSeeder --force   # limit honor awal SIBEBAN
php artisan storage:link                            # (sekali saja) untuk foto inovator & avatar

npm ci && npm run build
php artisan optimize
sudo supervisorctl restart pantau-sepangkep-worker:*
php artisan up
```

Opsional, untuk memindahkan data monitoring honor dari aplikasi lama (file matriks Excel):

```bash
php -d memory_limit=1G artisan honor:import-matriks /path/Matrix_Honor_Mitra_BPS_7309_2026.xlsx --tahun=2026
```

Kalau data demo sedang terpasang, buat ulang supaya ikut berisi presensi & riwayat:

```bash
php artisan pantau:demo --hapus
php artisan pantau:demo
```

## C. Setelah pembaruan — atur di aplikasi

| Menu | Yang perlu dilakukan |
|---|---|
| **Admin › Pengaturan** | Periksa bobot matriks. Bawaannya **80 / 20 / 10** (jumlahnya 110), lalu dinormalkan sehingga nilai sempurna tetap 100%. Kalau maksudnya 80 / 10 / 10 atau 70 / 20 / 10, ubah di sini. Atur juga batas potongan (80%), besaran potongan (1% / 2%), dan format nomor surat. |
| **Admin › Pengguna** | Centang **Petugas presensi** untuk pegawai yang mencatat apel/rapat, dan **Keuangan** untuk staf keuangan yang perlu mengunduh matriks TK. Akun yang bukan pegawai (mis. akun admin bawaan) hapus centang **Dihitung sebagai pegawai**. |
| **Presensi › Petugas presensi** | Pimpinan juga bisa menunjuk/mencabut petugas presensi dari sini. |
| **Portal Inovasi › Beranda** | Admin bisa menambahkan foto & nama **Inovator**. |
| `config/portal.php` | Semua tautan luar Portal Inovasi (WBK, PEKPPP, SIKU, dll.). Setelah mengubahnya, jalankan `php artisan optimize`. |

## Aturan perhitungan

- **Penyelesaian tugas bulan M** = tugas yang **sudah diverifikasi selesai** paling lambat akhir bulan M ÷ tugas tanggungan bulan M.
  Tanggungan bulan M = tugas ber-deadline di bulan M, ditambah tugas dari bulan sebelumnya yang belum selesai saat bulan M dimulai.
  Deadline yang diperpanjang (dan disetujui) memakai tanggal yang baru. Tugas yang dibatalkan atau ditolak tidak dihitung.
- **Bulan berjalan**: tugas yang deadline-nya belum tiba belum dihitung (kecuali sudah selesai), sehingga angka sementara tidak langsung memotong.
- **Kehadiran apel/rapat** = hadir ÷ kegiatan yang wajib diikuti. Izin, cuti, dan dinas luar tidak dihitung. Cuti/DL yang dicatat admin otomatis tercatat izin.
- **Nilai akhir** = Σ(bobot × persen) ÷ Σ bobot. Komponen tanpa data (mis. tidak ada rapat sebulan) tidak ikut dihitung.
- **Potongan TK**: nilai 100% → 0% · di atas 80% → 1% · 80% ke bawah → 2%.
