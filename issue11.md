# issue11.md - Audit Performa dan Risiko Ujian CBT

Dokumen ini adalah hasil revisi audit jalur ujian CBT berdasarkan pemeriksaan kode repo terbaru.

Tanggal revisi: 2026-06-08
Repo: `rfmoscowsind/CBT-SMKN1BLORA`

> Status dokumen: audit teknis dan rencana solusi. Tidak mengubah kode aplikasi.

---

## 1. Ringkasan Eksekutif

Fokus audit ini adalah kesiapan jalur ujian saat dipakai massal, terutama untuk skenario sekitar 1500 siswa.

Prioritas terbesar:

```text
1. Risiko kehilangan jawaban lokal/offline.
2. Ketahanan Redis untuk session, queue, dan pending jawaban.
3. Kapasitas queue worker untuk persist jawaban.
4. Beban write audit_logs per jawaban.
5. Monitoring yang masih mengambil semua sesi dari DB.
6. Risiko stale data jika Octane dipakai karena app()->instance() digunakan di hot path.
```

Temuan dibagi menjadi:

```text
VERIFIED BY CODE      = terbukti dari kode repo.
NEEDS RUNTIME CHECK   = perlu cek server/production.
PARTIAL               = sebagian benar, tetapi perlu penyesuaian solusi.
```

---

## 2. Matriks Temuan

| Severity | Status | Area | Ringkasan |
|---|---|---|---|
| Critical | VERIFIED BY CODE | Offline answer | Jawaban lokal disimpan ke localStorage tetapi belum auto-sync ke `/sync`. |
| High | PARTIAL + RUNTIME | Redis durability | Redis dipakai untuk session/cache/queue/pending answer, tetapi status AOF perlu cek server. |
| High | RUNTIME | Queue worker | Kode dispatch job ke queue `answers`, jumlah worker harus diverifikasi. |
| High | VERIFIED BY CODE | DB write audit | Jawaban yang berhasil dipersist juga insert audit `answer_saved`. |
| Medium/High | VERIFIED BY CODE | Monitoring | `/monitoring/sessions` mengambil semua sesi tanpa filter/limit lalu frontend filter sendiri. |
| Medium | RUNTIME | Index DB | Dugaan index duplikat perlu dibuktikan dari PostgreSQL production. |
| Medium | PARTIAL + RUNTIME | Config/routes cache | Status cache perlu cek `php artisan about`; route closure masih ada. |
| Medium | VERIFIED BY CODE | Octane/container state | Ada `app()->instance()` untuk data hot path. |
| Low/Medium | VERIFIED BY CODE | Redis config | `.env.production.example` pakai phpredis, fallback config masih predis. |

---

# 3. Critical - Jawaban Offline Lokal Belum Disinkronkan Otomatis

## Status

```text
VERIFIED BY CODE
```

## Area

```text
resources/js/pages/Exam/ExamInterface.vue
routes/web.php
app/Http/Controllers/WebController.php
app/Services/ExamService.php
```

## Gejala / Risiko

Frontend menampilkan pesan:

```text
Koneksi Terputus - Jawaban Disimpan Lokal
```

Jawaban memang disimpan ke `localStorage`, tetapi belum ada proses auto-sync yang mengirim jawaban lokal ke endpoint `/ujian/sesi/{id}/sync` ketika koneksi kembali online, saat pindah soal, saat reload, atau sebelum submit final.

Dampak terburuk:

```text
Siswa merasa jawaban tersimpan karena pilihan tampil di UI,
tetapi jawaban yang gagal POST ke server belum tentu masuk DB/Redis,
lalu saat submit localStorage dihapus setelah endpoint selesai berhasil.
```

## Bukti dari Kode

Frontend menyimpan jawaban lokal:

```text
simpanJawabanLokal()
localStorage.setItem(`${STORAGE_KEY}_${nomor}`, ...)
```

Frontend juga memuat kembali jawaban lokal saat soal dibuka:

```text
muatJawabanLokal(nomor)
```

Namun handler online/offline hanya mengubah status:

```text
const updateOnlineStatus = () => { isOnline.value = navigator.onLine; };
window.addEventListener('online', updateOnlineStatus);
window.addEventListener('offline', updateOnlineStatus);
```

Saat submit, frontend langsung memanggil:

