# Audit Issue CBT-SMKN1BLORA

Dokumen ini berisi ringkasan audit awal dari sisi performa query, indexing database, race condition, dan stabilitas saat CBT dipakai massal.

Audit ini berdasarkan file yang terlihat di repo:

- `routes/web.php`
- `app/Http/Controllers/WebController.php`
- `app/Services/ExamService.php`
- `app/Jobs/PersistAnswerSnapshot.php`
- `config/database.php`
- `config/queue.php`

> Catatan: migration/index aktual belum berhasil diverifikasi dari repo. Semua rekomendasi index di bawah wajib dicek ulang langsung di database PostgreSQL atau file migration.

---

## Ringkasan Prioritas

### P0 - Wajib sebelum ujian massal

1. Tambahkan unique index untuk mencegah sesi ujian dobel.
2. Tambahkan unique index jawaban per sesi dan soal.
3. Ubah queue dari `database` ke `redis` untuk job jawaban.
4. Tambahkan transaction/lock pada proses mulai ujian.
5. Tambahkan transaction/lock pada proses submit ujian.
6. Ubah mekanisme upsert jawaban agar aman dari last-write race condition.

### P1 - Penting untuk 1500 siswa aktif

1. Pindahkan heartbeat/ping online siswa ke Redis.
2. Batasi endpoint monitoring sessions agar tidak mengambil semua sesi dari awal.
3. Pastikan index komposit untuk query dashboard, sesi, jawaban, dan jadwal.
4. Jalankan queue worker khusus untuk queue `answers`.
5. Kurangi query berulang pada endpoint pengambilan soal.

### P2 - Maintenance dan skalabilitas

1. Pisahkan audit log jawaban high-volume dari audit log sistem.
2. Buat README deploy produksi.
3. Tambah health check DB, Redis, queue backlog, dan latency.
4. Buat load test sederhana untuk simulasi 1500 siswa.

---

# 1. Race Condition Saat Mulai Ujian

## Lokasi

`app/Services/ExamService.php`

Bagian flow `start()`:

```php
$existing = DB::table('sesi_ujians')
    ->where(['user_id' => $user->id, 'jadwal_ujian_id' => $j->id])
    ->first();

if ($existing) {
    return $existing;
}

$sessionId = DB::table('sesi_ujians')->insertGetId([...]);
```

## Masalah

Jika siswa double click tombol mulai, browser retry, koneksi lambat, atau request masuk paralel, dua request bisa sama-sama membaca bahwa sesi belum ada, lalu dua-duanya membuat sesi baru.

## Dampak

- Satu siswa bisa punya lebih dari satu sesi untuk jadwal yang sama.
- Tabel `sesi_ujian_soals` bisa terisi dobel.
- Monitoring dan hasil ujian bisa tidak konsisten.

## Rekomendasi

Tambahkan unique index:

```sql
CREATE UNIQUE INDEX IF NOT EXISTS uq_sesi_ujians_user_jadwal
ON sesi_ujians (user_id, jadwal_ujian_id);
```

Ubah start menjadi transaction dan lock:

```php
return DB::transaction(function () use ($user, $j, $ip, $ua) {
    $existing = DB::table('sesi_ujians')
        ->where('user_id', $user->id)
        ->where('jadwal_ujian_id', $j->id)
        ->lockForUpdate()
        ->first();

    if ($existing) {
        return $existing;
    }

    $sessionId = DB::table('sesi_ujians')->insertGetId([
        'user_id' => $user->id,
        'jadwal_ujian_id' => $j->id,
        'waktu_login' => now(),
        'status' => 'aktif',
        'ip_address' => $ip,
        'device_info' => json_encode(['user_agent' => $ua]),
        'last_seen_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Insert sesi_ujian_soals di transaction yang sama.

    return DB::table('sesi_ujians')->find($sessionId);
});
```

Tetap pertahankan unique index, karena lock saja tidak cukup jika query awal belum menemukan row.

---

# 2. Queue Jawaban Masih Berisiko Memakai Database

## Lokasi

`config/queue.php`

```php
'default' => env('QUEUE_CONNECTION', 'database'),
```

`app/Services/ExamService.php`

