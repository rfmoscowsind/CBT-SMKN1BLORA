# Requirements, Infrastruktur, dan Operasional CBT SMKN 1 Blora

Tanggal pembaruan: 2026-06-06  
Status: ditulis ulang berdasarkan struktur project yang ada di repository saat ini.

Dokumen ini menjelaskan kebutuhan runtime, dependency, file konfigurasi root, environment, deployment, service production, dan prosedur operasional. Detail backend ada di `back.md`; detail frontend ada di `front.md`.

## Ringkasan Project

Aplikasi ini adalah sistem CBT berbasis Laravel + Vue. Backend berjalan di Laravel/PHP, frontend dibuild dengan Vite, database utama PostgreSQL, dan Redis dipakai untuk session/cache/queue.

Target fungsi utama:

- Login multi-role: SuperAdmin, Admin/Panitia, Guru, Pengawas, Siswa.
- Manajemen master sekolah.
- Manajemen staf dan siswa.
- Bank soal PG/ISIAN.
- Jadwal ujian dan token.
- Pengerjaan ujian online.
- Autosave jawaban.
- Monitoring/radar peserta.
- Penilaian isian.
- Hasil ujian aktif.
- Download PDF hasil.
- Arsip hasil.
- Device lock/fingerprint siswa.

## Topologi Production Aktual

Server aplikasi:

- Host: `192.168.16.120`.
- Nama: `LXC-CBT`.
- Direktori aplikasi: `/var/www/html`.
- Domain production: `https://cbt.madnnet.my.id`.
- Service utama: Nginx, PHP-FPM, Redis, queue worker, scheduler, radar worker.

Server database primary:

- Host: `192.168.16.121`.
- Nama: `LXC-DB-POSTGRE-1`.
- Service utama: PostgreSQL.

Server standby:

- Host: `192.168.16.122`.
- Port umum: `22`, `5432`.
- Dipakai/direncanakan untuk standby/read-only sesuai konfigurasi operasional.

Redis production:

- Berjalan lokal di server aplikasi.
- Host: `127.0.0.1`.
- Port: `6379`.
- Dipakai Laravel untuk session, cache, dan queue.

## Requirement Runtime

### PHP

Versi:

- Minimal sesuai `composer.json`: PHP `^8.3`.

Extension yang dibutuhkan:

- `mbstring`.
- `xml`.
- `pgsql`.
- `redis`.
- `bcmath`.
- `curl`.
- `gd`.
- `intl`.
- `zip`.
- `fileinfo`.
- `openssl`.
- `pdo`.

Alasan:

- Laravel butuh extension dasar PHP.
- PostgreSQL butuh `pgsql/pdo_pgsql`.
- Redis queue/session butuh `phpredis`.
- DomPDF dan image processing membutuhkan extension grafis dan string.

### Composer

Dipakai untuk install dependency PHP.

Command production:

```bash
composer install --no-dev --optimize-autoloader
```

Command development/test:

```bash
composer install
composer test
```

### Node.js dan NPM

Dipakai untuk build frontend Vite.

Rekomendasi:

- Node.js 20 LTS atau lebih baru.
- NPM sesuai bawaan Node.

Command:

```bash
npm install
npm run build
```

### PostgreSQL

Database utama aplikasi.

Rekomendasi:

- PostgreSQL 15/16.
- Storage SSD/NVMe.
- Backup rutin.
- Connection limit disesuaikan jumlah PHP-FPM worker.

Tabel utama:

- `users`.
- `jurusans`.
- `rombels`.
- `tingkats`.
- `kelas_aktifs`.
- `mata_pelajarans`.
- `paket_soals`.
- `bank_soals`.
- `opsi_jawabans`.
- `master_ujians`.
- `jadwal_ujians`.
- `jadwal_ujian_kelas`.
- `sesi_ujians`.
- `sesi_ujian_soals`.
- `jawaban_siswas`.
- `audit_logs`.
- `session_events`.
- `hasil_ujian_unduhans`.
- Tabel permission Spatie.
- Tabel queue/cache/session Laravel.

### Redis

Redis wajib untuk production saat ini.

Dipakai untuk:

