# Review `newissue.md` dan Rencana Fix

Tanggal review: 2026-06-09

## Kesimpulan Singkat

Mayoritas poin di `newissue.md` benar sebagai risiko performa/ketahanan produksi, terutama:

- `PersistAnswerSnapshot` belum punya retry/backoff eksplisit.
- `ExamService::remaining()` memakai `static $cache`, tidak ideal untuk worker long-running/Octane.
- Banyak pemanggilan Redis di hot path tidak punya fallback.
- `RadarWorker` memang infinite loop tanpa graceful shutdown.
- Endpoint soal/sync masih terlalu banyak operasi per request untuk kondisi 1500+ siswa.

Namun ada beberapa koreksi:

- Device fingerprint history tidak insert setiap request tanpa batas, karena ada dedup `Cache::add(..., 60 detik)`. Yang tetap valid adalah `User::query()->find($user->id)` terjadi setiap enforce fingerprint.
- Issue "N+1 soal endpoint" bukan N+1 klasik untuk satu endpoint soal, tetapi tetap 3 query DB + 1 query opsi + Redis read per request soal.
- `static $cache` di `remaining()` bukan bocor data siswa langsung, karena key-nya `jadwal_ujian_id`, bukan user/session. Risiko yang benar: stale jadwal pada Octane/worker long-running dan cache lintas request.
- `PersistAnswerSnapshot` gagal tidak selalu langsung menghapus jawaban Redis. Redis baru dihapus setelah persist sukses/flush path tertentu. Tapi tanpa retry eksplisit, jawaban bisa tertahan, masuk `failed_jobs`, dan berisiko hilang saat TTL Redis habis atau saat reset sesi.
- `composer.json` memang memasang `laravel/octane`, tetapi repo tidak terlihat punya `config/octane.php`. Jadi risiko Octane perlu dianggap valid jika production menjalankan Octane, bukan otomatis pasti sedang aktif.

## Prioritas Fix yang Disarankan

### P0. Tambahkan Retry Policy untuk `PersistAnswerSnapshot`

Status audit: benar.

File: `app/Jobs/PersistAnswerSnapshot.php`

Fix minimal:

