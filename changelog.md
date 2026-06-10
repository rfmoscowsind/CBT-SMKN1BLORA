# Changelog

## 2026-06-09 - Generate siswa kelas 10 DKV 1

### Data

- Membuat atau memperbarui 36 akun siswa untuk kelas `10 DKV 1`.
- Username dibuat berurutan dari `siswa01` sampai `siswa36`.
- Password setiap akun diset sama dengan username masing-masing.
- Role akun diset `Siswa` dan status kehadiran `hadir`.

### Verification

- Total akun terverifikasi: 36
- Rentang username: `siswa01` sampai `siswa36`
- Kelas: `10 DKV 1`

## 2026-06-09 - Isi bank soal Pengetahuan Umum SMK

### Data

- Mengisi paket `PE - Pengetahuan Umum` dengan 20 soal pilihan ganda Pengetahuan Umum untuk SMK.
- Setiap soal memiliki 5 opsi jawaban A-E dan tepat 1 kunci jawaban.
- Paket ditandai `ready`.

### Verification

- Paket ID 13:
  - `questions`: 20
  - `options`: 100
  - `invalid_keys`: 0

## 2026-06-09 - Hapus jadwal tanpa ditahan arsip

### Changed

- Tombol hapus jadwal sekarang selalu aktif dan tidak lagi ditahan oleh syarat download PDF hasil.
- Endpoint hapus jadwal sekarang benar-benar menghapus `jadwal_ujians`, bukan mengisi `diarsipkan_at`.
- Data terkait jadwal yang ikut dibersihkan:
  - `sesi_ujians`
  - `sesi_ujian_soals`
  - `jawaban_siswas`
  - `session_events`
  - `audit_logs`
  - `hasil_ujian_unduhans`
  - `jadwal_ujian_kelas`
- Data master yang tetap disimpan:
  - `users`
  - `mata_pelajarans`
  - `paket_soals`
  - `bank_soals`
  - `opsi_jawabans`
  - `master_ujians`
  - `kelas_aktifs`
  - `jurusans`
  - `rombels`

### Verification

- PHP lint sukses:
  - `app/Http/Controllers/ScheduleManagementController.php`
- Frontend production build sukses di server:
  - `npm run build`
- PHP-FPM sudah reload agar perubahan aktif.

## 2026-06-09 - Perbaikan tombol hapus/arsip jadwal

### Fixed

- Tombol hapus/arsip jadwal sebelumnya disabled untuk semua jadwal setelah cleanup hasil karena `hasil_ujian_unduhans` ikut dibersihkan.
- Aturan backend diperbaiki:
  - Jadwal tanpa sesi/hasil siswa sekarang boleh dihapus/diarsipkan tanpa syarat download PDF.
  - Jadwal yang sudah punya sesi siswa tetap wajib punya download PDF hasil seluruh kelas target sebelum bisa diarsipkan.

### Verification

- PHP lint sukses:
  - `app/Http/Controllers/ScheduleManagementController.php`
- Verifikasi data:
  - Jadwal kosong sekarang `bisa_diarsipkan=true`.
  - Jadwal ID 20 masih `bisa_diarsipkan=false` karena masih punya 1 sesi dan belum ada download hasil.
- PHP-FPM sudah reload agar perubahan aktif.

## 2026-06-09 - Cleanup arsip hasil ujian

### Cleaned

- Membersihkan data hasil yang terkait jadwal arsip saja.
- Data yang dibersihkan:
  - `hasil_ujian_unduhans`: 18 row
  - `sesi_ujians`: 15 row
  - `jawaban_siswas`: 56 row
  - `sesi_ujian_soals`: 75 row
  - `session_events`: 188 row
  - `audit_logs`: 64 row
  - marker `jadwal_ujians.diarsipkan_at` untuk 18 jadwal dikosongkan
- Data yang sengaja tidak disentuh:
  - `users`
  - `mata_pelajarans`
  - `paket_soals`
  - `bank_soals`
  - `opsi_jawabans`
  - `master_ujians`
  - `kelas_aktifs`
  - `jurusans`
  - `rombels`

### Backup

- Backup sebelum pembersihan dibuat di server:
  - `/var/www/html/storage/app/cleanup-backups/archive-results-20260609-223103.json`
  - Ukuran: 164 KB

### Verification

- Setelah cleanup:
  - `jadwal_ujians.diarsipkan_at IS NOT NULL`: 0
  - `hasil_ujian_unduhans`: 0
  - `sesi_ujians`: 1
  - `jawaban_siswas`: 1
  - `users`: 4
  - `mata_pelajarans`: 3
  - `bank_soals`: 9
  - `opsi_jawabans`: 36
  - `master_ujians`: 2

## 2026-06-09 - Performance dan resiliency ujian real-time

### Fixed

- Menambahkan retry policy untuk `PersistAnswerSnapshot`:
  - `tries = 5`
  - `backoff = [3, 10, 30, 60, 120]`
  - `maxExceptions = 5`
  - `timeout = 30`
  - `failed()` handler menulis critical log dan audit `answer_persist_failed`.