```text
POST /ujian/sesi/{session}/selesai
```

lalu menghapus localStorage jika berhasil.

Backend sebenarnya sudah menyediakan endpoint:

```text
POST /ujian/sesi/{id}/sync
```

Dan `WebController::sync()` sudah memanggil:

```text
ExamService::saveMany()
```

Artinya backend sudah siap untuk menerima batch sync, tetapi frontend belum memakainya.

## Dampak Saat Ujian

Risiko paling tinggi terjadi pada kondisi:

```text
1. Wi-Fi siswa putus sesaat.
2. Siswa memilih jawaban saat offline atau POST gagal.
3. Jawaban tersimpan di localStorage.
4. Koneksi kembali online.
5. Tidak ada auto-sync.
6. Siswa klik selesai.
7. Server hanya flush Redis/DB, bukan localStorage browser.
8. Jawaban lokal yang belum terkirim bisa hilang.
```

## Solusi Disarankan

Tambahkan local pending queue di frontend.

Data lokal jangan hanya menyimpan:

```text
jawaban_siswa
ragu
client_updated_at
```

Tetapi simpan payload lengkap:

```text
nomor
soal_hash
tipe
opsi_hash
essay
ragu
client_updated_at
sync_status
retry_count
```

Flow sync:

```text
1. Saat siswa memilih jawaban, simpan ke pending queue lokal.
2. Coba POST /simpan seperti sekarang.
3. Jika gagal, biarkan pending tetap ada.
4. Saat event online, panggil syncPendingAnswers().
5. Sebelum pindah soal, coba flush pending soal berjalan.
6. Sebelum submit, panggil syncPendingAnswers() dan tunggu hasilnya.
7. Jika masih ada pending yang gagal, blok submit dan tampilkan pesan.
8. Setelah server confirm sync, baru hapus pending lokal.
```

Endpoint yang dipakai:

```text
POST /ujian/sesi/{session_hash}/sync
```

Payload konseptual:

```json
{
  "answers": [
    {
      "soal_hash": "...",
      "opsi_hash": "...",
      "essay": null,
      "ragu": false,
      "client_updated_at": "2026-06-08T08:00:00.000Z"
    }
  ]
}
```

## Verifikasi Setelah Perbaikan

```text
1. Matikan koneksi browser.
2. Pilih jawaban beberapa soal.
3. Pastikan jawaban masuk pending queue lokal.
4. Hidupkan koneksi.
5. Pastikan frontend memanggil /sync.
6. Cek Redis queue_jawaban:{session_id} atau DB jawaban_siswas.
7. Klik submit.
8. Pastikan submit diblok jika masih ada pending gagal.
9. Pastikan localStorage hanya dibersihkan setelah server mengonfirmasi sync/submit.
```

---

# 4. High - Redis Durability untuk Session dan Pending Jawaban

## Status

```text
PARTIAL + NEEDS RUNTIME CHECK
```

## Area

```text
.env.production.example
config/database.php
app/Services/ExamService.php
Laravel session/cache/queue
Redis server runtime
```

## Gejala / Risiko

Aplikasi production diarahkan memakai Redis untuk:

```text
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_QUEUE=answers
```

Selain itu, jawaban pending disimpan di key:

```text
queue_jawaban:{session_id}
```

Jika Redis restart/crash dan persistence tidak aktif, risiko:

```text
1. Session siswa bisa hilang.
2. Jawaban pending yang belum dipersist worker bisa hilang.
3. Queue job bisa terdampak.
4. Siswa bisa logout massal atau jawaban terakhir tidak masuk DB.
```

## Hal yang Sudah Terverifikasi dari Kode

Kode memakai Redis untuk buffer jawaban:

```text
Redis::hset("queue_jawaban:{session_id}", question_id, payload)
Redis::expire(..., 86400)
PersistAnswerSnapshot::dispatch(...)->onQueue('answers')
```

Batch sync juga memakai Redis pipeline dan dispatch job:

```text
PersistSessionAnswersSnapshot::dispatch(session_id)->onQueue('answers')
```

## Hal yang Belum Bisa Diverifikasi dari Repo

Status runtime Redis:

```text
aof_enabled
appendonly
appendfsync
maxmemory-policy
```

harus dicek langsung di server production.

