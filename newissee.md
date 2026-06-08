# NEWISSEE - Known Issue CBT Berdasarkan Screenshot dan Pemeriksaan Repo

Dokumen ini mencatat known issue/temuan performa dari screenshot DevTools dan pemeriksaan kode repository `CBT-SMKN1BLORA`.

Tanggal catatan: 2026-06-08.

Fokus:

- performa PHP-FPM saat ini,
- pola request dashboard dan halaman ujian,
- potensi bottleneck untuk 1500 siswa via Cloudflare/internet,
- issue yang belum tentu error, tetapi perlu diwaspadai sebelum ujian massal.

---

## Kesimpulan Singkat

Dari screenshot DevTools, performa frontend dan PHP-FPM saat ini terlihat sehat.

Contoh observasi dari screenshot:

```text
Login page:
- document sekitar 90-120 ms
- total load sekitar < 500 ms
- asset banyak dari disk cache/service worker

Dashboard siswa:
- DOMContentLoaded sekitar 400 ms
- Load sekitar 568 ms
- Finish sekitar 806 ms

Halaman ujian:
- DOMContentLoaded sekitar 336-414 ms
- Load sekitar 390-579 ms
- endpoint soal/simpan terlihat sekitar puluhan sampai ratusan ms
- Finish panjang 11-13 detik kemungkinan karena background request/service worker, bukan render utama lambat
```

Artinya:

```text
PHP-FPM masih layak dipakai.
Octane belum wajib.
```

Namun untuk 1500 siswa via internet/Cloudflare, tetap ada beberapa known issue dan risiko operasional.

---

# 1. Finish DevTools Panjang, Tapi Load Utama Cepat

## Observasi

Di halaman ujian, DevTools menunjukkan:

```text
DOMContentLoaded < 500 ms
Load < 600 ms
Finish bisa 11-13 detik
```

## Analisis

Ini biasanya bukan berarti halaman lambat. Finish bisa panjang karena:

- service worker masih menangani fetch,
- request background seperti ping/device/raw/event,
- request async setelah halaman tampil,
- resource kecil yang dimuat setelah initial render.

## Dampak

Jika hanya melihat angka `Finish`, performa bisa terlihat seolah lambat padahal UI sudah usable.

## Rekomendasi

Patokan performa untuk CBT jangan hanya `Finish`, tapi pisahkan:

```text
DOMContentLoaded
Load
TTFB document
waktu endpoint soal
waktu endpoint simpan
waktu endpoint ping
waktu endpoint submit
```

Target sehat:

```text
GET soal       < 300-500 ms
POST save      < 200-500 ms
POST sync      < 500 ms
POST ping      < 100-300 ms
POST submit    < 1-2 detik
```

---

# 2. Service Worker Banyak Terlibat

## Observasi

Di screenshot banyak request memiliki initiator:

```text
sw.js
ServiceWorker
```

## Analisis

Service worker membantu cache asset dan mempercepat halaman setelah load pertama. Namun service worker juga bisa membuat debugging lebih sulit karena:

- response bisa berasal dari cache,
- asset lama bisa tetap terpakai setelah deploy,
- request background bisa membuat waterfall terlihat panjang,
- siswa bisa melihat versi frontend lama jika update SW tidak rapi.

## Dampak saat ujian

Jika deploy dilakukan dekat waktu ujian, sebagian browser siswa bisa masih memakai asset lama.

## Rekomendasi

- Jangan deploy frontend mepet ujian.
- Pastikan build asset punya hash versioning.
- Pastikan service worker update strategy jelas.
- Sediakan prosedur hard refresh/clear cache jika ada masalah.
- Pertimbangkan tombol/endpoint versi aplikasi agar frontend bisa mendeteksi mismatch.

Checklist sebelum ujian:

```text
- buka halaman login dari incognito
- buka dashboard siswa
- buka sesi ujian dummy
- pastikan asset terbaru yang tampil
- pastikan service worker tidak menyajikan bundle lama
```

---

# 3. PHP-FPM Masih Cukup, Octane Belum Wajib

## Observasi