```php
PersistAnswerSnapshot::dispatch($s->id, $q->id)->onQueue('answers');
```

## Masalah

Jika `.env` tidak mengubah `QUEUE_CONNECTION`, maka queue akan memakai database. Untuk CBT massal, ini berbahaya karena setiap save jawaban membuat job di tabel `jobs`.

## Dampak

- DB utama menjadi broker queue.
- Write load meningkat tinggi.
- Saat 1500 siswa aktif, tabel `jobs` bisa jadi bottleneck.
- Latency simpan jawaban bisa naik.

## Rekomendasi

Gunakan Redis queue:

```env
QUEUE_CONNECTION=redis
REDIS_QUEUE_CONNECTION=default
REDIS_QUEUE=answers
```

Worker produksi:

```bash
php artisan queue:work redis --queue=answers --sleep=0 --tries=3 --timeout=30 --max-jobs=1000
```

Untuk awal 1500 siswa, jalankan 4 sampai 8 worker khusus queue `answers`, lalu monitor backlog dan DB latency.

---

# 3. Race Condition Saat Simpan Jawaban

## Lokasi

`app/Services/ExamService.php`

Flow saat save:

```php
Redis::hset("queue_jawaban:{$s->id}", (string) $q->id, json_encode($payload));
Redis::expire("queue_jawaban:{$s->id}", 86400);
PersistAnswerSnapshot::dispatch($s->id, $q->id)->onQueue('answers');
```

Flow persist:

```php
DB::table('jawaban_siswas')->updateOrInsert(
    ['sesi_ujian_id' => $sessionId, 'bank_soal_id' => $soalId],
    [...]
);
```

## Yang Sudah Bagus

- Jawaban disimpan dulu ke Redis hash.
- Field Redis memakai `bank_soal_id`, jadi jawaban terakhir untuk soal yang sama menimpa jawaban lama.
- Setelah persist, Redis dihapus memakai Lua `forgetIfCurrent()`, sehingga job lama tidak menghapus payload baru.

## Masalah

Pengecekan timestamp dilakukan sebelum `updateOrInsert()`. Jika worker berjalan paralel, masih ada kemungkinan jawaban lama menimpa jawaban baru.

Skenario:

1. Job A membaca jawaban lama.
2. Job B membaca jawaban baru.
3. Job B menulis jawaban baru ke DB.
4. Job A lanjut dan menulis jawaban lama ke DB.

## Dampak

- Jawaban akhir siswa bisa mundur ke versi lama.
- Nilai akhir bisa salah jika jawaban lama berbeda dengan jawaban terbaru.

## Rekomendasi

Untuk PostgreSQL, gunakan atomic upsert dengan kondisi timestamp.

Contoh konsep SQL:

```sql
INSERT INTO jawaban_siswas (
    sesi_ujian_id,
    bank_soal_id,
    opsi_jawaban_id,
    jawaban_essay,
    tipe_soal,
    skor,
    scoring_status,
    client_updated_at,
    server_updated_at,
    created_at,
    updated_at
)
VALUES (...)
ON CONFLICT (sesi_ujian_id, bank_soal_id)
DO UPDATE SET
    opsi_jawaban_id = EXCLUDED.opsi_jawaban_id,
    jawaban_essay = EXCLUDED.jawaban_essay,
    tipe_soal = EXCLUDED.tipe_soal,
    skor = EXCLUDED.skor,
    scoring_status = EXCLUDED.scoring_status,
    client_updated_at = EXCLUDED.client_updated_at,
    server_updated_at = EXCLUDED.server_updated_at,
    updated_at = EXCLUDED.updated_at
WHERE jawaban_siswas.server_updated_at IS NULL
   OR EXCLUDED.server_updated_at >= jawaban_siswas.server_updated_at;
```

Syarat wajib:

```sql
CREATE UNIQUE INDEX IF NOT EXISTS uq_jawaban_siswas_session_soal
ON jawaban_siswas (sesi_ujian_id, bank_soal_id);
```

---

# 4. Submit Ujian Perlu Lock

## Lokasi

`app/Services/ExamService.php`