## Perintah Verifikasi Runtime

```bash
redis-cli INFO persistence | egrep 'aof_enabled|aof_last_bgrewrite_status|rdb_last_bgsave_status'
redis-cli CONFIG GET appendonly
redis-cli CONFIG GET appendfsync
redis-cli CONFIG GET maxmemory-policy
redis-cli DBSIZE
```

## Solusi Disarankan

Untuk CBT production:

```conf
appendonly yes
appendfsync everysec
save 900 1
save 300 10
save 60 10000
maxmemory-policy noeviction
```

Catatan:

```text
noeviction lebih aman untuk session/jawaban.
Jika Redis penuh, aplikasi akan error terlihat daripada diam-diam membuang data penting.
```

Tambahkan monitoring:

```text
used_memory
connected_clients
instantaneous_ops_per_sec
rejected_connections
evicted_keys
aof_enabled
aof_last_bgrewrite_status
keys queue_jawaban:*
queue length answers
```

---

# 5. High - Kapasitas Queue Worker Jawaban

## Status

```text
NEEDS RUNTIME CHECK
```

## Area

```text
app/Services/ExamService.php
app/Jobs/PersistAnswerSnapshot.php
app/Jobs/PersistSessionAnswersSnapshot.php
systemd / supervisor worker production
```

## Gejala / Risiko

Setiap simpan jawaban memanggil:

```text
PersistAnswerSnapshot::dispatch(session_id, question_id)->onQueue('answers')
```

Batch sync memanggil:

```text
PersistSessionAnswersSnapshot::dispatch(session_id)->onQueue('answers')
```

Job sudah memiliki:

```text
tries = 5
backoff = [3, 10, 30, 60, 120]
timeout 30/60
ShouldBeUniqueUntilProcessing
```

Ini bagus, tetapi jumlah worker production tidak bisa diketahui dari repo.

Jika production hanya menjalankan satu worker:

```text
queue:work redis --queue=answers,default
```

maka antrean bisa menumpuk saat 1500 siswa menjawab bersamaan.

## Dampak Saat Ujian

```text
1. Jawaban masuk Redis.
2. Job persist antre.
3. Submit harus flush pending.
4. Jika antrean panjang, submit/hasil bisa lambat.
5. Jika Redis terganggu sebelum flush, pending answer berisiko.
```

## Solusi Disarankan

Pisahkan worker:

```text
answers queue  : khusus jawaban, prioritas tinggi
default queue  : pekerjaan umum
reports queue  : PDF/export/laporan
imports queue  : import Excel
```

Contoh target awal:

```text
answers workers : 4 - 8 worker
default workers : 1 - 2 worker
reports workers : 1 worker, boleh distop saat ujian
```

Command konsep:

```bash
php artisan queue:work redis --queue=answers --sleep=1 --tries=5 --timeout=60 --max-jobs=1000 --max-time=3600
php artisan queue:work redis --queue=default --sleep=2 --tries=3 --timeout=120 --max-jobs=500 --max-time=3600
```

Verifikasi runtime:

```bash
systemctl status cbt-worker*
ps aux | grep 'queue:work'
php artisan queue:monitor redis:answers --max=100
redis-cli LLEN queues:answers
```

---

# 6. High - Audit Write per Jawaban

## Status

```text
VERIFIED BY CODE
```

## Area

```text
app/Services/ExamService.php
jawaban_siswas
audit_logs
```

## Gejala / Risiko

Setiap jawaban yang berhasil dipersist akan mencatat audit:

```text
persistPreparedAnswer()
  -> persistAnswerIfNewer()
  -> recordAnswerAuditSafely()
  -> auditAnswerSaved()
  -> insert audit_logs action answer_saved
```

Ini tidak selalu terjadi persis saat klik, karena jawaban dibuffer di Redis dan dipersist oleh worker/flush. Namun secara total, setiap jawaban yang berhasil masuk DB tetap berpotensi menghasilkan 1 row audit.

## Dampak Saat Ujian

Estimasi kasar:

```text
1500 siswa x 50 soal = 75.000 jawaban
75.000 jawaban = 75.000 upsert jawaban_siswas + 75.000 insert audit_logs
```

Jika siswa mengubah jawaban beberapa kali, jumlah audit bisa lebih besar.

Dampaknya:

