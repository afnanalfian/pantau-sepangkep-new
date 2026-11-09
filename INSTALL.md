# Panduan Instalasi PANTAU SEPANGKEP di VPS

Panduan ini mengganti project TALL stack lama di VPS dan domain yang sudah ada dengan **PANTAU SEPANGKEP**.
Project lama **tidak dihapus**: kode baru dipasang di folder terpisah, lalu web server dialihkan ke sana.
Bila ada masalah, cukup arahkan kembali ke folder lama (lihat bagian *Rollback*).

> Contoh di bawah memakai Ubuntu 22.04/24.04, Nginx, PHP-FPM 8.3, MySQL/MariaDB.
> Ganti `pantau.domainanda.go.id` dengan domain Anda, dan `/var/www/project-lama` dengan lokasi project lama.

---

## 0. Ringkasan teknologi

| Komponen | Versi |
|---|---|
| PHP | **8.3 atau lebih baru** (wajib, Laravel 13) |
| Laravel | 13 |
| Livewire | 4 (Alpine.js sudah termasuk) |
| Tailwind CSS | 4 (via Vite) |
| Database | MySQL 8 / MariaDB 10.6+ |
| Node.js | 20.19+ atau 22 (hanya untuk build aset) |

Fitur yang butuh layanan latar belakang:

- **Queue worker** (supervisor) → upload file ke Google Drive & tugas berat lain.
- **Scheduler** (cron tiap menit) → pengingat deadline, eskalasi SLA verifikasi, peringatan dini.
- **HTTPS** → wajib agar notifikasi push ke HP bisa jalan.
git push -u origin tall
---

## 1. Backup project lama

```bash
# Backup database lama (ganti nama db & user)
mysqldump -u root -p nama_db_lama > ~/backup_db_lama_$(date +%F).sql

# Backup folder project lama
sudo tar czf ~/backup_project_lama_$(date +%F).tar.gz -C /var/www project-lama

# Catat konfigurasi Nginx yang aktif
ls -l /etc/nginx/sites-enabled/
sudo cat /etc/nginx/sites-enabled/NAMA_FILE_SITE_LAMA
```

---

## 2. Cek & lengkapi paket server

```bash
php -v          # harus 8.3.x atau lebih baru
composer -V     # Composer 2.x
node -v         # 20.19+ / 22.x
```

Jika PHP masih 8.2 atau lebih rendah, pasang PHP 8.3 (paralel, tidak mengganggu project lain):

```bash
sudo add-apt-repository ppa:ondrej/php -y && sudo apt update
sudo apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl \
    php8.3-zip php8.3-gd php8.3-intl php8.3-bcmath php8.3-gmp php8.3-sqlite3 unzip
```

Jika PHP sudah 8.3, pastikan ekstensi berikut ada:

```bash
sudo apt install -y php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-gd php8.3-intl php8.3-bcmath php8.3-gmp
php -m | grep -Ei "gmp|bcmath|gd|zip|intl|mbstring|curl|pdo_mysql"
```

> `gmp`/`bcmath` dipakai untuk enkripsi notifikasi push; `gd` & `zip` untuk Excel.

Node.js 22 (jika belum ada / versinya lama):

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
```

Supervisor (untuk queue worker):

```bash
sudo apt install -y supervisor
```

---

## 3. Batas upload file (50 MB per file)

Edit `/etc/php/8.3/fpm/php.ini` **dan** `/etc/php/8.3/cli/php.ini`:

```ini
upload_max_filesize = 60M
post_max_size = 64M
max_execution_time = 300
memory_limit = 512M
```

```bash
sudo systemctl restart php8.3-fpm
```

---

## 4. Database baru

```bash
sudo mysql
```

```sql
CREATE DATABASE pantau_sepangkep CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pantau'@'localhost' IDENTIFIED BY 'PASSWORD_KUAT_ANDA';
GRANT ALL PRIVILEGES ON pantau_sepangkep.* TO 'pantau'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

> Memakai database baru sehingga database project lama tetap utuh.

---

## 5. Upload kode

**Opsi A – via Git** (bila project disimpan di GitHub/GitLab):

```bash
cd /var/www
sudo git clone https://github.com/AKUN/pantau-sepangkep.git
```

**Opsi B – via file ZIP** (dari komputer Anda):

```bash
# di komputer lokal
scp pantau-sepangkep.zip user@IP_VPS:/tmp/

# di VPS
cd /var/www
sudo unzip /tmp/pantau-sepangkep.zip      # menghasilkan folder /var/www/pantau-sepangkep
```

