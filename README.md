# PANTAU SEPANGKEP

**Sistem Pemantauan Beban Kerja & Kinerja Pegawai – BPS Kabupaten Pangkajene dan Kepulauan**

Dibangun dengan TALL stack: **Tailwind CSS 4 · Alpine.js · Laravel 13 · Livewire 4**.
Panduan pemasangan ke VPS: **[INSTALL.md](INSTALL.md)**.

## Fitur

**Tugas & alur kerja**
- Penugasan bertingkat: Pimpinan → Ketua Tim → Anggota, termasuk **Teruskan ke anggota** (sub-tugas).
- Disposisi langsung pimpinan ke pegawai mana pun, dengan **tembusan otomatis** ke ketua timnya.
- **Pengajuan tugas mandiri** (bottom-up) yang disetujui ketua tim / pimpinan.
- Papan **Kanban drag & drop** (Belum dikerjakan → Sedang dikerjakan → Perlu revisi → Menunggu verifikasi → Selesai) + tampilan daftar & kalender.
- **Wajib bukti dukung**: “Tandai selesai” hanya bisa jika sudah ada output (file, gambar, atau link).
- Verifikasi berjenjang, catatan revisi, tolak/setujui pengajuan, pembatalan, buka kembali.
- Pemisahan tugas (segregation of duties): tidak bisa memverifikasi tugas sendiri; tugas ketua tim untuk dirinya sendiri diverifikasi pimpinan.
- **Pengalihan verifikasi saat cuti / dinas luar** ke pimpinan, dengan pemberitahuan ke anggota.
- **SLA verifikasi 2×24 jam kerja** → eskalasi ke pimpinan atau setujui otomatis (dibekukan saat verifikator cuti) + pencatatan pelanggaran SLA ketua tim.
- Pengingat H-1 deadline, tugas terlambat (poin dibekukan), dan **pengajuan perpanjangan waktu**.
- Diskusi/komentar per tugas & **audit trail** lengkap.

**Lampiran**
- Per tugas: Surat Tugas, Dokumen Pendukung, Gambar/Dokumentasi, dan Output.
- Unggah banyak file sekaligus, **unggah satu folder**, seret-lepas file, atau tambah **link**.
- Tersimpan otomatis ke **Google Drive**: `PANTAU SEPANGKEP / Pegawai / Bulan Tahun / Tugas / Kategori`.

**Beban kerja & kinerja**
- Poin = katalog kegiatan × volume, atau jam kerja × koefisien kompleksitas (1,0 / 1,2 / 1,5); bobot dikunci admin.
- Target bulanan **prorata cuti/DL**; bar capaian (terverifikasi / berjalan / target) dan proyeksi akhir bulan.
- Heatmap beban kerja (Underload · Cukup · Optimal · Overload), badge keaktifan (Aktif/Pasif/Dorman), tombol **Beri tugas langsung**.
- Laporan bulanan: capaian, komponen kinerja tukin (maks 70%), ketepatan waktu, **disparitas beban**, kinerja manajerial ketua tim. Ekspor **Excel**.
- **Portofolio KIPPAPP** per bulan/triwulan (butir RHK + bukti dukung) – sekali klik.
- Peringatan dini pegawai pasif & *Yellow Alert* progres mingguan.

**Notifikasi**
- Lonceng di web (real-time polling) + **Web Push ke HP/desktop** (PWA, bisa “Tambah ke Layar Utama”).
- Pengguna tetap login sampai menekan Keluar.

**Administrasi**
- Hanya akun `admin` di awal; **import akun dari Excel** (`username`, `password`), wajib ganti password saat login pertama.
- **Import biodata** (`username`, `nama`, `nip`, `jabatan`) dengan template yang bisa diunduh.
- Tambah, ubah, reset password, nonaktifkan (langsung dikeluarkan dari semua perangkat), hapus akun.
- Peran sistem (Administrator, Pimpinan) terpisah dari jabatan; satu akun bisa multi-peran & multi-tim.
- **Kelola Tim drag & drop**: 6 tim utama + tim tambahan, mode pindah/salin, tetapkan ketua dengan ★.
- Katalog kegiatan (poin, satuan, kompleksitas, butir RHK), pengaturan target/SLA, status Google Drive & antrean.

## Pengembangan lokal

```bash
composer install
cp .env.example .env && php artisan key:generate
# untuk lokal: DB_CONNECTION=sqlite, APP_ENV=local, APP_DEBUG=true, SESSION_SECURE_COOKIE=false
touch database/database.sqlite
php artisan migrate --seed
php artisan pantau:vapid --write
npm install && npm run dev
php artisan serve          # terminal lain: php artisan queue:work
```

Login awal: `admin` / nilai `ADMIN_PASSWORD` (default `admin12345`).
