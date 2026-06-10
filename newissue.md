# Audit Performance — Hambatan Saat Ujian Berlangsung

**Tanggal Audit**: 2026-06-09  
**Konteks**: 1500+ siswa concurrent, ujian berlangsung real-time  
**Status Production**: `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `SESSION_DRIVER=redis` — sudah benar

---

## CRITICAL — Bisa bikin lag/gagal saat ujian

### 1. Device Fingerprint = DB Query di Setiap Request

**File**: `app/Http/Controllers/Concerns/HandlesDeviceFingerprints.php:350`

```php
$freshUser = User::query()->find($user->id);
```

`enforceDeviceFingerprintForUser()` memanggil `User::query()->find()` di **setiap request** — ping, save, soal, sync, flag. Dengan 1500 siswa × ping setiap 10 detik = **150 query/detik** tambahan ke tabel `users` yang seharusnya tidak perlu.

Belum termasuk `recordDeviceFingerprintHistory()` yang melakukan **DB insert** per request dan `deviceLockEnabled()` yang hit cache tapi bisa fallback ke DB.

**Dampak**: DB overload di jam-jam sibuk ujian, response time naik signifikan.

**Fix**: Cache `freshUser` di request-scoped storage (misal `app()->instance()`), atau skip re-fetch jika `$user` dari auth guard sudah fresh.

---

### 2. RadarWorker Infinite Loop Tanpa Batas

**File**: `app/Console/Commands/RadarWorker.php:17`

```php
while (true) {
    // Join 5 tabel (sesi_ujians, users, kelas_aktifs, jadwal_ujians, master_ujians)
    // 2x GROUP BY aggregate queries
    // sleep(2);
}
```

Tidak ada:
- Max iterations / time limit
- Graceful shutdown signal
- Memory usage monitoring
- Supervisor/restart mechanism yang eksplisit

**Dampak**:
- Memory leak seiring waktu (PHP process tidak pernah mati)
- Jika crash, monitoring radar mati total tanpa notifikasi
- Query join 5 tabel + 2 aggregate setiap 2 detik = beban DB konstan
- Dengan 1500 sesi aktif, query `WHERE IN (1500 IDs)` jadi lambat

**Fix**: Tambahkan max iterations, signal handler (`pcntl_signal`), supervisor config dengan `max_memory` dan `autorestart`, pertimbangkan pindah logic ke scheduled command + Redis pub/sub.

---

### 3. Audit Log Insert Synchronous di Hot Path

**File**: `app/Services/ExamService.php:482-497`

```php
private function auditAnswerSaved(int $sessionId, int $soalId, array $answer): void
{
    DB::table('audit_logs')->insert([...]);
}
```

Dipanggil dari `flushOne()` dan `flushAll()` yang dijalankan oleh job `PersistAnswerSnapshot`. Setiap jawaban yang di-persist = 1 synchronous DB insert.

**Estimasi beban**: 1500 siswa × 50 soal = **75.000 inserts** selama sesi ujian, plus audit log dari login, event, submit.

**Dampak**: Write contention di tabel `audit_logs`, memperlambat flush job yang seharusnya cepat.

**Fix**: Dispatch audit log sebagai job async terpisah, atau batch insert setiap N entries menggunakan Redis list + periodic flush.

---

### 4. Redis = Single Point of Failure

**File**: `app/Http/Controllers/WebController.php:263-273`, `app/Services/ExamService.php:279`

```php
// WebController::ping()
Redis::setex($redisKey, 45, now()->timestamp);