```text
1. Write DB meningkat tajam.
2. Index audit_logs ikut bertambah.
3. Storage WAL PostgreSQL meningkat.
4. Latency persist jawaban bisa naik saat puncak.
```

## Solusi Disarankan

Jangan matikan audit total. Ubah menjadi audit yang lebih ringan.

Pilihan solusi:

```text
A. Audit hanya event penting:
   - exam_started
   - answer_persist_failed
   - submit
   - auto_submit
   - reset
   - device_locked
   - sync_failed

B. Audit jawaban dibuat batch/ringkas:
   - answer_saved_summary per sesi per interval
   - contoh: total_saved, total_changed, last_question_id

C. Audit detail hanya saat mode debug/audit khusus.
```

Rekomendasi awal:

```text
Untuk production ujian besar, jangan insert audit_logs untuk setiap answer_saved normal.
Simpan detail jawaban cukup di jawaban_siswas.
Audit detail dipakai hanya untuk failure/security event.
```

## Verifikasi Setelah Perbaikan

```sql
SELECT action, count(*)
FROM audit_logs
WHERE created_at >= now() - interval '1 hour'
GROUP BY action
ORDER BY count(*) DESC;
```

---

# 7. Medium/High - Monitoring Sessions Mengambil Semua Sesi

## Status

```text
VERIFIED BY CODE
```

## Area

```text
routes/web.php
resources/js/pages/Dashboard/PengawasDashboard.vue
resources/js/pages/Monitoring/LiveRadar.vue
```

## Gejala / Risiko

Endpoint:

```text
GET /monitoring/sessions
```

mengambil semua sesi:

```text
DB::table('sesi_ujians as s')
  ->join(...)
  ->orderByDesc('s.id')
  ->get()
```

Lalu menghitung jawaban dan total soal untuk semua session id yang diambil.

Frontend pengawas memanggil endpoint ini setiap 3 detik, lalu baru memfilter status `aktif` dan `terkunci` di browser.

LiveRadar berbeda: ia memakai endpoint `/monitoring/radar` yang membaca Redis key `cbt:radar:live`, polling 2 detik. Itu lebih ringan dibanding `/monitoring/sessions`.

## Dampak Saat Ujian

Jika tabel `sesi_ujians` makin besar:

```text
1. Query mengambil banyak sesi yang sebenarnya sudah selesai/lama.
2. Query jawaban_siswas dan sesi_ujian_soals ikut whereIn banyak id.
3. Polling setiap 3 detik bisa membebani DB utama.
4. Pengawas bisa memperberat sistem saat ujian berlangsung.
```

## Solusi Disarankan

Ubah endpoint `/monitoring/sessions` agar server-side filter:

```text
status IN ('aktif', 'terkunci')
atau sesi recent dalam 30-60 menit terakhir
limit maksimal, misal 500/1000
filter by jadwal aktif hari ini
```

Tambahkan cache pendek:

```text
Cache::remember('monitoring:sessions:active', 2-5 detik, fn() => query)
```

Atau gunakan Redis radar sebagai sumber utama:

```text
/monitoring/radar -> Redis cbt:radar:live
```

Rekomendasi final:

```text
Dashboard pengawas pakai data Redis/radar untuk live view.
Endpoint DB hanya untuk detail/rekap, bukan polling utama.
```

---

# 8. Medium - Duplikasi Index di Tabel Hot Path

## Status

```text
NEEDS RUNTIME CHECK
```

## Area

```text
PostgreSQL production
sesi_ujians
sesi_ujian_soals
jawaban_siswas
bank_soals
opsi_jawabans
audit_logs
```

## Catatan

Dugaan index duplikat tidak bisa dipastikan hanya dari repo karena index aktif tergantung hasil migrasi production.

## Risiko

Jika ada index redundant di tabel hot path:

```text
1. Insert sesi lebih berat.
2. Insert/update jawaban lebih berat.
3. Insert audit_logs lebih berat.
4. Vacuum/autovacuum bekerja lebih banyak.
5. WAL meningkat.
```

## Query Verifikasi

```sql
SELECT schemaname, tablename, indexname, indexdef
FROM pg_indexes
WHERE tablename IN (
  'sesi_ujians',
  'sesi_ujian_soals',
  'jawaban_siswas',
  'bank_soals',
  'opsi_jawabans',
  'audit_logs'
)
ORDER BY tablename, indexname;
```