```php
public function submit(object $s): object
{
    if ($s->status !== 'aktif') {
        return $s;
    }

    $this->flushAll($s->id);

    $score = DB::table('jawaban_siswas')
        ->where('sesi_ujian_id', $s->id)
        ->sum('skor');

    DB::table('sesi_ujians')->where('id', $s->id)->update([...]);
}
```

## Masalah

Submit bisa terpanggil lebih dari sekali:

- Siswa klik selesai.
- Browser retry.
- Auto-submit karena waktu habis.
- Request lain memicu `ownedSession()` dan melihat waktu habis.

## Dampak

- Event submit bisa dobel.
- Perhitungan nilai bisa dilakukan saat proses flush jawaban belum stabil.
- Status sesi bisa update berulang.

## Rekomendasi

Gunakan transaction dan lock row sesi:

```php
public function submit(object $s): object
{
    return DB::transaction(function () use ($s) {
        $session = DB::table('sesi_ujians')
            ->where('id', $s->id)
            ->lockForUpdate()
            ->first();

        if (!$session || $session->status !== 'aktif') {
            return $session ?: $s;
        }

        $this->flushAll($session->id);

        $score = DB::table('jawaban_siswas')
            ->where('sesi_ujian_id', $session->id)
            ->sum('skor');

        DB::table('sesi_ujians')
            ->where('id', $session->id)
            ->where('status', 'aktif')
            ->update([
                'status' => 'selesai',
                'waktu_submit' => now(),
                'nilai_akhir' => $score,
                'updated_at' => now(),
            ]);

        $this->event($session->id, 'submit');

        return DB::table('sesi_ujians')->find($session->id);
    });
}
```

---

# 5. Query Ambil Soal Masih Cukup Banyak

## Lokasi

`app/Http/Controllers/WebController.php`

Fungsi `singleQuestionPayload()` melakukan beberapa query untuk satu soal:

1. Ambil row `sesi_ujian_soals`.
2. Ambil `bank_soals`.
3. Ambil `jawaban_siswas`.
4. Ambil pending answer dari Redis.
5. Ambil opsi jawaban.

## Dampak

Saat 1500 siswa berpindah soal bersamaan, query kecil ini bisa menjadi beban tinggi.

## Rekomendasi

Pastikan index minimal:

```sql
CREATE UNIQUE INDEX IF NOT EXISTS uq_sesi_ujian_soals_session_nomor
ON sesi_ujian_soals (sesi_ujian_id, nomor_soal);

CREATE UNIQUE INDEX IF NOT EXISTS uq_sesi_ujian_soals_session_bank_soal
ON sesi_ujian_soals (sesi_ujian_id, bank_soal_id);

CREATE UNIQUE INDEX IF NOT EXISTS uq_jawaban_siswas_session_bank_soal
ON jawaban_siswas (sesi_ujian_id, bank_soal_id);

CREATE INDEX IF NOT EXISTS idx_opsi_jawabans_bank_soal
ON opsi_jawabans (bank_soal_id);
```

Optimasi lanjutan:

- Cache metadata soal per paket.
- Cache opsi soal per `bank_soal_id`.
- Jangan kirim ulang navigasi penuh setiap ambil soal jika tidak berubah.

---

# 6. Endpoint Monitoring Sessions Mengambil Semua Data

## Lokasi

`routes/web.php`

Endpoint:

```php
Route::get('/monitoring/sessions', function() {
    $sessions = DB::table('sesi_ujians as s')
        ->join('users as u','u.id','=','s.user_id')
        ->join('jadwal_ujians as j','j.id','=','s.jadwal_ujian_id')
        ->join('master_ujians as m','m.id','=','j.master_ujian_id')
        ->select(...)
        ->orderByDesc('s.id')
        ->get();
});
```

## Masalah

Query ini tidak punya filter tanggal, jadwal, status, atau limit. Jika data sesi sudah banyak, endpoint monitoring bisa berat.

## Dampak

- Dashboard pengawas/admin lambat.
- DB terbebani oleh query monitoring.
- Saat ujian aktif, monitoring bisa mengganggu flow siswa.

## Rekomendasi

Tambahkan filter:

```php
->where('j.waktu_mulai', '>=', now('Asia/Jakarta')->startOfDay()->utc())
->where('j.waktu_mulai', '<=', now('Asia/Jakarta')->endOfDay()->utc())
->whereIn('s.status', ['aktif', 'terkunci', 'selesai'])
->limit(2000)
```