// ExamService::save()
Redis::hset("queue_jawaban:{$s->id}", (string) $q->id, json_encode($payload));
```

Tidak ada try-catch atau fallback. Jika Redis down:
- `save()` → HTTP 500, jawaban siswa hilang
- `ping()` → HTTP 500, status online mati
- `flushOne()` → tidak bisa baca queue, jawaban tidak ter-persist
- `submit()` → `flushAll()` gagal, nilai akhir tidak terhitung

**Dampak**: Redis outage = **seluruh sistem ujian lumpuh**.

**Fix**: Wrap Redis calls di try-catch, fallback ke direct DB write saat Redis tidak tersedia. Tambahkan circuit breaker pattern.

---

### 5. N+1 Query di Soal Endpoint

**File**: `app/Http/Controllers/WebController.php:399-407`, `app/Http/Controllers/ApiController.php:186-190`

```php
$item = DB::table('sesi_ujian_soals')->where([...])->first();           // Query 1
$question = DB::table('bank_soals')->where('id', $item->bank_soal_id)->first(); // Query 2
$answer = DB::table('jawaban_siswas')->where([...])->first();           // Query 3
$options = $this->questionOptions($question, $item);                    // Query 4
$pending = $this->pendingAnswer(...);                                   // Redis call
```

4 DB queries + 1 Redis call per request soal. Rate limit 300 request/menit = hingga **1200 queries/menit** hanya dari endpoint soal.

**Dampak**: DB connection pool habis saat peak load.

**Fix**: Cache `bank_soals` + `opsi_jawabans` per sesi (soal tidak berubah selama ujian), gunakan `Cache::remember()` dengan TTL = durasi ujian.

---

### 6. Sync Endpoint Loop Tanpa Batch

**File**: `app/Http/Controllers/ApiController.php:115-137`, `app/Http/Controllers/WebController.php:218-238`

```php
foreach ($request->input('answers', []) as $answer) {
    $this->exams->save(...);  // Redis hset + dispatch job
    DB::table('sesi_ujian_soals')->where([...])->update(['ditandai' => ...]); // DB update
}
```

Sync 10 jawaban = 10× Redis write + 10× job dispatch + 10× DB update `ditandai`. Seharusnya bisa batch.

**Dampak**: Spike latency saat siswa reconnect dan sync banyak jawaban sekaligus setelah offline.

**Fix**: Batch Redis `HMSET`, single DB update untuk `ditandai` dengan `CASE WHEN`, dispatch 1 job untuk flush semua.

---

### 7. PersistAnswerSnapshot Tanpa Retry Policy

**File**: `app/Jobs/PersistAnswerSnapshot.php:13-31`

```php
class PersistAnswerSnapshot implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    public int $uniqueFor = 30;
    // TIDAK ADA:
    // public int $tries = 5;
    // public array $backoff = [3, 10, 30, 60, 120];
    // public int $maxExceptions = 5;
}
```

Jika job gagal (DB timeout, deadlock, Redis blip), langsung masuk `failed_jobs`. Jawaban siswa bisa **hilang permanen** karena data sudah dihapus dari Redis oleh `forgetIfCurrent()`.

**Dampak**: Kehilangan jawaban siswa — ini adalah failure paling mahal di sistem CBT.

**Fix**: Tambahkan `$tries`, `$backoff`, dan `failed()` handler yang menulis ke emergency log/tabel terpisah.

---

## MODERATE — Bikin lambat tapi tidak crash

### 8. `sessionItems()` Query Berulang per Request

**File**: `app/Http/Controllers/WebController.php:447-450`

```php
private function sessionItems(int $sessionId)
{
    return DB::table('sesi_ujian_soals')
        ->where('sesi_ujian_id', $sessionId)
        ->orderBy('nomor_soal')
        ->get();
}
```

Dipanggil di `examPayload()`, `examViewData()`, `navigation()`, `questionList()` — dalam satu request yang sama, query ini bisa dieksekusi 2-3×.

**Fix**: Resolve sekali di awal method, pass sebagai parameter.

---

### 9. Static `$cache` di ExamService::remaining() Tidak Octane-Safe

**File**: `app/Services/ExamService.php:208-222`

```php
public function remaining(object $s): int
{
    static $cache = [];
    $key = 'jadwal_' . $s->jadwal_ujian_id;
    if (!array_key_exists($key, $cache)) {
        $cache[$key] = DB::table('jadwal_ujians')->find($s->jadwal_ujian_id);
    }
    // ...
}
```

Dengan Laravel Octane (yang terdaftar di `composer.json`), `static` variable persist antar request di worker yang sama. Ini menyebabkan:
- Data bocor antar siswa (jadwal siswa A terbaca oleh siswa B)
- Stale data jika jadwal diupdate admin saat ujian berlangsung

**Fix**: Ganti dengan `app()->scoped()` atau request-scoped cache.

---

### 10. `questionOptions()` Sort O(n²)

**File**: `app/Http/Controllers/WebController.php:436`

```php
$query->whereIn('id', $order)->get()
    ->sortBy(fn ($option) => array_search($option->id, $order, true))
    ->values()
```

`array_search()` = O(n) dipanggil n kali di `sortBy()` = O(n²). Dengan 5 opsi ini tidak terasa, tapi untuk soal dengan banyak opsi atau batch rendering, ini tidak efisien.

**Fix**: Gunakan `array_flip($order)` untuk O(1) lookup.

---

### 11. `studentProfile()` Query Tanpa Cache

**File**: `app/Http/Controllers/WebController.php:565-579`

```php
$row = DB::table('users as u')
    ->leftJoin('kelas_aktifs as k', 'k.id', '=', 'u.kelas_aktif_id')
    ->leftJoin('jurusans as j', 'j.id', '=', 'k.jurusan_id')
    ->where('u.id', $user->id)
    ->first([...]);
