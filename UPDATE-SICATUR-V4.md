# Update SICATUR (26 Nov 2026)

- Form Permintaan: isian Jenis FP dihapus. Format nomor: 001/73090/<tim>/<bulan FP>/<tahun FP>.
  Tanggal yang tampil & dasar nomor = Tanggal FP (bisa tanggal mundur / sisipan).
- Menu baru Surat Pernyataan (isian sama dengan BAPP). Format: SP-001/73090/<klasifikasi>/<tahun>.
- Fitur Ubah diganti Hapus (pembuat nomor atau admin, wajib alasan, tercatat di log).
  Nomor lain tidak berubah; nomor yang dihapus tidak dipakai lagi.
- Semua menu mendukung tanggal mundur / nomor sisipan.
- SK: tombol "Unduh format Excel daftar petugas (Nama, Jabatan)".

## Pasang di VPS
    cd /var/www/pantau-sepangkep
    git pull
    php artisan migrate --force          # tanggal FP lama dipindah ke kolom tanggal
    sudo -u www-data php artisan optimize:clear
    sudo -u www-data php artisan optimize