Cek index yang sama/overlap:

```sql
SELECT
  t.relname AS table_name,
  i.relname AS index_name,
  pg_get_indexdef(ix.indexrelid) AS indexdef
FROM pg_class t
JOIN pg_index ix ON t.oid = ix.indrelid
JOIN pg_class i ON i.oid = ix.indexrelid
WHERE t.relname IN (
  'sesi_ujians',
  'sesi_ujian_soals',
  'jawaban_siswas',
  'bank_soals',
  'opsi_jawabans',
  'audit_logs'
)
ORDER BY t.relname, i.relname;
```

## Solusi Disarankan

Jika terbukti redundant:

```sql
DROP INDEX CONCURRENTLY nama_index;
```

Jangan hapus index yang dipakai oleh constraint unik/foreign key tanpa validasi.

---

# 9. Medium - Config/Route Cache Production

## Status

```text
PARTIAL + NEEDS RUNTIME CHECK
```

## Area

```text
routes/web.php
production deployment
php artisan about
```

## Catatan dari Kode

Route closure masih ada di `routes/web.php`, contoh:

```text
/healthz
/readyz
/media/soal-images/{file}
/monitoring/radar
/monitoring/stats
/monitoring/sessions
/kelola/guru/jadwal-terkait
/auth/user
/vue/{any}
```

Jika `php artisan route:cache` gagal karena closure, route closure perlu dipindah ke controller.

Status cache production harus dicek runtime:

```bash
php artisan about
php artisan route:list --except-vendor
```

## Solusi Disarankan