Atur kepemilikan (ganti `www-data` bila user web server berbeda):

```bash
sudo chown -R $USER:www-data /var/www/pantau-sepangkep
cd /var/www/pantau-sepangkep
```

---

## 6. Install dependensi & konfigurasi

```bash
composer install --no-dev --optimize-autoloader

cp .env.example .env
php artisan key:generate
nano .env
```

Isi minimal di `.env`:

```dotenv
APP_URL=https://pantau.domainanda.go.id
DB_DATABASE=pantau_sepangkep
DB_USERNAME=pantau
DB_PASSWORD=PASSWORD_KUAT_ANDA
ADMIN_PASSWORD=PasswordAdminAwal123
VAPID_SUBJECT="mailto:email-anda@bps.go.id"
```

Lanjutkan:

```bash
# Tabel + akun admin + 6 tim utama + katalog kegiatan standar
php artisan migrate --force --seed

# Kunci notifikasi push HP (sekali saja; JANGAN dibuat ulang setelah dipakai)
php artisan pantau:vapid --write

# Link storage untuk foto profil
php artisan storage:link

# Build CSS/JS
npm ci
npm run build

# Izin folder tulis
sudo chown -R $USER:www-data storage bootstrap/cache
sudo chmod -R ug+rwx storage bootstrap/cache

# Optimasi produksi
php artisan optimize
```

> Paket ZIP sudah menyertakan hasil build di `public/build`. `npm run build` tetap dianjurkan
> agar aset dibangun ulang di server; jika Node.js tidak tersedia, langkah ini boleh dilewati.

---

## 7. Arahkan domain ke project baru (Nginx)

Buka file site lama, misalnya `/etc/nginx/sites-available/pantau`:

```bash
sudo nano /etc/nginx/sites-available/NAMA_FILE_SITE_LAMA
```

Ubah/sesuaikan menjadi seperti ini (yang penting: `root`, `client_max_body_size`, dan `fastcgi_pass` ke PHP 8.3):

```nginx
server {
    listen 80;
    server_name pantau.domainanda.go.id;
    root /var/www/pantau-sepangkep/public;

    index index.php;
    charset utf-8;
    client_max_body_size 64M;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Service worker harus selalu terbaru & boleh mengatur seluruh situs
    location = /sw.js {
        add_header Cache-Control "no-cache";
        add_header Service-Worker-Allowed "/";
        try_files $uri =404;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 300;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
```

Jika sebelumnya sudah memakai HTTPS dari Certbot, **jangan hapus** blok `listen 443 ssl` dan baris `ssl_certificate` yang dibuat Certbot – cukup ganti `root`, `client_max_body_size`, `fastcgi_pass`, lalu tambahkan blok `location = /sw.js`.

```bash
sudo nginx -t && sudo systemctl reload nginx
```