Screenshot menunjukkan response login/dashboard/ujian sudah cepat di PHP-FPM.

## Analisis

Octane memang bisa lebih cepat karena Laravel tidak boot ulang per request. Namun Octane membawa risiko persistent worker:

- static state bisa bocor antar request,
- singleton yang menyimpan data user/request bisa berbahaya,
- memory leak lebih terasa,
- butuh reload khusus setelah deploy.

## Rekomendasi

Untuk ujian pertama atau production dekat waktu ujian:

```text
Gunakan PHP-FPM dulu.
```

Pindah ke Octane hanya jika load test menunjukkan:

```text
CPU web sering penuh
latency save/soal naik
php-fpm max_children mentok
request dinamis > kapasitas FPM
```

Tuning PHP-FPM awal untuk i5-9400F RAM 16GB:

```ini
pm = dynamic
pm.max_children = 70
pm.start_servers = 10
pm.min_spare_servers = 10
pm.max_spare_servers = 25
pm.max_requests = 500
request_terminate_timeout = 60s
```

OPcache production:

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=32
opcache.max_accelerated_files=50000
opcache.validate_timestamps=0
opcache.jit=off
```

Setelah deploy:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo systemctl reload php8.3-fpm
sudo systemctl reload nginx
```

---

# 4. Config Default Repo Masih Database-Heavy Jika `.env` Server Salah

## Kondisi repo

`config/cache.php` default masih:

```php
'default' => env('CACHE_STORE', 'database')
```

`config/session.php` default masih:

```php
'driver' => env('SESSION_DRIVER', 'database')
```

`config/queue.php` default masih:

```php
'default' => env('QUEUE_CONNECTION', 'database')
```

## Risiko

Jika `.env` production salah atau config cache belum di-refresh, sistem bisa tetap memakai database untuk:

- cache,
- session,
- queue.

Untuk 1500 siswa, ini bisa menjadi bottleneck besar.

## Rekomendasi wajib production

```env
CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_STORE=redis
QUEUE_CONNECTION=redis
REDIS_QUEUE=answers
```

