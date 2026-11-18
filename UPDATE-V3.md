# PANTAU SEPANGKEP — Pembaruan v3 (SICATUR & SIBEBAN)

Paket `pantau-v3-lengkap.zip` **sudah termasuk semua isi v2 dan v2.1**. Cukup timpa sekali.

## 1. Timpa file (Windows)

```powershell
Expand-Archive -Path "$env:USERPROFILE\Downloads\pantau-v3-lengkap.zip" -DestinationPath "C:\wamp64\www\pantau-sepangkep-new" -Force
cd C:\wamp64\www\pantau-sepangkep-new
```

## 2. Hapus file lama yang tidak dipakai lagi

Aman diulang. File yang sudah tidak ada akan dilewati.

```powershell
Remove-Item -ErrorAction SilentlyContinue `
  app\Livewire\Admin\Catalog.php, app\Livewire\Monitoring\Reports.php, app\Services\WorkloadService.php, `
  resources\views\livewire\admin\catalog.blade.php, resources\views\livewire\monitoring\reports.blade.php, `
  resources\views\components\points-bar.blade.php, resources\views\components\level.blade.php, `
  app\Models\Sibeban\LimitHonor.php, app\Services\Sibeban\PenugasanExcelReader.php, `
  resources\views\livewire\sibeban\partials\badge.blade.php, resources\views\livewire\sibeban\partials\legenda.blade.php, `
  resources\views\livewire\sibeban\kegiatan\partials\kegiatan-lain.blade.php
```

## 3. Kirim ke GitHub

```powershell
git add -A
git commit -m "PANTAU v3: SICATUR lengkap, SIBEBAN limit bulanan"
git push
```

## 4. Di VPS

```bash
cd /var/www/pantau-sepangkep
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --class=SibebanSeeder --force
npm run build
php artisan optimize:clear
php artisan optimize
sudo chown -R $USER:www-data storage bootstrap/cache
sudo systemctl reload php8.3-fpm
php artisan queue:restart
```

Migrasi v3 hanya **menambah kolom/tabel**. Data lama tidak hilang.

---

## Yang perlu dilakukan admin setelah update

### SIBEBAN
1. **Limit honor**: buka *Portal Inovasi › SIBEBAN › Limit honor*. Isi limit per mitra **per bulan**. Nilai awalnya Rp 3.910.000.
2. **Wilayah mitra**: buka *Database mitra*, klik **Ekspor**, lalu isi kolom **Kecamatan** dan **Desa/Kelurahan**. Setelah itu klik **Impor Excel**. Mitra yang Sobat ID-nya sama akan diperbarui, tidak dobel.

### SICATUR
1. Buka *SICATUR › Pengaturan*.
   - **Nomor terakhir**: isi nomor terakhir tahun ini dari SICATUR lama. Contoh: Surat Tugas 18, Form Permintaan 15, SK 19, BAST 17. Nomor berikutnya melanjutkan dari angka itu.
   - **Format nomor**: cek formatnya, terutama **Form Permintaan**. Bawaannya `{nomor}/73090/{klasifikasi}/{hari}/{bulan}/{tahun}`. Contoh di SICATUR lama tertulis `015/73130/KU.320/04/01/2026`, jadi sesuaikan bila perlu.
   - **Buku pedoman**: unggah PDF atau isi tautannya.
2. (Opsional) **Impor riwayat lama** lewat template di halaman yang sama, supaya semua nomor lama ikut tercatat.
3. **Link Surat**: tambahkan tautan template/dokumen di tab *Link Surat*.

## Ringkasan perubahan

**SIBEBAN**
- Limit honor dihitung **per bulan**, satu angka yang bisa diatur admin. Halaman utama sekarang *Ketersediaan mitra*: daftar mitra yang honornya bulan ini masih di bawah limit, dengan filter kecamatan, desa, dan posisi. Bisa diekspor ke Excel.
- Semua angka rupiah ditulis lengkap, misalnya Rp 3.910.000.
- Kegiatan tidak lagi punya isian honor.
- Alur kegiatan:
  1. Pilih petugas (filter kecamatan/desa, mitra yang limitnya penuh disembunyikan).
  2. Unduh template. Sobat ID, nama, kecamatan, dan desa sudah terisi.
  3. Isi jabatan dan honor per bulan.
  4. Impor, cek pratinjau status limit, lalu simpan.
- Database mitra punya kolom Kecamatan dan Desa/Kelurahan. **Hanya admin** yang bisa tambah, ubah, hapus, dan impor mitra.
- Pegawai bisa membuat kegiatan dan mengelola petugas di kegiatan buatannya sendiri. Admin bisa mengelola semua kegiatan.

**SICATUR**
- Enam jenis nomor, masing-masing dengan form dan nomor urutnya sendiri:
  - Surat Tugas/Perintah (bisa memilih pegawai yang ditugaskan, tanggal pelaksanaan, dan tempat)
  - Form Permintaan
  - SK
  - BAST
  - BAPP
  - BA
- Tab Beranda menampilkan kartu tiap jenis beserta nomor terakhirnya. Tab lain: Riwayat (cari, filter, ekspor Excel), Link Surat, dan Pengaturan (admin).
- Tombol **Nomor Surat Keluar (SRIKANDI)** dan **Buku Pedoman** tetap tersedia.