```php
class PersistAnswerSnapshot implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 30;
    public int $tries = 5;
    public int $maxExceptions = 5;
    public array $backoff = [3, 10, 30, 60, 120];
    public int $timeout = 30;

    public function failed(\Throwable $exception): void
    {
        \Illuminate\Support\Facades\Log::critical('PersistAnswerSnapshot failed', [
            'session_id' => $this->sessionId,
            'question_id' => $this->questionId,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

Tambahan yang lebih aman:

- Buat tabel `answer_persist_failures` atau pakai `audit_logs` action `answer_persist_failed`.
- Pastikan supervisor queue production tidak memakai `--tries=1`.
- Jalankan worker khusus: `php artisan queue:work redis --queue=answers --tries=5 --backoff=3 --timeout=60 --max-jobs=1000 --max-time=3600`.

### P0. Ganti `static $cache` di `ExamService::remaining()`

Status audit: benar untuk risiko long-running worker, tapi bukan bocor data siswa langsung.

File: `app/Services/ExamService.php`

Fix minimal request-scoped:

```php
public function remaining(object $s): int
{
    $key = 'exam.schedule.remaining.' . $s->jadwal_ujian_id;

    $j = app()->has($key)
        ? app($key)
        : tap(DB::table('jadwal_ujians')->find($s->jadwal_ujian_id), fn ($row) => app()->instance($key, $row));

    $deadline = min(
        now()->parse($j->waktu_selesai),
        now()->parse($s->waktu_login)->addMinutes($j->durasi_menit)
    );

    return max(0, now()->diffInSeconds($deadline, false));
}
```

Alternatif lebih stabil:

- Inject repository kecil `ExamScheduleCache`.
- Gunakan `Cache::remember("jadwal_ujian:{$id}:remaining:v1", 60, ...)` jika jadwal jarang berubah.
- Saat admin update jadwal, panggil `Cache::forget(...)`.

### P0. Bungkus Redis Hot Path dengan Fallback

Status audit: benar.

File utama:

- `app/Services/ExamService.php`
- `app/Http/Controllers/WebController.php`
- `app/Http/Controllers/ApiController.php`
- `app/Console/Commands/RadarWorker.php`

Masalah:

- `save()` bergantung pada `Redis::hset()` dan queue dispatch.
- `ping()` bisa HTTP 500 kalau Redis down.
- `pendingAnswer()` / `pendingAnswers()` bisa merusak render soal jika Redis bermasalah.
- `submit()` memanggil `flushAll()` yang membaca Redis.

Fix minimal:

1. Buat service `AnswerBuffer` untuk semua operasi Redis jawaban.
2. Di `save()`, jika Redis gagal, langsung persist ke DB via method existing `persistAnswerIfNewer()`.
3. Di `ping()`, jika Redis gagal, update DB `last_seen_at` langsung dengan throttle berbasis cache/DB atau cukup update DB sebagai fallback darurat.
4. Di `pendingAnswer()`, jika Redis gagal, return `null` dan log warning supaya soal tetap bisa dibuka.

Contoh pola:

```php
try {
    Redis::hset("queue_jawaban:{$s->id}", (string) $q->id, json_encode($payload));
    Redis::expire("queue_jawaban:{$s->id}", 86400);
    PersistAnswerSnapshot::dispatch($s->id, $q->id)->onQueue('answers');
} catch (\Throwable $e) {
    Log::error('Redis answer buffer unavailable; persisting directly', [
        'session_id' => $s->id,
        'question_id' => $q->id,
        'error' => $e->getMessage(),
    ]);

    $score = 0;
    $status = 'pending_manual';
    if ($q->tipe_soal === 'PG') {
        $ok = DB::table('opsi_jawabans')
            ->where(['id' => $opsiId, 'bank_soal_id' => $q->id, 'is_benar' => true])
            ->exists();
        $score = $ok ? $q->bobot_nilai : 0;
        $status = 'auto_scored';
    }

    $this->persistAnswerIfNewer((int) $s->id, (int) $q->id, $payload, $q, $score, $status);
}
```

Catatan: jangan jadikan fallback direct DB sebagai mode normal, karena ini mengembalikan write pressure ke DB. Ini hanya jalur darurat saat Redis down.

### P1. Kurangi Query Device Fingerprint per Request

Status audit: sebagian benar.

File: `app/Http/Controllers/Concerns/HandlesDeviceFingerprints.php`

Yang benar:

- `User::query()->find($user->id)` memang dipanggil setiap enforce fingerprint.
- `Schema::hasTable()` ada di `deviceLockEnabled()` dan `recordDeviceFingerprintHistory()`, meski `deviceLockEnabled()` dibungkus cache 30 detik.

Yang perlu diluruskan:

- `recordDeviceFingerprintHistory()` punya dedup 60 detik, jadi tidak selalu insert per request yang identik.

Fix:

- Cache user fresh per request dengan `app()->instance("device-fingerprint-user:{$id}", $freshUser)`.
- Hanya re-fetch jika perlu membaca `is_device_locked` yang harus real-time.
- Cache hasil `Schema::hasTable()` minimal 5-10 menit atau pindahkan asumsi tabel ke konfigurasi setelah migration stabil.
- Pertimbangkan `select()` kolom yang dibutuhkan saja: `id`, `role`, `is_device_locked`, `device_fingerprint`.

### P1. Audit Log Jawaban Jangan Sync di Hot Path

Status audit: benar.

File: `app/Services/ExamService.php`

Masalah:

- `auditAnswerSaved()` insert ke `audit_logs` setelah persist jawaban.
- Untuk 75.000 jawaban, ini menambah 75.000 write audit.

Fix:

- Buat job `WriteAuditLog`.
- Atau masukkan audit ke Redis stream/list lalu flush batch per 1-5 detik.
- Jika audit harus tetap sinkron, minimal bungkus `auditAnswerSaved()` dengan try-catch supaya kegagalan audit tidak menggagalkan persist jawaban.

Fix minimal yang aman:

```php
try {
    $this->auditAnswerSaved($sessionId, $soalId, $a);
} catch (\Throwable $e) {
    Log::warning('Answer audit log failed', [
        'session_id' => $sessionId,
        'question_id' => $soalId,
        'error' => $e->getMessage(),
    ]);
}
```

### P1. Optimasi Endpoint Soal

Status audit: benar sebagai hot path, tapi bukan N+1 klasik.

File:

- `app/Http/Controllers/WebController.php`
- `app/Http/Controllers/ApiController.php`

Saat ini per request soal:

- Ambil `sesi_ujian_soals`.
- Ambil `bank_soals`.
- Ambil `jawaban_siswas`.
- Ambil `opsi_jawabans`.
- Ambil pending Redis.
- Hitung remaining.

Fix bertahap:

1. Cache `bank_soals` by ID.
2. Cache `opsi_jawabans` by `bank_soal_id`.
3. Cache `sesi_ujian_soals` by `session_id` selama ujian.
4. Ambil item nomor soal dari collection cached, bukan query ulang.
5. Untuk API dan Web, satukan logic `singleQuestionPayload()` agar fix tidak dobel.

Catatan: `ExamService::save()` sudah punya cache untuk `bank_soal`, `session_soals`, dan `opsi_soal`. Endpoint render soal belum memakai cache yang sama.

### P1. Batch Sync

Status audit: benar.

File:

- `app/Http/Controllers/WebController.php`
- `app/Http/Controllers/ApiController.php`

Masalah:

- Loop `answers`.
- Tiap jawaban memanggil `save()` -> Redis HSET + dispatch job.
- Tiap `ragu` update DB satu per satu.

Fix:

- Tambahkan method `ExamService::saveMany(object $session, array $answers): array`.
- Validasi semua `soal_hash` dan `opsi_hash`.
- Batch ambil metadata soal dan opsi.
- Pakai Redis pipeline atau `hmset/hmset` equivalent.
- Dispatch satu job `PersistSessionAnswersSnapshot($sessionId)` untuk flush semua pending session.
- Update `ditandai` dengan satu query `CASE WHEN`.

### P2. Perbaiki `RadarWorker`

Status audit: benar.

File: `app/Console/Commands/RadarWorker.php`

Fix minimal:

- Tambahkan option command: `--sleep=2`, `--max-iterations=0`, `--max-memory=256`.
- Tambahkan signal handler jika `pcntl_*` tersedia.
- Berhenti jika memory melewati limit.
- Jalankan via supervisor dengan autorestart.

Contoh command signature:

```php
protected $signature = 'cbt:radar-worker {--sleep=2} {--max-iterations=0} {--max-memory=256}';
```

Loop:

```php
$running = true;
if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGTERM, fn () => $running = false);
    pcntl_signal(SIGINT, fn () => $running = false);
}