Setelah ubah `.env`:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan queue:restart
sudo systemctl reload php8.3-fpm
```

---

# 5. Start Ujian Masih Melakukan Generate Sesi Soal Saat Siswa Mulai

## Kondisi kode

Saat `ExamService::start()`, sistem:

1. cek jadwal,
2. cek kelas,
3. lock/cek existing session,
4. jika belum ada, insert session,
5. ambil semua ID soal paket,
6. ambil opsi semua soal,
7. shuffle jika perlu,
8. insert batch ke `sesi_ujian_soals`.

## Analisis

Kode sudah lebih baik karena opsi diambil batch dan insert batch. Namun kalau 1500 siswa klik mulai bersamaan, tetap terjadi banyak pekerjaan DB secara spike.

## Dampak

Saat start massal:

- DB primary bisa spike,
- lock/unique index akan bekerja keras,
- pembuatan `sesi_ujian_soals` untuk semua siswa bisa berat.

## Rekomendasi

- Login boleh dibuka 10-15 menit sebelum ujian.
- Tombol mulai jangan ditekan semua dalam detik yang sama jika bisa.
- Pertimbangkan pre-generate sesi untuk jadwal tertentu.
- Pastikan index `sesi_ujians(user_id, jadwal_ujian_id)` dan `sesi_ujian_soals(sesi_ujian_id, nomor_soal)` ada.

Opsi optimasi lanjutan:

```text
php artisan cbt:prepare-sessions --jadwal=ID
```

Command ini bisa membuat sesi dan `sesi_ujian_soals` sebelum ujian dimulai, sehingga saat siswa klik mulai, sistem hanya membuka sesi existing.

---

# 6. Save Jawaban Sudah Ringan, Tapi Tetap Bergantung Redis dan Worker

## Kondisi kode

Saat save jawaban, sistem:

- validasi sesi aktif dan sisa waktu,
- cache data soal,
- cache daftar soal sesi,
- validasi opsi,
- simpan payload ke Redis hash `queue_jawaban:{sessionId}`,
- dispatch job `PersistAnswerSnapshot` ke queue `answers`.

## Analisis

Ini desain bagus untuk CBT karena request save tidak langsung menulis penuh ke DB. Namun ini membuat Redis dan worker menjadi komponen kritikal.

## Risiko

Jika Redis lambat/mati:

- save jawaban gagal,
- session/cache bisa terganggu,
- queue answers terganggu.

Jika worker mati:

- jawaban tetap ada di Redis,
- tetapi DB bisa terlihat belum update,
- report/monitoring bisa tidak sinkron sampai submit/flush.

## Rekomendasi

- Redis harus dedicated dan stabil.
- Worker `answers` wajib jalan dengan supervisor/systemd.
- Monitor Redis memory dan queue length.
- Jangan pakai Redis eviction policy yang bisa membuang jawaban.

Redis setting:

```conf
maxmemory-policy noeviction
appendonly yes
appendfsync everysec
```

Worker awal:

```bash
php artisan queue:work redis --queue=answers --sleep=0 --tries=3 --timeout=30
```

Jumlah worker awal:

```text
4 worker dulu
naik ke 6-8 jika DB masih ringan
```

---

# 7. Ping Sudah Redis-First, Tapi Interval Frontend Tetap Perlu Dijaga

## Kondisi kode

`WebController::ping()` sudah menyimpan status online ke Redis TTL 45 detik dan update DB `last_seen_at` maksimal 1x per 60 detik per sesi.

## Analisis

Ini sudah benar untuk 1500 siswa. Namun jika frontend ping terlalu sering, origin tetap menerima banyak request HTTP.

## Risiko

Jika ping interval terlalu rapat:

```text
1500 siswa x ping setiap 3 detik = 500 request/detik
```

Walaupun DB tidak berat, web dan Redis tetap kena.

## Rekomendasi

Gunakan interval ping:

```text
10-15 detik
```

Saat tab hidden/background, pertimbangkan interval lebih lambat:

```text
30 detik
```

Jika request gagal, gunakan backoff:

```text
10s -> 20s -> 30s
```

---

# 8. Endpoint Soal Masih Query Per Nomor Soal

## Kondisi kode

`singleQuestionPayload()` melakukan query:

- `sesi_ujian_soals` by session + nomor,
- `bank_soals` by ID,
- `jawaban_siswas` by session + bank_soal,
- Redis pending answer,
- opsi jawaban by bank_soal.

## Analisis

Untuk request per soal, ini masih normal dan dari screenshot endpoint soal terlihat cepat. Namun untuk 1500 siswa, query ini tetap menjadi path panas.

## Rekomendasi

Pastikan index:

```sql
CREATE UNIQUE INDEX IF NOT EXISTS uq_sesi_nomor
ON sesi_ujian_soals (sesi_ujian_id, nomor_soal);

CREATE UNIQUE INDEX IF NOT EXISTS uq_jawaban_session_soal
ON jawaban_siswas (sesi_ujian_id, bank_soal_id);