Minimal saat deploy:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
php artisan event:cache
```

Untuk route cache:

```bash
php artisan route:cache
```

Jika gagal:

```text
1. Pindahkan closure route ke controller.
2. Ulangi php artisan route:cache.
```

Catatan:

```text
Config cache lebih prioritas daripada route cache.
Route cache bagus, tetapi jangan dipaksakan jika closure masih banyak dan belum dipindah.
```

---

# 10. Medium - Container Cache Berpotensi Stale di Octane

## Status

```text
VERIFIED BY CODE
```

## Area

```text
app/Services/ExamService.php
app/Http/Controllers/WebController.php
Octane / long-running worker
```

## Gejala / Risiko

Kode memakai `app()->instance()` untuk menyimpan data hot path.

Contoh pola:

```text
if (app()->bound($key)) return app($key);
app()->instance($key, $items);
```

Titik yang terlihat:

```text
ExamService::remaining()
WebController::sessionItems()
```

Di PHP-FPM, risiko rendah karena proses request selesai dan container hilang.

Di Octane/long-running worker, data yang dimasukkan ke container bisa berumur lebih panjang dari satu request jika tidak dibersihkan oleh lifecycle worker. Ini berpotensi stale terutama setelah:

```text
1. reset sesi
2. edit jadwal
3. archive/delete jadwal
4. regenerate sesi
5. ubah urutan soal
```

## Dampak Saat Ujian

```text
1. Sisa waktu bisa memakai data jadwal lama.
2. Daftar soal sesi bisa stale setelah reset.
3. Data request sebelumnya bisa terbaca ulang oleh request berikutnya pada worker yang sama.
```

## Solusi Disarankan

Hindari `app()->instance()` untuk cache data ujian.

Ganti ke:

```text
1. local variable biasa dalam method/request
2. Cache::remember dengan TTL eksplisit dan key jelas
3. Redis/cache yang diforget saat reset/edit jadwal
4. request-scoped helper yang benar-benar dibersihkan per request
```

Rekomendasi awal:

```text
- WebController::sessionItems() cukup query langsung atau pakai property lokal request.
- ExamService::remaining() jangan app()->instance jadwal; gunakan Cache::remember TTL pendek atau query langsung karena ringan.
```

---

# 11. Low/Medium - Fallback Redis Config Tidak Selaras

## Status

```text
VERIFIED BY CODE
```

## Area

```text
.env.production.example
config/database.php
```

## Gejala / Risiko

`.env.production.example` memakai:

```text
REDIS_CLIENT=phpredis
```

Tetapi fallback di `config/database.php` masih:

```text
'client' => env('REDIS_CLIENT', 'predis')
```

Jika `.env` tidak terbaca, config cache salah, atau env variable kosong, aplikasi bisa fallback ke `predis` diam-diam.

## Solusi Disarankan

Jika keputusan production adalah PhpRedis, ubah fallback menjadi:

```php
'client' => env('REDIS_CLIENT', 'phpredis'),
```

Verifikasi:

```bash
php artisan tinker
config('database.redis.client')
```

---

# 12. Temuan Tambahan - Endpoint Batch Jadwal Sudah Ada di Route

## Status

```text
INFO
```

Pemeriksaan route terbaru menunjukkan endpoint batch jadwal sudah ada:

```text
POST /kelola/data/jadwal-ujian/batch/preview
POST /kelola/data/jadwal-ujian/batch
```

Dan route `mass-delete` sekarang juga sudah ada:

```text
POST /kelola/data/jadwal-ujian/mass-delete
DELETE /kelola/data/jadwal-ujian
```

Artinya catatan lama tentang mismatch route mass-delete sudah tidak relevan untuk repo terbaru.

Tetap disarankan:

```text
Aksi default untuk banyak jadwal sebaiknya bulk archive, bukan mass delete permanen.
```

---

# 13. Prioritas Perbaikan

## P0 - Wajib sebelum ujian besar

```text
1. Implement auto-sync pending localStorage ke endpoint /sync.
2. Blok submit jika pending lokal belum tersinkron.
3. Pastikan Redis AOF aktif dan maxmemory-policy aman.
4. Pastikan worker queue answers cukup.
```

## P1 - Sangat disarankan

```text
1. Kurangi audit_logs per jawaban normal.
2. Optimasi /monitoring/sessions agar server-side filter/cached.
3. Hilangkan app()->instance() di hot path ujian jika Octane dipakai.
```

## P2 - Setelah stabil

```text
1. Inventaris dan rapikan index PostgreSQL.
2. Pindahkan route closure ke controller untuk route cache penuh.
3. Samakan Redis fallback ke phpredis.
4. Tambahkan dashboard health Redis persistence/queue depth.
```

---

# 14. Checklist Verifikasi Production

## Redis

```bash
redis-cli INFO persistence | egrep 'aof_enabled|aof_last_bgrewrite_status|rdb_last_bgsave_status'
redis-cli CONFIG GET appendonly
redis-cli CONFIG GET appendfsync
redis-cli CONFIG GET maxmemory-policy
redis-cli INFO memory | egrep 'used_memory_human|maxmemory_human|mem_fragmentation_ratio'
```

## Queue

```bash
ps aux | grep 'queue:work'
systemctl status cbt-worker*
redis-cli LLEN queues:answers
php artisan queue:monitor redis:answers --max=100
```

## Laravel cache

```bash
php artisan about
php artisan config:show cache.default
php artisan config:show queue.default
php artisan config:show session.driver
php artisan config:show database.redis.client
```

## Monitoring DB load

```sql
SELECT state, count(*)
FROM pg_stat_activity
WHERE datname = current_database()
GROUP BY state;
```

## Audit log volume

```sql
SELECT action, count(*)
FROM audit_logs
WHERE created_at >= now() - interval '1 hour'
GROUP BY action
ORDER BY count(*) DESC;
```

## Pending jawaban

```bash
redis-cli --scan --pattern 'queue_jawaban:*' | wc -l
```

---

# 15. Kesimpulan

Kondisi repo terbaru sudah lebih baik daripada rencana awal karena:

```text
1. Backend endpoint /sync sudah tersedia.
2. ExamService sudah punya saveMany() untuk batch sync.
3. Jawaban sudah dibuffer Redis dan dipersist via queue answers.
4. Job persist punya retry/backoff/timeout.
5. Route batch jadwal sudah tersedia.
```

Namun risiko utama masih ada di frontend:

```text
localStorage hanya dipakai untuk restore tampilan,
belum menjadi pending queue yang disinkronkan otomatis ke server.
```

Maka perbaikan paling penting adalah:

```text
Implementasi pending local answer sync di ExamInterface.vue.
```

Setelah itu, pastikan sisi server production kuat:

```text
Redis durable, worker answers cukup, monitoring tidak membebani DB, dan audit jawaban tidak terlalu boros write.
```