Lebih baik lagi: monitoring sessions wajib memakai filter `jadwal_id`.

Index pendukung:

```sql
CREATE INDEX IF NOT EXISTS idx_sesi_ujians_jadwal_status
ON sesi_ujians (jadwal_ujian_id, status);

CREATE INDEX IF NOT EXISTS idx_sesi_ujians_status_id
ON sesi_ujians (status, id DESC);

CREATE INDEX IF NOT EXISTS idx_jadwal_ujians_time
ON jadwal_ujians (waktu_mulai, waktu_selesai);
```

---

# 7. Ping Online Siswa Berpotensi Write-Heavy

## Lokasi

`app/Http/Controllers/WebController.php`

```php
DB::table('sesi_ujians')
    ->where('id', $session->id)
    ->update(['last_seen_at' => now(), 'updated_at' => now()]);
```

## Masalah

Jika 1500 siswa ping tiap 10 detik, DB menerima sekitar 150 update/detik hanya untuk status online.

## Dampak

- Beban write PostgreSQL meningkat.
- Tabel `sesi_ujians` bisa mengalami bloat karena update berulang.
- Monitoring online membuat DB lebih sibuk.

## Rekomendasi

Pindahkan heartbeat ke Redis:

```php
Redis::setex("cbt:session:online:{$session->id}", 45, now()->timestamp);
```

Update DB `last_seen_at` cukup setiap 60 sampai 120 detik, atau saat event penting.

---

# 8. Audit Log Jawaban Bisa Membengkak

## Lokasi

`app/Services/ExamService.php`

`flushOne()` dan `flushAll()` insert `audit_logs` dengan action `answer_saved`.

## Masalah

Jika siswa sering mengganti jawaban, audit row bertambah sangat cepat.

Contoh kasar:

- 1500 siswa
- 50 soal
- rata-rata 5 kali perubahan jawaban

Total bisa sekitar 375.000 row audit untuk satu ujian.

## Dampak

- Tabel `audit_logs` cepat besar.
- Query audit/admin melambat.
- Backup dan maintenance DB makin berat.

## Rekomendasi

- Pisahkan audit jawaban high-volume ke tabel khusus, misalnya `answer_audit_logs`.
- Gunakan partition PostgreSQL per bulan atau per tanggal ujian.
- Untuk audit operasional, tetap pakai `audit_logs`.

Index minimal:

```sql
CREATE INDEX IF NOT EXISTS idx_audit_logs_session_action_created
ON audit_logs (sesi_ujian_id, action, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_audit_logs_user_created
ON audit_logs (user_id, created_at DESC);
```

---

# 9. Index Database yang Wajib Dicek

## Users

```sql
CREATE UNIQUE INDEX IF NOT EXISTS users_username_unique
ON users (username);

CREATE INDEX IF NOT EXISTS idx_users_role
ON users (role);

CREATE INDEX IF NOT EXISTS idx_users_kelas_aktif
ON users (kelas_aktif_id);
```

## Jadwal Ujian

```sql
CREATE INDEX IF NOT EXISTS idx_jadwal_ujian_kelas_kelas_jadwal
ON jadwal_ujian_kelas (kelas_aktif_id, jadwal_ujian_id);

CREATE INDEX IF NOT EXISTS idx_jadwal_ujians_active_time
ON jadwal_ujians (diarsipkan_at, waktu_mulai, waktu_selesai);

CREATE INDEX IF NOT EXISTS idx_jadwal_ujians_master
ON jadwal_ujians (master_ujian_id);
```

## Sesi Ujian

```sql
CREATE UNIQUE INDEX IF NOT EXISTS uq_sesi_user_jadwal
ON sesi_ujians (user_id, jadwal_ujian_id);

CREATE INDEX IF NOT EXISTS idx_sesi_jadwal_status
ON sesi_ujians (jadwal_ujian_id, status);

CREATE INDEX IF NOT EXISTS idx_sesi_user_status
ON sesi_ujians (user_id, status);

CREATE INDEX IF NOT EXISTS idx_sesi_last_seen
ON sesi_ujians (last_seen_at);
```

## Soal Per Sesi

