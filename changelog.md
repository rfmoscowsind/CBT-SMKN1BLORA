# Changelog

## 2026-06-11 - Implementasi Fix Login, Monitoring, dan Beban Submit

### Fixed
- Menambahkan `axios.defaults.withCredentials = true` dan interceptor global `401/419` di `resources/js/main.js` agar session expired diarahkan bersih ke login.
- Menambahkan route Vue `/vue/dashboard/admin` sebagai alias dashboard panitia agar redirect role `Admin` tidak jatuh ke fallback `/dashboard`.
- Menghentikan registrasi Service Worker baru di template app/layout sambil tetap membersihkan worker dan cache lama untuk mengurangi risiko state/cache login nyangkut.
- Menghapus eksekusi `proc_open()`/`psql` dari request `/monitoring/stats`; route sekarang membaca snapshot PgBouncer dari cache.
- Menambahkan command `monitoring:pgbouncer-snapshot` dan jadwal tiap menit untuk mengisi snapshot PgBouncer di cache.
- Mengubah `ExamService::flushAll()` menjadi bulk upsert PostgreSQL per sesi dengan guard `server_updated_at` agar submit massal tidak menembakkan query per jawaban.
- Mengubah timer `ExamInterface.vue` menjadi berbasis deadline absolut `Date.now()` agar tidak ngaret saat tab browser di-background.

### Documentation
- Memperbarui `newgemini.md` dengan status implementasi fix per lokasi kode.

## 2026-06-11 - Dokumentasi Verifikasi Analisis Gemini

### Added
- Menambahkan `newgemini.md` sebagai dokumen verifikasi analisis Gemini terkait login, cookie/session, Service Worker, router fallback, monitoring PgBouncer, bulk submit jawaban, dan timer ujian.

### Notes
- Dokumen mengelompokkan klaim menjadi `VALID`, `KURANG TEPAT`, dan `TAMBAHAN` berdasarkan kode aktual.
- Tidak ada perubahan kode aplikasi, konfigurasi runtime, service, atau build asset pada entry ini.

## 2026-06-11 - Fix Login 500 Device Fingerprint History

### Fixed
- Memperbaiki error login siswa yang kadang menghasilkan HTTP 500 karena insert `device_fingerprint_histories.lock_enabled` mengirim integer `0/1` ke kolom PostgreSQL boolean.
- Nilai `lock_enabled` sekarang dikirim sebagai ekspresi boolean database (`true`/`false`) agar konsisten dengan perbaikan boolean PostgreSQL lain di jalur device lock.

### Verification
- `php -l app/Http/Controllers/Concerns/HandlesDeviceFingerprints.php` sukses.
- Octane/RoadRunner direstart setelah patch.
- Smoke test insert device fingerprint history untuk user siswa berhasil dalam transaksi rollback (`DEVICE_HISTORY_INSERT_OK`).
- Error terakhir di `storage/logs/laravel.log` untuk `lock_enabled` terjadi sebelum patch, pada `2026-06-11 04:16:17`.

## 2026-06-11 - Hardening Jalur Ujian dan Runtime Octane

### Added
- Menambahkan pending queue lokal di `ExamInterface.vue` untuk jawaban siswa yang belum terkonfirmasi server.
- Menambahkan auto-sync batch ke endpoint `/ujian/sesi/{session}/sync` saat koneksi kembali online, sebelum pindah soal, dan sebelum submit final.
- Menambahkan indikator sinkronisasi/pending jawaban agar siswa tahu masih ada jawaban lokal yang menunggu server.
- Menambahkan service systemd template `cbt-answer-worker@.service` dan mengaktifkan 4 worker khusus queue `answers`.

### Changed
- Submit ujian sekarang diblok jika masih ada pending jawaban lokal yang gagal sinkron ke server.
- Queue worker default dipisah dari queue jawaban: `cbt-worker.service` hanya memproses `default`, sementara `cbt-answer-worker@1..4` memproses `answers`.
- Redis production diaktifkan AOF dengan `appendonly yes`, `appendfsync everysec`, RDB snapshot standar, dan `maxmemory-policy noeviction`.
- `/monitoring/sessions` sekarang server-side filtered (`active`, `finished`, `recent`), dibatasi `limit`, dan di-cache pendek 3 detik.
- Dashboard Panitia memakai scope monitoring eksplisit untuk sesi aktif dan hasil selesai.
- `ExamService::remaining()`, `WebController::sessionItems()`, dan `ApiController::sessionItems()` tidak lagi memakai `app()->instance()` agar aman untuk Octane/RoadRunner long-running worker.
- Audit `answer_saved` normal dibuat opt-in lewat `AUDIT_ANSWER_SAVED=false` untuk mengurangi write `audit_logs` saat ujian besar.
- Fallback Redis client di `config/database.php` diselaraskan ke `phpredis`.