$iteration = 0;
while ($running) {
    // collect radar
    $iteration++;
    if ($maxIterations > 0 && $iteration >= $maxIterations) break;
    if ((memory_get_usage(true) / 1024 / 1024) >= $maxMemory) break;
    sleep($sleep);
}
```

Lebih bagus lagi: jadikan scheduled command tiap 2-5 detik hanya jika infra mendukung, atau gunakan event-driven update dari session/jawaban.

### P2. Dedup `sessionItems()`

Status audit: benar untuk beberapa path, tapi `examPayload()` sudah pass `$items` ke `navigation()`.

File: `app/Http/Controllers/WebController.php`

Fix:

- Jangan panggil `singleQuestionPayload()` dalam `questionList()` untuk setiap item, karena itu akan membuat query per soal jika view lama memakai list penuh.
- Jika perlu render list soal penuh, buat method batch yang load semua question/options/answers sekali.

### P2. Optimasi `questionOptions()` Sort

Status audit: benar, micro-optimization.

File:

- `app/Http/Controllers/WebController.php`
- `app/Http/Controllers/ApiController.php`

Fix:

```php
$position = array_flip(array_map('intval', $order));
$options = $query->whereIn('id', $order)
    ->get()
    ->sortBy(fn ($option) => $position[(int) $option->id] ?? PHP_INT_MAX)
    ->values();