```sql
CREATE UNIQUE INDEX IF NOT EXISTS uq_sesi_soal
ON sesi_ujian_soals (sesi_ujian_id, bank_soal_id);

CREATE UNIQUE INDEX IF NOT EXISTS uq_sesi_nomor
ON sesi_ujian_soals (sesi_ujian_id, nomor_soal);

CREATE INDEX IF NOT EXISTS idx_sesi_soals_bank
ON sesi_ujian_soals (bank_soal_id);
```

## Jawaban Siswa

```sql
CREATE UNIQUE INDEX IF NOT EXISTS uq_jawaban_session_soal
ON jawaban_siswas (sesi_ujian_id, bank_soal_id);

CREATE INDEX IF NOT EXISTS idx_jawaban_session
ON jawaban_siswas (sesi_ujian_id);

CREATE INDEX IF NOT EXISTS idx_jawaban_scoring_status
ON jawaban_siswas (scoring_status);

CREATE INDEX IF NOT EXISTS idx_jawaban_server_updated
ON jawaban_siswas (server_updated_at);
```

## Bank Soal dan Opsi

```sql
CREATE INDEX IF NOT EXISTS idx_bank_soals_paket_urutan
ON bank_soals (paket_soal_id, urutan);

CREATE INDEX IF NOT EXISTS idx_opsi_bank_kode
ON opsi_jawabans (bank_soal_id, kode);

CREATE INDEX IF NOT EXISTS idx_opsi_correct
ON opsi_jawabans (bank_soal_id, id)
WHERE is_benar = true;
```

## Event Sesi

```sql
CREATE INDEX IF NOT EXISTS idx_session_events_session_created
ON session_events (sesi_ujian_id, created_at DESC);
```

---

# 10. Rekomendasi Konfigurasi Produksi

## Queue

```env
QUEUE_CONNECTION=redis
REDIS_QUEUE_CONNECTION=default
REDIS_QUEUE=answers
```

Worker:

```bash
php artisan queue:work redis --queue=answers --sleep=0 --tries=3 --timeout=30 --max-jobs=1000
```

## Cache

Pastikan cache memakai Redis:

```env
CACHE_STORE=redis
SESSION_DRIVER=redis
```

## Octane

Jika memakai Octane, pastikan tidak ada state global yang bocor antar request. Static cache di `remaining()` masih per worker process, jadi harus hati-hati jika data jadwal berubah saat ujian berjalan.

## PostgreSQL

Minimal pantau:

```sql
SELECT count(*) FROM pg_stat_activity;
SELECT * FROM pg_stat_user_indexes ORDER BY idx_scan ASC LIMIT 20;
SELECT relname, n_dead_tup FROM pg_stat_user_tables ORDER BY n_dead_tup DESC LIMIT 20;
```

---

# 11. Checklist Sebelum Ujian 1500 Siswa

- [ ] Unique index `sesi_ujians(user_id, jadwal_ujian_id)` sudah ada.
- [ ] Unique index `jawaban_siswas(sesi_ujian_id, bank_soal_id)` sudah ada.
- [ ] Queue sudah Redis, bukan database.
- [ ] Worker queue `answers` aktif minimal 4 worker.
- [ ] Submit memakai lock/transaction.
- [ ] Start session memakai lock/unique-safe insert.
- [ ] Upsert jawaban memakai timestamp-safe atomic upsert.
- [ ] Ping online siswa pindah ke Redis atau dibatasi interval update DB.
- [ ] Monitoring sessions punya filter jadwal/tanggal/status.
- [ ] Index utama sudah dicek dengan `EXPLAIN ANALYZE`.
- [ ] Load test minimal 500 sampai 1500 virtual siswa sudah dicoba.

---

# 12. Catatan Akhir

Desain dasar aplikasi sudah cukup baik karena jawaban disimpan lewat Redis queue sebelum masuk DB. Risiko terbesar saat ini bukan pada fitur CBT-nya, tetapi pada atomicity dan beban tulis saat ujian massal.

Fokus perbaikan paling penting:

1. Unique index.
2. Redis queue.
3. Lock start dan submit.
4. Atomic timestamp-safe upsert jawaban.
5. Optimasi monitoring dan ping.