- `SESSION_DRIVER=redis`.
- `CACHE_STORE=redis`.
- `QUEUE_CONNECTION=redis`.
- `queue_jawaban:{sessionId}` saat autosave.
- Cache soal/opsi/session.
- Registry session untuk device lock.
- Data radar/live state.

Konfigurasi `.env` production:

```env
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

## Dependency PHP

### `composer.json`

File manifest dependency PHP.

Package production:

- `laravel/framework`: framework utama.
- `laravel/octane`: opsi runtime high-performance.
- `laravel/tinker`: REPL Laravel.
- `tymon/jwt-auth`: autentikasi JWT API.
- `spatie/laravel-permission`: role dan permission.
- `vinkla/hashids`: encode/decode ID publik.
- `barryvdh/laravel-dompdf`: generate PDF laporan.
- `maatwebsite/excel`: import/export spreadsheet.
- `intervention/image`: pemrosesan gambar soal.

Package development:

- `phpunit/phpunit`: test.
- `laravel/pint`: formatter PHP.
- `laravel/pail`: tail log Laravel.
- `mockery/mockery`, `fakerphp/faker`, `nunomaduro/collision`.

Script penting:

- `composer setup`: install, generate key, migrate, npm install, build.
- `composer dev`: menjalankan server, queue listener, pail, vite bersamaan.
- `composer test`: clear config lalu menjalankan test Laravel.

### `composer.lock`

File lock dependency PHP.

Fungsi:

- Menjamin versi package konsisten.
- Harus dicommit untuk aplikasi.
- Jangan edit manual.

## Dependency Frontend

### `package.json`

Manifest dependency Node/Vite.

Script:

- `npm run dev`: menjalankan Vite dev server.
- `npm run build`: build production.

Dependency/devDependency penting:

- `vite`.
- `laravel-vite-plugin`.
- `@vitejs/plugin-vue`.
- `@tailwindcss/vite`.
- `tailwindcss`.
- `vue`.
- `vue-router`.
- `pinia`.
- `axios`.
- `bootstrap`.
- `@popperjs/core`.
- `sweetalert2`.
- `concurrently`.

### `package-lock.json`

File lock dependency NPM.

Fungsi:

- Menjamin versi package JS konsisten.
- Harus dicommit.
- Jangan edit manual.

### `vite.config.js`

Konfigurasi Vite.

Input build:

- `resources/css/app.css`.
- `resources/js/app.js`.
- `resources/js/main.js`.

Plugin:

- `laravel-vite-plugin`.
- `@tailwindcss/vite`.
- `@vitejs/plugin-vue`.
- Bunny font `Instrument Sans`.

Alias:

- `@` mengarah ke `/resources/js`.
- `vue` diarahkan ke build bundler.

Catatan:

- Watch mengabaikan `storage/framework/views/**` agar cache Blade tidak memicu rebuild.

## File Root dan Konfigurasi Project

### `artisan`

CLI Laravel.

Dipakai untuk:

- `php artisan migrate`.
- `php artisan queue:work`.
- `php artisan schedule:work`.
- `php artisan cbt:radar-worker`.
- `php artisan config:clear`.
- `php artisan test`.

### `.env`

File environment lokal/production.

Fungsi:

- Database credential.
- App key.
- Redis/session/cache/queue.
- Mail.
- JWT secret.

Catatan:

- Jangan commit.
- Jangan taruh secret di dokumen.
- Jika production Linux, jalankan command cache/config dari `/var/www/html`, bukan dari Windows share.

### `.env.example`

Template environment.

Fungsi:

- Menjadi referensi variabel yang dibutuhkan.
- Aman dicommit selama tidak berisi secret asli.

### `.gitignore`

Daftar file/folder yang tidak dicommit.

Saat ini penting karena mengabaikan:

- `.env`.
- `node_modules`.
- `vendor`.
- `public/build`.
- `public/storage`.
- log/cache umum.
- folder artefak `public/T3V7OJ~5/` dan `public/T68XVV~A/`.

### `.gitattributes`

Aturan atribut git.

Fungsi:

- Normalisasi line ending.
- Konfigurasi export archive jika ada.

### `.editorconfig`

Standar editor.

Fungsi:

- Mengatur indentasi, charset, newline, dan whitespace lintas editor.

### `.npmrc`

Konfigurasi NPM lokal.

Fungsi:

- Mengatur behavior install NPM sesuai kebutuhan project.

### `.submit`

File marker kosong.

Fungsi:

- Tidak terlihat dipakai oleh runtime Laravel/Vite.
- Kemungkinan marker internal/deployment.

### `phpunit.xml`

Konfigurasi PHPUnit.

Fungsi:

- Menentukan bootstrap test.
- Menentukan environment test.
- Menentukan suite test.

## Config Laravel

### `config/app.php`

Konfigurasi nama app, environment, debug, URL, timezone, locale, encryption key, provider.

### `config/auth.php`

Konfigurasi auth guard/provider.

Penting:

- Web session untuk browser.
- API guard untuk JWT.

### `config/cache.php`

Konfigurasi cache.

Production:

- Gunakan Redis.

### `config/database.php`

Konfigurasi database dan Redis.

Production:

- PostgreSQL primary.
- Redis local.

### `config/filesystems.php`

Konfigurasi disk storage.

Dipakai untuk:

- Gambar soal.
- File import.
- Public storage.

### `config/logging.php`

Konfigurasi log.

Rekomendasi:

- Gunakan daily log untuk production.
- Jangan biarkan log besar dicommit.

### `config/mail.php`

Konfigurasi email.

Belum menjadi fitur inti, tetapi tetap disiapkan.

### `config/permission.php`

Konfigurasi Spatie Permission.

Dipakai role dan permission.

### `config/queue.php`

Konfigurasi queue.

Production:

- `redis`.
- Worker harus aktif.

### `config/services.php`

Konfigurasi service eksternal.

Secret harus dari `.env`.

### `config/session.php`

Konfigurasi session.

Production:

- `redis`.
- Penting untuk device lock dan invalidasi session.

## Direktori Runtime

### `storage/app`

Storage aplikasi.

Fungsi:

- File upload.
- File generated internal.
- Subfolder `public` untuk file yang bisa dipublish.

### `storage/framework`

Cache runtime Laravel.

Isi umum:

- `cache`.
- `sessions`.
- `testing`.
- `views`.

Catatan:

- Isi selain `.gitignore` boleh dibersihkan.
- File akan dibuat ulang oleh Laravel.

### `storage/logs`

Log Laravel.

Catatan:

- Log boleh dibersihkan/rotate.
- Jangan dicommit.

### `bootstrap/cache`

Cache bootstrap Laravel.

Isi umum:

- `packages.php`.
- `services.php`.
- Kadang `config.php`, `routes-v7.php`, dll jika optimize/cache dijalankan.

Catatan penting:

- Jangan menjalankan `php artisan config:cache` dari Windows share untuk production Linux karena path Windows dapat masuk cache.

## Service Production

Service permanen yang dipakai:

```text
cbt-worker.service      -> php artisan queue:work redis --queue=answers,default --sleep=1 --tries=3 --timeout=60
cbt-scheduler.service   -> php artisan schedule:work
cbt-radar.service       -> php artisan cbt:radar-worker
```

Restart:

```bash
sudo systemctl restart cbt-worker.service cbt-scheduler.service cbt-radar.service
```

Status:

```bash
sudo systemctl status cbt-worker.service cbt-scheduler.service cbt-radar.service
```

Log:

```bash
journalctl -u cbt-worker.service -f
journalctl -u cbt-scheduler.service -f
journalctl -u cbt-radar.service -f
```

Catatan:

- Jangan menjalankan worker manual paralel jika service permanen aktif.
- Worker ganda dapat menyebabkan proses queue/radar dobel.

## Deployment Production

Langkah umum:

```bash
cd /var/www/html
git pull
composer install --no-dev --optimize-autoloader
npm install
npm run build
php artisan migrate --force
php artisan config:clear
php artisan route:clear
php artisan view:clear
sudo systemctl reload php8.3-fpm php8.2-fpm
sudo systemctl restart cbt-worker.service cbt-scheduler.service cbt-radar.service
```

Catatan:

- Jika ingin menjalankan `config:cache`, lakukan hanya dari server Linux production.
- Untuk menghindari stale chunk, user bisa membuka `/recover-login.html` setelah deploy besar.
- `public/build` dibuat ulang oleh Vite.

## Smoke Test

Tanpa login:

```bash
curl -I https://cbt.madnnet.my.id/login
curl -I https://cbt.madnnet.my.id/dashboard
curl -I https://cbt.madnnet.my.id/vue/management/hasil
curl -I https://cbt.madnnet.my.id/vue/management/arsip-hasil
```

Ekspektasi:

- `/login` status `200`.
- `/dashboard` redirect ke login jika belum login.
- Route `/vue/*` redirect/login guard jika belum login.

Dengan login:

- Dashboard sesuai role muncul.
- Siswa melihat jadwal yang sesuai kelas dan waktu.
- Mulai ujian menghasilkan session hash.
- Simpan jawaban tidak error.
- Submit menghasilkan status selesai.
- Management hasil menampilkan jadwal belum arsip.
- Arsip hasil menampilkan jadwal sudah arsip.
- Download hasil mencatat `hasil_ujian_unduhans`.

## Testing

Command:

```bash
composer test
```

Atau:

```bash
php artisan test
```

Test penting:

- Completion flow.
- Device fingerprint reset.
- Exam result download.
- Exam result management.
- Final flow.
- Question bank management.
- Schedule management.
- School class generation.
- Security regression.
- Student dashboard schedule.
- Student password management.

## Backup dan Restore

Minimal backup:

- Database PostgreSQL.
- `.env` production di vault/secret manager.
- `storage/app/public` jika berisi gambar soal.
- Konfigurasi Nginx.
- Systemd service file worker.

Contoh backup PostgreSQL:

```bash
pg_dump -h 192.168.16.121 -U <db_user> -d <db_name> -Fc -f cbt_$(date +%F).dump
```

Contoh restore:

```bash
pg_restore -h 192.168.16.121 -U <db_user> -d <db_name> --clean --if-exists cbt_YYYY-MM-DD.dump
```

## Security Checklist

- `.env` tidak dicommit.
- `cred.txt` tidak dipakai sebagai penyimpanan secret permanen.
- Password user selalu hash.
- JWT secret kuat dan disimpan di `.env`.
- APP_KEY production tidak berubah setelah deploy.
- Database tidak dibuka publik.
- Redis hanya listen local/internal.
- Nginx memakai HTTPS.
- Endpoint management wajib auth.
- Endpoint monitoring wajib permission/role.
- Device fingerprint siswa wajib diverifikasi.
- Kunci jawaban tidak dikirim ke frontend siswa.
- Log tidak menampilkan secret.

## File Dokumentasi Project

File `.md` di root berisi catatan historis/perencanaan/issue. Sesuai permintaan, file `.md` tidak dihapus.

File dokumentasi utama yang sekarang diperbarui:

- `back.md`: backend dan database.
- `front.md`: frontend dan UI.
- `req.md`: requirement, dependency, deployment, operasional.

File `.md` lain seperti `README.md`, `CHANGELOG.md`, `STATUS.md`, `IMPLEMENTATION_STATUS.md`, dan catatan issue tetap dipertahankan sebagai arsip konteks.

## File yang Bukan Source dan Boleh Dibersihkan

Aman dibersihkan jika permission memungkinkan:

- `.phpunit.result.cache`.
- `storage/logs/*.log`.
- `storage/framework/views/*.php`.
- `storage/framework/sessions/*`.
- `bootstrap/cache/packages.php`.
- `bootstrap/cache/services.php`.
- Folder artefak salah tempat seperti `public/T3V7OJ~5` dan `public/T68XVV~A`.

Tidak boleh dibersihkan sembarangan:

- `.env`.
- `composer.lock`.
- `package-lock.json`.
- `vendor` di production tanpa rencana reinstall.
- `node_modules` di environment build tanpa rencana reinstall.
- `public/build` di production tanpa build ulang.
- `storage/app/public` jika berisi gambar soal.