```

### P2. Cache `studentProfile()` dan `studentSchedules()`

Status audit: benar untuk profile; dashboard schedules sudah dioptimasi sebagian tapi belum cached.

File: `app/Http/Controllers/WebController.php`

Fix:

- Cache profile per user 5-15 menit.
- Cache schedules per user/kelas 30-60 detik selama jam ujian.
- Forget cache saat admin update data user/kelas/jadwal.

### P2. Frontend Fatal Error Auto-Retry

Status audit: sebagian benar.

File: `resources/js/main.js`

Yang sudah ada:

- Chunk loading error sudah auto reload satu kali.

Yang belum ada:

- Error Vue umum masih langsung overlay manual.

Fix:

- Tambahkan retry terbatas untuk chunk/network-like error.
- Jangan auto retry infinite untuk error logic Vue, karena bisa membuat loop.
- Tampilkan countdown sebelum reload.

### P3. Monitoring dan Health Check

Status audit: benar.

File: `routes/web.php`

Temuan:

- Ada monitoring route berat.
- Ada `proc_open()` untuk PgBouncer.
- Tidak terlihat health endpoint ringan.

Fix:

- Tambah route publik/internal ringan:

```php
Route::get('/healthz', fn () => response()->json([
    'ok' => true,
    'time' => now()->toISOString(),
]));
```

- Tambah `/readyz` yang cek DB/Redis singkat dengan timeout pendek, untuk load balancer.
- Cache hasil PgBouncer stats 5-10 detik agar endpoint monitoring tidak spawn `psql` terus-menerus.

## Urutan Implementasi Aman

1. `PersistAnswerSnapshot`: retry/backoff/failed log.
2. `ExamService::remaining()`: hapus static cache.
3. Redis fallback minimal untuk `save()`, `ping()`, `pendingAnswer()`, `flushAll()`.
4. Try-catch audit log jawaban agar audit tidak menggagalkan persist.
5. Optimasi endpoint soal dengan cache question/options/session items.
6. Batch sync.
7. RadarWorker graceful shutdown + supervisor config.
8. Health check dan caching monitoring.

## Catatan Testing

Minimal test/manual verification setelah fix:

- Simulasi save jawaban normal: Redis queue terisi dan job persist jalan.
- Simulasi Redis down saat `save()`: jawaban tetap masuk `jawaban_siswas`.
- Simulasi Redis down saat `ping()`: endpoint tidak 500.
- Simulasi job `PersistAnswerSnapshot` gagal: retry berjalan dan failed log tercatat.
- Simulasi submit dengan pending Redis: semua jawaban ter-flush sebelum status selesai.
- Jalankan endpoint soal berulang dan cek jumlah query turun setelah cache.
- Jalankan `cbt:radar-worker --max-iterations=2` dan pastikan command selesai.

## Revisi Prioritas dari `newissue.md`

P0:

- Retry/backoff `PersistAnswerSnapshot`.
- Hapus `static $cache` di `remaining()`.
- Redis fallback untuk save/ping/submit path.

P1:

- Device fingerprint request-scoped cache.
- Audit log async atau minimal non-blocking.
- Cache endpoint soal.
- Batch sync.

P2:

- RadarWorker graceful shutdown.
- Dedup/batch render soal penuh.
- Cache profile/dashboard.
- Frontend retry terbatas.

P3:

- PgBouncer monitoring cache.
- Health/readiness endpoint.
- Cache `Schema::hasTable()` atau hilangkan dari runtime hot path.