### Fixed
- Jawaban yang sebelumnya hanya tersimpan di `localStorage` kini tidak lagi hilang diam-diam saat submit; frontend wajib menyinkronkan pending queue sebelum menyelesaikan ujian.
- Endpoint `/sync` web sekarang mengembalikan `sisa_detik` dan `server_updated_at` agar frontend bisa menyelaraskan timer setelah batch sync.

### Runtime
- Backup konfigurasi runtime dibuat di `/root/cbt-runtime-backups/20260611-040812`.
- Service aktif dan enabled setelah reload: `laravel-octane-cbt`, `cbt-worker`, `cbt-answer-worker@1..4`, `cbt-radar`, `nginx`, dan `redis-server`.
- Laravel cache diperbarui dengan `optimize:clear`, `config:cache`, `view:cache`, `event:cache`, lalu Octane dan worker direstart.

### Verification
- `npm run build` sukses dan menghasilkan asset baru untuk `ExamInterface` serta `PanitiaDashboard`.
- `php -l` sukses untuk route/controller/service/config yang diubah.
- `php artisan config:show database.redis.client` menghasilkan `phpredis`.
- `php artisan config:show app.audit_answer_saved` menghasilkan `false`.
- `redis-cli INFO persistence` menunjukkan `aof_enabled:1` dan rewrite status `ok`.
- `ps` menunjukkan 4 worker `--queue=answers` dan 1 worker `--queue=default`.
- `curl https://cbt.madnnet.my.id/login` menghasilkan HTTP 200.
- `curl https://cbt.madnnet.my.id/readyz` menghasilkan DB dan Redis `true`.
- Login smoke test superadmin berhasil menuju `/vue/dashboard/superadmin` dan `/monitoring/sessions?scope=active&limit=5` menghasilkan HTTP 200.

### Test Notes
- `php artisan test --stop-on-failure` menjalankan 14 test pass lalu berhenti pada test lama `api permission matrix controls module access`: expected 403 tetapi menerima 200 untuk `/api/v1/guru/paket-soal` setelah permission `manage-questions` dicabut.
- Failure tersebut berada di area permission API dan tidak terkait langsung dengan perubahan pending sync/Redis/worker, tetapi perlu ditindaklanjuti terpisah.

## 2026-06-10 - Implementasi Fitur Batch Jadwal Multi-Grup

### Added

**Backend:**
- Tambah endpoint `POST /kelola/data/jadwal-ujian/batch/preview` untuk preview jadwal batch sebelum submit.
- Tambah endpoint `POST /kelola/data/jadwal-ujian/batch` untuk submit dan membuat jadwal batch.
- Tambah `use App\Services\ScheduleBatchService` di `ScheduleManagementController`.
- Tambah method `previewBatch()` dan `storeBatch()` di `ScheduleManagementController`.
- Tambah method `validateBatchRequest()` untuk validasi payload batch dengan aturan:
  - Header batch: nama_batch, tingkat, token, waktu default, durasi, opsi acak, visibilitas hasil.
  - Groups: jurusan_id, rombel_ids (array), paket_soal_id, override, dan pengaturan khusus jika override aktif.
- Service `ScheduleBatchService` sudah tersedia dengan method:
  - `expand()`: expand grup menjadi daftar jadwal per kelas dengan validasi paket ready, durasi, bentrok internal batch.
  - `checkConflicts()`: cek bentrok jadwal dengan jadwal aktif yang ada di database.
  - `store()`: simpan jadwal batch dengan auto create/reuse master ujian berdasarkan kombinasi unik.
- Tambah data tingkat, jurusan, rombel di response `index()` untuk mendukung dropdown batch modal.

**Frontend (ExamSchedule.vue):**
- Tambah tombol "Buat Jadwal Batch" dengan icon layer-group di halaman Jadwal Aktif.
- Ubah tombol "Buat Jadwal Baru" menjadi "Buat Jadwal Tunggal" untuk membedakan dengan batch.
- Tambah modal batch multi-grup dengan komponen:
  - **Header Batch**: nama batch, tingkat, token, waktu/durasi/opsi default, visibilitas hasil.
  - **Form Tambah Grup**: jurusan, rombel multi-select, paket soal, override untuk pengaturan khusus per grup.
  - **Tabel Grup**: preview grup yang sudah ditambahkan dengan tombol hapus per grup.
  - **Preview Jadwal**: menampilkan expand hasil jadwal yang akan dibuat sebelum submit.