HTTPS (jika belum):

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d pantau.domainanda.go.id
```

> **Apache?** File `public/.htaccess` sudah disertakan. Arahkan `DocumentRoot` ke `/var/www/pantau-sepangkep/public`, aktifkan `mod_rewrite`, dan set `AllowOverride All`.

---

## 8. Queue worker (Supervisor)

Hentikan worker project lama jika ada:

```bash
ls /etc/supervisor/conf.d/
sudo supervisorctl stop all          # atau hanya worker project lama
sudo mv /etc/supervisor/conf.d/WORKER_LAMA.conf ~/WORKER_LAMA.conf.bak   # opsional
```

Buat `/etc/supervisor/conf.d/pantau-worker.conf`:

```ini
[program:pantau-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/pantau-sepangkep/artisan queue:work --sleep=3 --tries=4 --timeout=900 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/pantau-sepangkep/storage/logs/worker.log
stopwaitsecs=900
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

---

## 9. Scheduler (cron)

```bash
sudo crontab -u www-data -e
```

Hapus baris `schedule:run` milik project lama, lalu tambahkan:

```cron
* * * * * cd /var/www/pantau-sepangkep && php artisan schedule:run >> /dev/null 2>&1
```

Jadwal yang berjalan otomatis:

| Jadwal | Fungsi |
|---|---|
| Tiap 15 menit | Pengingat H-1 deadline, tugas terlambat (poin dibekukan), eskalasi SLA verifikasi 2×24 jam kerja |
| Hari kerja 09.00 | Peringatan dini: pegawai tanpa tugas aktif / pasif > 4 hari kerja |
| Jumat 17.00 | *Yellow Alert*: progres mingguan < 20 poin |

---

## 10. Login pertama & data awal

1. Buka `https://pantau.domainanda.go.id`, login: **username `admin`**, password = nilai `ADMIN_PASSWORD`.
2. Anda langsung diminta membuat password baru.
3. **Admin › Pengguna › Import Excel › Import akun** → unggah `akun_pegawai_import.xlsx` (kolom `username`, `password`).
   Semua akun baru otomatis **wajib ganti password saat login pertama**.
4. **Admin › Pengguna › Import Excel › Import biodata** → unduh template (sudah berisi semua username), isi `nama`, `nip`, `jabatan`, lalu unggah.
5. **Admin › Pengguna** → centang **Pimpinan** untuk Kepala/Kasubbag Umum, dan **Administrator** untuk pengelola aplikasi.
6. **Admin › Kelola Tim** → seret pegawai ke tim; klik ★ untuk menetapkan ketua tim. Mode **Salin** untuk pegawai yang ada di beberapa tim.
7. **Admin › Pengaturan** → pilih *Pimpinan utama*, target poin, batas SLA.
8. Pertimbangkan menonaktifkan akun `admin` bawaan setelah akun Anda sendiri diberi peran Administrator.

---

## 11. Menghubungkan Google Drive

Semua lampiran (surat tugas, dokumen, gambar, output) disimpan dengan struktur:

```
PANTAU SEPANGKEP/
└── Nama Pegawai (username)/
    └── November 2026/
        └── TGS-2611-0001 - Judul Tugas/
            ├── 1. Surat Tugas/
            ├── 2. Dokumen Pendukung/   (folder yang diunggah jadi subfolder)
            ├── 3. Gambar/
            └── 4. Output/
```

### Cara A – Akun Google kantor (disarankan)

1. Login ke <https://console.cloud.google.com> memakai akun Google kantor.
2. Buat project baru, misalnya **PANTAU SEPANGKEP**.
3. **APIs & Services › Library** → cari **Google Drive API** → **Enable**.
4. **APIs & Services › OAuth consent screen**:
   - User type: **Internal** (jika akun Google Workspace kantor) atau External.
   - Isi nama aplikasi & email, simpan. Jika *External*, tambahkan akun kantor sebagai *Test user* lalu **Publish app** agar token tidak kedaluwarsa tiap 7 hari.
5. **APIs & Services › Credentials › Create credentials › OAuth client ID**:
   - Application type: **Web application**
   - Authorized redirect URI: `https://pantau.domainanda.go.id/admin/google/callback`
6. Salin **Client ID** dan **Client secret** ke `.env`:

   ```dotenv
   GOOGLE_DRIVE_ENABLED=true
   GOOGLE_DRIVE_AUTH=oauth
   GOOGLE_DRIVE_CLIENT_ID=xxxxxxxx.apps.googleusercontent.com
   GOOGLE_DRIVE_CLIENT_SECRET=xxxxxxxx
   ```

   ```bash
   php artisan optimize
   sudo supervisorctl restart pantau-worker:*
   ```

7. Di aplikasi: **Admin › Pengaturan › Hubungkan akun Google** → pilih akun kantor → izinkan.
8. Klik **Tes koneksi**. Folder **PANTAU SEPANGKEP** akan dibuat di *My Drive* akun tersebut.

Opsional: agar folder dibuat di dalam folder tertentu / Shared Drive, isi `GOOGLE_DRIVE_PARENT_FOLDER_ID` dengan ID dari URL folder (`https://drive.google.com/drive/folders/<ID>`).

### Cara B – Service account + Shared Drive

1. Di Cloud Console: **IAM & Admin › Service Accounts › Create**, lalu **Keys › Add key › JSON**.
2. Simpan file ke `storage/app/private/google-service-account.json` (folder ini tidak bisa diakses publik).
3. Buat **Shared Drive** di Google Drive, tambahkan email service account sebagai *Content manager*.
4. `.env`:

   ```dotenv
   GOOGLE_DRIVE_ENABLED=true
   GOOGLE_DRIVE_AUTH=service_account
   GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON=storage/app/private/google-service-account.json
   GOOGLE_DRIVE_PARENT_FOLDER_ID=<ID Shared Drive>
   ```

> Service account tidak punya kuota penyimpanan sendiri, jadi wajib memakai Shared Drive
> (atau *domain-wide delegation* dengan `GOOGLE_DRIVE_IMPERSONATE=email@domain`).

### Hak akses file

Pegawai membuka/mengunduh lampiran **melalui aplikasi** (aplikasi yang mengambil file dari Drive), jadi file di Drive tidak perlu dibagikan.
Jika ingin link "Buka di Google Drive" juga bisa dibuka pegawai, set:

```dotenv
GOOGLE_DRIVE_SHARE=domain          # semua akun @bps.go.id bisa melihat
GOOGLE_DRIVE_SHARE_DOMAIN=bps.go.id
```

Jika Drive belum diaktifkan, file tetap tersimpan aman di server (`storage/app/private`). Setelah Drive aktif,
klik **Admin › Pengaturan › Upload ulang yang gagal / lokal** untuk memindahkan file lama ke Drive.

---

## 12. Notifikasi push ke HP

Syarat: situs memakai **HTTPS** dan kunci VAPID sudah dibuat (langkah 6).

- **Android (Chrome/Edge/Samsung Internet):** login → muncul kotak *“Aktifkan notifikasi di perangkat ini”* → **Aktifkan** → **Izinkan**.
  Disarankan juga *Tambahkan ke layar utama* agar PANTAU tampil seperti aplikasi.
- **iPhone/iPad (iOS 16.4+):** buka di **Safari** → tombol **Bagikan** → **Tambah ke Layar Utama** → buka PANTAU dari ikon tersebut → menu **Notifikasi** → **Aktifkan notifikasi**.
- **Laptop/PC:** Chrome/Edge/Firefox → **Aktifkan**.

Cek dari menu **Notifikasi › Kirim tes**. Satu akun bisa mendaftarkan beberapa perangkat.
Saat pengguna **Keluar**, perangkat itu berhenti menerima notifikasi.

Pengguna tetap login di perangkatnya sampai menekan **Keluar** (cookie "ingat saya" ±400 hari, sesi diperpanjang otomatis).

---

## 13. Pembaruan aplikasi di kemudian hari

```bash
cd /var/www/pantau-sepangkep
php artisan down
git pull                                # atau upload & timpa file (jangan timpa .env dan storage/)
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci && npm run build
php artisan optimize
sudo supervisorctl restart pantau-worker:*
php artisan up
```

---

## 14. Rollback ke project lama

```bash
sudo nano /etc/nginx/sites-available/NAMA_FILE_SITE_LAMA   # kembalikan root ke /var/www/project-lama/public
sudo nginx -t && sudo systemctl reload nginx
sudo supervisorctl stop pantau-worker:*
sudo mv ~/WORKER_LAMA.conf.bak /etc/supervisor/conf.d/WORKER_LAMA.conf && sudo supervisorctl update
# kembalikan baris cron project lama
```

---

## 15. Pemecahan masalah

| Gejala | Solusi |
|---|---|
| Halaman putih / error 500 | `tail -n 50 storage/logs/laravel-*.log`; pastikan izin `storage/` & `bootstrap/cache/` |
| Tampilan tanpa gaya (CSS hilang) | `npm run build`, pastikan `public/build/manifest.json` ada, lalu `php artisan optimize` |
| Upload gagal untuk file besar | Cek `upload_max_filesize`, `post_max_size` (php.ini) & `client_max_body_size` (Nginx) |
| Lampiran tertahan “mengunggah ke Drive…” | `sudo supervisorctl status`; lihat `storage/logs/worker.log`; klik **Tes koneksi** di Pengaturan |
| Tombol “Aktifkan notifikasi” tidak muncul | Situs harus HTTPS; cek `VAPID_PUBLIC_KEY` di `.env`; lalu `php artisan optimize` |
| Notifikasi tidak masuk ke iPhone | Harus dibuka dari ikon Layar Utama (bukan tab Safari biasa), iOS ≥ 16.4 |
| Pengingat deadline tidak terkirim | Cek cron: `php artisan schedule:list`; uji manual `php artisan pantau:reminders` |
| Error setelah mengubah `.env` | `php artisan optimize:clear && php artisan optimize` |
| Lupa password admin | `php artisan tinker` → `App\Models\User::where('username','admin')->first()->update(['password'=>'Baru12345','must_change_password'=>true]);` |

---

## 16. Perintah artisan khusus

| Perintah | Fungsi |
|---|---|
| `php artisan pantau:vapid --write` | Membuat kunci Web Push (sekali saja) |
| `php artisan pantau:reminders` | Pengingat deadline, tugas terlambat, eskalasi SLA |
| `php artisan pantau:early-warning` | Peringatan pegawai tanpa tugas / pasif |
| `php artisan pantau:early-warning --weekly` | Yellow Alert progres mingguan |