CREATE INDEX IF NOT EXISTS idx_opsi_bank_kode
ON opsi_jawabans (bank_soal_id, kode);
```

Optimasi lanjutan:

- cache opsi per soal,
- cache question payload static tanpa jawaban,
- pisahkan payload statis soal dan payload jawaban.

---

# 9. Navigation Mengambil Semua Jawaban Session

## Kondisi kode

`navigation()` mengambil seluruh `jawaban_siswas` untuk satu session, lalu mengambil semua pending answer Redis.

## Analisis

Untuk 40-100 soal per siswa masih wajar. Namun jika tiap request halaman memanggil navigation penuh, query ini bisa sering terjadi.

## Risiko

Pada 1500 siswa, request navigasi penuh terlalu sering bisa menambah beban DB/Redis.

## Rekomendasi

- Pastikan hanya dipanggil saat perlu.
- Untuk pindah soal, frontend bisa update navigasi lokal setelah save.
- Endpoint soal tunggal sebaiknya tidak selalu mengirim navigation lengkap jika tidak perlu.
- Pertimbangkan endpoint khusus `navigation` dengan cache pendek 5-10 detik per session.

---

# 10. Asset Frontend 1.5-1.7 MB Masih Wajar, Tapi Wajib Dicache Cloudflare

## Observasi screenshot

Dashboard/ujian menunjukkan resources sekitar:

```text
1.5 MB - 1.7 MB
```

## Analisis

Ukuran ini masih wajar untuk SPA. Namun untuk 1500 siswa full internet, load awal bisa menjadi spike bandwidth.

## Rekomendasi Cloudflare

Cache agresif untuk asset static:

```text
/build/*
/assets/*
*.css
*.js
*.woff2
*.png statis
favicon.ico
```

Jangan cache halaman dinamis:

```text
/login
/dashboard
/vue/* jika berisi data user
/ujian/*
/api/*
```

Matikan fitur yang berisiko mengganggu SPA:

```text
Rocket Loader: OFF
Bot Fight Mode agresif: hati-hati/test dulu
Auto Minify: test dulu sebelum ujian
```

---

# 11. Full Internet via Cloudflare Membutuhkan Bandwidth Origin yang Stabil

## Kondisi operasional

Semua siswa akses via Cloudflare/internet.

## Rekomendasi bandwidth

```text
Minimum     : 100 Mbps simetris
Recommended : 200 Mbps simetris
Nyaman      : 300 Mbps simetris
```

Jika soal banyak gambar, gunakan minimal 200 Mbps simetris.

## Catatan

Cloudflare mengurangi traffic asset statis, tetapi request dinamis tetap ke origin:

- login,
- dashboard data,
- mulai ujian,
- ambil soal,
- save,
- ping,
- submit.

---

# 12. Known Issue Operasional: Jangan Pakai Tombol Reset Destruktif untuk Siswa Nyangkut

Catatan ini sudah dibahas di `gpt08061.md`, tetapi tetap perlu masuk known issue performa/operasional.

Reset destruktif dapat menghapus jawaban dan mengulang ujian dari nol. Untuk siswa yang hanya nyangkut, harus ada tombol:

```text
Buka Sesi / Lanjutkan
```

Perilaku tombol ini:

```text
jangan hapus jawaban_siswas
jangan hapus queue_jawaban Redis
jangan hapus sesi_ujian_soals
jangan ubah waktu_login
set status aktif
clear device lock/fingerprint
```

---

# 13. Checklist Load Test yang Harus Dilakukan

Minimal simulasi:

```text
100 siswa
300 siswa
500 siswa
1000 siswa
1500 siswa
```

Endpoint yang dites:

```text
POST /login
GET dashboard siswa
POST/GET mulai ujian
GET soal nomor 1
POST simpan jawaban
GET soal berikutnya
POST sync
POST ping
POST submit
```

Metric yang dicatat:

```text
p50 latency
p95 latency
p99 latency
error rate
CPU web
RAM web
PHP-FPM active children
Redis ops/sec
Redis memory
queue length answers
PostgreSQL CPU
PostgreSQL IOPS
slow query
replication lag standby
```

Target sebelum ujian:

```text
error rate < 1%
POST save p95 < 500 ms
GET soal p95 < 500 ms
POST ping p95 < 300 ms
submit p95 < 2 detik
queue answers tidak menumpuk lama
DB CPU tidak stabil di 100%
```

---

# Rekomendasi Final Saat Ini

Berdasarkan screenshot dan repo:

```text
PHP-FPM tetap dipakai dulu.
Octane belum wajib.
```

Yang lebih penting sebelum 1500 siswa:

1. Pastikan production `.env` benar-benar Redis untuk session/cache/queue.
2. Pastikan OPcache aktif.
3. Pastikan Cloudflare cache asset static.
4. Pastikan index DB wajib sudah ada.
5. Pastikan worker queue `answers` aktif.
6. Pastikan Redis noeviction.
7. Pastikan tombol `Buka Sesi / Lanjutkan` tersedia.
8. Lakukan load test bertahap.

Jika load test menunjukkan PHP-FPM mentok, baru evaluasi Laravel Octane di staging.