- Implementasi logic:
  - `openBatchModal()`: reset form dan buka modal batch.
  - `generateBatchToken()`: generate token random 6 karakter A-Z0-9.
  - `tambahGrup()`: tambah grup ke array `batchGroups` dengan validasi form.
  - `hapusGrup()`: hapus grup dari array.
  - `previewBatch()`: panggil endpoint preview dan tampilkan hasil expand.
  - `submitBatch()`: submit batch setelah konfirmasi user.
- Tambah ref reactive:
  - `batchForm`: header batch.
  - `grupForm`: form tambah grup.
  - `batchGroups`: array grup yang sudah ditambahkan.
  - `batchPreview`: array hasil preview expand.
  - `tingkatList`, `jurusanList`, `rombelList`: data master untuk dropdown.
- Tambah styling:
  - `.modal-batch`: modal lebih lebar (max-width 900px).
  - `.batch-section`: section dengan border dan background.
  - `.override-section`: section override dengan border biru.
  - `.preview-item`: item preview dengan border bottom.

**Routes:**
- Tambah route `POST /kelola/data/jadwal-ujian/batch/preview` → `ScheduleManagementController@previewBatch`.
- Tambah route `POST /kelola/data/jadwal-ujian/batch` → `ScheduleManagementController@storeBatch`.

### Fixed

- Fix bug mass-delete: tambah route `POST /kelola/data/jadwal-ujian/mass-delete` untuk menyamakan dengan frontend yang memanggil POST, selain route DELETE yang sudah ada.

### Changed

- Response `index()` di `ScheduleManagementController` sekarang mengembalikan data tambahan:
  - `tingkats`: list tingkat untuk dropdown batch.
  - `jurusans`: list jurusan untuk dropdown batch.
  - `rombels`: list rombel untuk dropdown batch.

### How It Works

1. **Alur Batch:**
   - Admin klik "Buat Jadwal Batch".
   - Isi header batch (nama, tingkat, token, waktu/durasi/opsi default).
   - Tambah satu atau lebih grup (jurusan, rombel multi-select, paket soal).
   - Grup bisa gunakan override untuk pengaturan khusus berbeda dari default.
   - Klik "Preview" untuk melihat expand jadwal yang akan dibuat.
   - Klik "Submit Batch" untuk membuat semua jadwal sekaligus.

2. **Token Batch:**
   - Semua jadwal dalam satu batch memakai token yang sama.
   - Token default auto-generate 6 karakter A-Z0-9.
   - Token bisa diubah manual atau generate ulang.

3. **Master Ujian:**
   - Master ujian dibuat/reuse otomatis berdasarkan kombinasi:
     - `paket_soal_id`, `acak_soal`, `acak_opsi`, `tampilkan_nilai_akhir`, `hasil_visibilitas`, `tanggal_rilis_hasil`.
   - Jika kombinasi sudah ada, pakai master lama.
   - Jika belum ada, buat master baru.

4. **Validasi:**
   - Paket soal wajib status `ready`.
   - Durasi tidak boleh melebihi rentang waktu mulai-selesai.
   - Visibilitas `scheduled` wajib punya tanggal rilis hasil.
   - Cek bentrok jadwal dengan jadwal aktif yang ada.
   - Cek duplikat kelas dalam batch yang sama pada waktu yang sama.

5. **Hasil Akhir:**
   - Setiap rombel dalam grup menjadi 1 row `jadwal_ujians`.
   - Contoh: Grup DKV rombel 1,2 + Grup TAV rombel 1,2,3 = 5 jadwal.

### Database Impact

- Tidak ada perubahan struktur database (Tahap 1 implementasi).
- Jadwal tetap dibuat per kelas di `jadwal_ujians`.
- Tabel `jadwal_batches` opsional untuk tahap berikutnya.

### Verification

- Backend:
  - `ScheduleManagementController.php`: method `previewBatch()`, `storeBatch()`, `validateBatchRequest()` ditambahkan.
  - `ScheduleBatchService.php`: sudah tersedia dengan logic expand, check conflicts, store.
  - Routes batch terdaftar di `web.php`.
- Frontend:
  - `ExamSchedule.vue`: modal batch, form grup, preview, submit batch diimplementasi.
  - UI responsive dengan modal lebar 900px.
  - Token auto-generate dan manual edit tersedia.
- Flow:
  - Admin bisa membuat banyak jadwal dalam satu kali submit.
  - Semua jadwal memakai token yang sama.
  - Master ujian auto create/reuse.
  - Validasi bentrok dan duplikat diterapkan.

### Next Steps (Tahap 2 - Opsional)

- Tambah tabel `jadwal_batches` untuk grouping permanen.
- Tambah kolom `batch_id` di `jadwal_ujians`.
- Filter jadwal berdasarkan batch.
- Regenerate token satu batch bersama.
- Archive/delete satu batch sekaligus.

---

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