- Menambahkan `PersistSessionAnswersSnapshot` untuk flush semua jawaban pending dalam satu sesi setelah sync batch.
- Mengganti `ExamService::remaining()` dari `static $cache` ke request-scoped container cache agar aman untuk worker long-running/Octane.
- Menambahkan fallback direct DB persist saat Redis/queue buffer jawaban gagal di `ExamService::save()` dan `saveMany()`.
- Membuat submit gagal dengan HTTP 503 jika pending answers tidak bisa di-flush, supaya sesi tidak ditutup sebelum jawaban pending terselamatkan.
- Membungkus audit log jawaban dengan safe wrapper sehingga kegagalan audit tidak menggagalkan persist jawaban.
- Menambahkan fallback ping web/API: jika Redis heartbeat gagal, `last_seen_at` tetap di-update langsung ke DB.
- Membuat pembacaan pending answer dari Redis non-fatal di endpoint soal/navigation.
- Mengurangi query fingerprint per request dengan request-scoped fresh user cache.
- Menambahkan cache aman untuk `Schema::hasTable()` di device fingerprint runtime path.
- Menambahkan dedup/fallback cache untuk `deviceLockEnabled()` dan fingerprint history.

### Performance

- Menambahkan `ExamService::saveMany()` untuk sync/reconnect:
  - batch validasi metadata soal/opsi
  - Redis pipeline untuk banyak jawaban
  - satu job flush sesi, bukan satu job per jawaban
  - fallback direct DB persist jika Redis gagal
- Mengubah sync web/API agar mengirim batch jawaban ke `saveMany()`.
- Mengubah update `ragu/ditandai` saat sync menjadi satu SQL `CASE`, bukan update per jawaban.
- Menambahkan request-scoped cache untuk `sessionItems()`.
- Menambahkan cache render untuk `bank_soals` dan `opsi_jawabans` pada endpoint soal web/API.
- Mengoptimasi sort opsi dari `array_search()` per item menjadi lookup `array_flip()`.
- Menambahkan cache pendek untuk `studentProfile()` dan `studentSchedules()` agar dashboard/payload ujian tidak melakukan join berulang saat burst.
- Menambahkan cache 10 detik untuk PgBouncer overview di endpoint monitoring agar tidak spawn `psql` setiap hit.

### Reliability

- Memperbaiki `RadarWorker`:
  - option `--sleep`
  - option `--max-iterations`
  - option `--max-memory`
  - graceful shutdown via `SIGTERM`/`SIGINT` jika `pcntl` tersedia
  - memory guard
- Menambahkan endpoint ringan:
  - `GET /healthz`
  - `GET /readyz`
- Menambahkan frontend auto-retry terbatas untuk error asset/network dengan countdown sebelum reload.
- Menjalankan `php artisan queue:restart` agar queue worker mengambil kode job/service terbaru.
- Reload PHP-FPM agar perubahan route/kode aktif di runtime web.

### Changed Files

- `app/Console/Commands/RadarWorker.php`
- `app/Http/Controllers/ApiController.php`
- `app/Http/Controllers/Concerns/HandlesDeviceFingerprints.php`
- `app/Http/Controllers/WebController.php`
- `app/Jobs/PersistAnswerSnapshot.php`
- `app/Jobs/PersistSessionAnswersSnapshot.php`
- `app/Services/ExamService.php`
- `resources/js/main.js`
- `routes/web.php`
- `public/build/*`

### Verification

- PHP lint sukses:
  - `app/Services/ExamService.php`
  - `app/Http/Controllers/WebController.php`
  - `app/Http/Controllers/ApiController.php`
  - `app/Http/Controllers/Concerns/HandlesDeviceFingerprints.php`
  - `app/Jobs/PersistAnswerSnapshot.php`
  - `app/Jobs/PersistSessionAnswersSnapshot.php`
  - `app/Console/Commands/RadarWorker.php`
  - `routes/web.php`
- Laravel route boot sukses:
  - `php artisan route:list --path=healthz`
  - `php artisan route:list --path=readyz`
- Radar worker test sukses:
  - `php artisan cbt:radar-worker --max-iterations=1 --sleep=1`
- Frontend production build sukses di server:
  - `npm run build`
- HTTP runtime check sukses setelah reload PHP-FPM:
  - `GET /healthz` -> `200`
  - `GET /readyz` -> `200`
- Queue restart signal sukses:
  - `php artisan queue:restart`

### Test Notes

- `php artisan test` tidak tersedia di instalasi server.
- `vendor/bin/phpunit` juga tidak tersedia karena dev dependency PHPUnit tidak terpasang di `vendor/bin`.
- Verifikasi yang bisa dijalankan selesai lewat lint PHP, route boot, command worker, build frontend, dan HTTP health/readiness.

### Deployment Notes

- `routes/web.php` dimiliki `www-data:www-data` mode `755`, sehingga sempat perlu permission sementara untuk patch route, lalu dikembalikan ke `755`.
- Build frontend dijalankan di server Linux karena `node_modules` berisi native binding Linux; build dari Windows share gagal untuk binding native Windows.