```

Dipanggil di `dashboard()` dan `examPayload()` (setiap request soal). Profile siswa tidak berubah selama ujian.

**Fix**: Cache per user dengan TTL = durasi ujian, atau resolve sekali di middleware.

---

### 12. Dashboard Siswa — 4-Table Join + 2 Aggregate Queries

**File**: `app/Http/Controllers/WebController.php:581-644`

```php
// Main query: join jadwal_ujians, master_ujians, paket_soals, mata_pelajarans, jadwal_ujian_kelas, sesi_ujians
// Plus: DB::table('bank_soals')->whereIn(...)->groupBy(...) x2
```

Total 3 queries yang cukup berat setiap siswa buka dashboard. Saat 1500 siswa buka dashboard bersamaan sebelum ujian, ini jadi beban.

**Fix**: Cache dashboard data per kelas dengan TTL 30-60 detik.

---

### 13. Frontend Fatal Error Tidak Auto-Retry

**File**: `resources/js/main.js:28-51`

```javascript
const showFatalError = (error) => {
    // Hanya tampilkan tombol "Muat Ulang" manual
}
```

Jika siswa dapat error saat ujian (network blip, server hiccup), mereka harus refresh manual. Di situasi ujian, ini bikin panik dan buang waktu.

**Fix**: Tambahkan exponential backoff auto-retry (max 3×) sebelum tampilkan overlay. Tampilkan countdown "Mencoba ulang dalam 3 detik..."

---

## LOW — Perlu diperbaiki tapi tidak urgent

### 14. RadarWorker Query Join 5 Tabel Setiap 2 Detik

**File**: `app/Console/Commands/RadarWorker.php:21-55`

Query berat dijalankan terus-menerus setiap 2 detik. Saat sesi aktif sedikit ini tidak masalah, tapi saat ratusan sesi aktif, aggregate queries jadi lambat.

---

### 15. Monitoring Endpoint PgBouncer Spawn `proc_open()`

**File**: `routes/web.php:349`

Monitoring PgBouncer menjalankan `psql` via `proc_open()` setiap kali endpoint dipanggil. Ini blocking dan lambat.

---

### 16. `Schema::hasTable()` Dipanggil Runtime

**File**: `HandlesDeviceFingerprints.php:93,139`

```php
if (! Schema::hasTable('app_settings')) { ... }
if (! Schema::hasTable('device_fingerprint_histories')) { ... }
```

`Schema::hasTable()` melakukan query ke `information_schema` setiap kali. Seharusnya di-cache atau dicek sekali saat deploy.

---

### 17. Octane + Static Variable Data Leak

**File**: `app/Services/ExamService.php:208`

Seperti disebutkan di #9, static variable di Octane worker bisa bocor antar request. Ini bug yang bisa menyebabkan siswa melihat sisa waktu ujian siswa lain.

---

### 18. Tidak Ada Health Check Endpoint

Tidak ada endpoint sederhana untuk load balancer / uptime monitor. Monitoring endpoint yang ada (`/monitoring/radar`, `/monitoring/stats`) terlalu berat untuk health check.

---

## Ringkasan Prioritas

| Prioritas | # | Issue | Effort | Impact |
|-----------|---|-------|--------|--------|
| **P0** | 7 | PersistAnswerSnapshot retry policy | 10 menit | Mencegah kehilangan jawaban |
| **P0** | 9 | Static cache Octane-unsafe | 15 menit | Mencegah data bocor antar siswa |
| **P0** | 4 | Redis fallback/circuit breaker | 2 jam | Mencegah total outage |
| **P1** | 1 | Device fingerprint DB overhead | 1 jam | Kurangi ~150 query/detik |
| **P1** | 3 | Audit log async | 1 jam | Kurangi write contention |
| **P1** | 5 | N+1 soal endpoint + cache | 2 jam | Kurangi ribuan query/menit |
| **P1** | 6 | Sync batch optimization | 2 jam | Kurangi latency saat reconnect |
| **P2** | 2 | RadarWorker graceful shutdown | 1 jam | Stabilitas monitoring |
| **P2** | 8 | sessionItems dedup | 30 menit | Kurangi query duplikat |
| **P2** | 10 | questionOptions sort fix | 15 menit | Micro-optimization |
| **P2** | 11 | studentProfile cache | 30 menit | Kurangi query berulang |
| **P2** | 12 | Dashboard cache per kelas | 1 jam | Kurangi beban saat peak |
| **P2** | 13 | Frontend auto-retry | 1 jam | UX siswa saat error |
| **P3** | 14-18 | Low priority items | varies | Maintainability |

**Total effort untuk P0+P1**: ~8-10 jam kerja  
**Total effort untuk semua fix**: ~15-20 jam kerja
