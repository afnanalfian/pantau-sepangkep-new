# PANTAU SEPANGKEP — Pembaruan v3.1 (SICATUR sama dengan aplikasi Apps Script)

Paket `pantau-v3.1-lengkap.zip` **sudah termasuk v2, v2.1, dan v3**. Cukup timpa sekali.

## 1. Timpa file (Windows)

```powershell
Expand-Archive -Path "$env:USERPROFILE\Downloads\pantau-v3.1-lengkap.zip" -DestinationPath "C:\wamp64\www\pantau-sepangkep-new" -Force
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
  resources\views\livewire\sibeban\kegiatan\partials\kegiatan-lain.blade.php, `
  app\Models\PortalLink.php, resources\views\livewire\portal\partials\sicatur-isian.blade.php, UPDATE-V3.md
```

## 3. Kirim ke GitHub, lalu jalankan di VPS

```powershell
git add -A
git commit -m "PANTAU v3.1: SICATUR sama dengan aplikasi Apps Script"
git push
```

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

## 4. Pindahkan data SICATUR lama (admin, sekali saja)

1. Buka Google Sheets SICATUR lama, lalu pilih **File › Download › Microsoft Excel (.xlsx)**.
2. Di PANTAU, buka **Portal Inovasi › SICATUR › Pengaturan › Impor dari spreadsheet SICATUR lama**, lalu unggah file tadi.

Yang ikut diimpor:
- **Kode klasifikasi** dari sheet *Klas Teknis* dan *Klas Fasilitatif*. Tanpa daftar ini, dropdown cari klasifikasi kosong.
- **Semua nomor** dari sheet Surat Tugas/Perintah, Form Permintaan, SK, BAST, BAPP, dan BA, beserta isiannya, status SK, dan link surat.

Setelah diimpor, nomor berikutnya otomatis melanjutkan dari data lama. Impor aman diulang karena nomor yang sama hanya diperbarui, tidak dobel. Sebaiknya impor ulang tepat sebelum SICATUR lama berhenti dipakai.

Di halaman yang sama ada pengaturan **Buku Pedoman** (bawaannya tautan Drive yang lama) dan **webhook SK** (opsional).

## Yang sama dengan aplikasi lama

- **Tab:** Beranda · Surat Tugas · Form Permintaan · SK · BAST · BAPP · BA · Riwayat · Link Surat (angka = jumlah SK yang menunggu link).
- **Isian tiap form** urutannya sama dengan `Config.gs`, dan semuanya wajib diisi. Tombol "Terbitkan nomor …" baru aktif kalau isian lengkap. Di bawah tombol muncul "Belum lengkap: …".
- **Kode klasifikasi:** dropdown cari dari Klas Teknis dan Klas Fasilitatif. Bisa diketik kode atau kata kunci, dan harus dipilih dari daftar.
- **Pembuat:** dipilih dari daftar pegawai. Nama Anda otomatis terpilih, dan pilihan ini terbawa ke tab lain.
- **Tanggal:**
  - Tanggal setelah hari ini ditolak.
  - Tanggal sebelum hari ini wajib dicentang **Tanggal mundur**. Kalau sudah ada nomor yang lebih baru, nomornya menjadi **sisipan** (mis. B-003.1/…).
- **Format nomor**, sama seperti `Nomor.gs`:
  - Surat Tugas: `B-001/73090/{klasifikasi}/{tahun}`
  - Form Permintaan: `001/73090/{KU.xxx dari Jenis FP}/{kode tim}/{bulan}/{tahun}`. Nomor urut **dimulai ulang tiap bulan per tim teknis**.
  - SK: `NOMOR 1 TAHUN 2026`
  - BAST, BAPP, BA: `BAST-001/73090/{klasifikasi}/{tahun}`
- **Setelah nomor terbit** ada tombol Salin nomor dan Ubah isian. Khusus SK ada juga *Salin tautan untuk pengunggah*.
- **Daftar "Nomor terbaru (N tercatat)"** menampilkan 8 nomor terakhir dengan tombol Salin dan Ubah.
- **Ubah data nomor:**
  - Nomor urut tetap.
  - Tim Teknis pada Form Permintaan dikunci.
  - Tanggal hanya bisa diubah di antara tanggal nomor sebelum dan sesudahnya.
  - Kalau nomornya ikut berubah, muncul konfirmasi *"Nomor akan berubah dari … menjadi …"*.
  - Setiap perubahan tercatat di log (bisa dilihat di jendela Ubah). "Diubah oleh" otomatis memakai nama akun yang sedang masuk.
- **Riwayat:** cari dengan kata kunci, jenis surat, dan rentang tanggal, 20 per halaman. Ada tambahan unduh Excel.
- **Link Surat (SK):**
  - Daftar bisa disaring: Menunggu link, Sudah ada link, atau Semua.
  - Tombol *Isi link* atau *Lihat / ganti link* membuka jendela berisi rincian SK.
  - Setelah link disimpan, status otomatis berubah dari **Proses** menjadi **Selesai**.

## Perbedaan kecil yang disengaja

- **"Diubah oleh" dan "Diunggah oleh"** otomatis memakai akun yang sedang masuk, tidak dipilih manual. Dengan begitu log selalu mencatat orang yang benar.
- **Tombol hapus tidak ada** (sama seperti aplikasi lama). Nomor yang salah cukup diubah isiannya.
- **Webhook SK:** format JSON-nya saya rancang sendiri, karena kode pengirim webhook tidak ada di file yang dikirim.
