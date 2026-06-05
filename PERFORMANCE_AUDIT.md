# 🚀 PERFORMANCE & SECURITY AUDIT — CBT SMKN 1 Blora (1500 Concurrent Users)

---

## 🔴 CRITICAL — Must Fix (4 items)

### 1. ProcessAnswerBatch — `select *` on ALL active sessions

**Lokasi:** `back.md` → `ProcessAnswerBatch@handle`
```php
$allSessions = SesiUjian::where('status', 'aktif')->pluck('id');
```

**Masalah:** Dengan 1500 user, ini mengambil 1500 ID lalu looping satu per satu. Setiap iterasi melakukan `Redis::hgetall()`, `DB::transaction()`, `JawabanSiswa::updateOrCreate()`. Ini **blocking queue job** yang akan memakan waktu puluhan detik.

**Solusi:**
```php
// Proses dalam batch, bukan per session
$queueKeys = Redis::keys('queue_jawaban:*');
foreach ($queueKeys as $key) {
    $sesiId = str_replace('queue_jawaban:', '', $key);
    // process...
}
```

---

### 2. `getActiveSessions()` — LEFT JOIN jawaban_siswas tanpa index

**Masalah:** `leftJoin('jawaban_siswas')` + `COUNT(DISTINCT ...)` + `groupBy()` pada tabel dengan jutaan baris = **full table scan**.

**Solusi:** 
```sql
-- Add composite index for monitoring queries
CREATE INDEX idx_monitoring_sesi ON jawaban_siswas (sesi_ujian_id, id);
```

Atau query jawaban terpisah:
```php
// First get sessions
$sessions = DB::table('sesi_ujians')->where(...)->get();
// Then count answers separately
$jawabanCounts = JawabanSiswa::whereIn('sesi_ujian_id', $ids)
    ->groupBy('sesi_ujian_id')
    ->selectRaw('sesi_ujian_id, COUNT(*) as count')
    ->pluck('count', 'sesi_ujian_id');
```

---

### 3. `save()` and `simpanJawabanKeServer()` — hit DB on every keystroke

**Masalah:** Setiap kali user memilih opsi, `simpanJawaban()` dipanggil → trigger `simpanJawabanKeServer()` dalam 1 detik. 1500 user × 40 soal × banyak klik = **ribuan request** ke database.

**Solusi (sudah ada):** Debounce 1 detik + simpan ke localStorage dulu. TAPI `simpanJawabanKeServer()` tetap dipanggil setiap kali. Seharusnya: simpan ke **Redis queue** dulu, bukan langsung updateOrCreate.

```php
// Di simpanJawabanKeServer, ganti:
// $this->exams->save(...) → push ke Redis queue
Redis::hset("queue_jawaban:{$sesiId}", $bankSoalId, json_encode($data));
```

---

### 4. `show()` method — query `sesi_ujian_soals` setiap refresh navigasi

**Masalah:** Setiap kali user klik navigasi soal, method `show()` (GET /ujian/sesi/{id}) query `sesi_ujian_soals` LEFT JOIN `jawaban_siswas`. Ini dipanggil **setiap navigasi**.

**Solusi:** 
- Cache navigasi status di **Redis** dengan TTL 60 detik
- Di frontend, simpan status navigasi di **Pinia store** dan update lokal saja

---

## 🟡 HIGH — Should Fix (5 items)

### 5. Tidak ada rate limiting per-user di kode

Hanya disebut di Nginx level. Untuk 1500 user, perlu juga di Laravel:
```php
// routes/api.php
Route::middleware('throttle:60,1')->group(function () {
    // exam routes
});
```

### 6. `JawabanSiswa::updateOrCreate()` — race condition

Jika 2 request save tiba bersamaan untuk soal yang sama, `UNIQUE(sesi_ujian_id, bank_soal_id)` akan conflict. Perlu `lock` atau queue.

### 7. Tidak ada pagination di `getActiveSessions()`

Jika ada 1500 sesi, semua dikirim dalam 1 response. Perlu pagination.

### 8. `Redis::setex()` dengan durasi_menit*60 — bisa overflow

Jika ujian 3 jam = 10800 detik, OK. Tapi jika ada bug durasi > 24 jam, perlu validasi.

### 9. CSRF Protection — JWT-only, tidak ada double submit cookie

Sudah OK untuk API. Tapi untuk web routes (yang pakai session), perlu CSRF token.

---

## 🟢 INFO — Best Practices (6 items)

### 10. Gunakan `pluck()` bukan `get()->toArray()`

Sudah OK — `pluck('id')` sudah digunakan.

### 11. `scoring_status DEFAULT 'auto_scored'`

Untuk ISIAN seharusnya `pending_manual`. Tapi sudah di-handle di code.

### 12. Full-text search dengan GIN index

✅ Sudah — `idx_bank_soal_fts` USING GIN.

### 13. Partial indexes untuk active sessions

✅ Sudah — `idx_sesi_aktif_partial` dan `idx_jawab_pending_scoring`.

### 14. JSON → JSONB

✅ Sudah — `JSONB` digunakan.

### 15. Prepared statements via Eloquent

✅ Sudah — menggunakan Eloquent ORM, bukan raw SQL (kecuali `DB::raw`).

---

## 📋 REKOMENDASI PRIORITAS

| Prioritas | Item | Dampak |
|-----------|------|--------|
| 🔴 P1 | Batch process di ProcessAnswerJob | Queue blocking |
| 🔴 P1 | LEFT JOIN tanpa index monitoring | Full table scan |
| 🔴 P1 | Langsung DB write setiap klik | DB overload |
| 🔴 P1 | Navigasi query tanpa cache | High read load |
| 🟡 P2 | Rate limiting per-user | Abuse risk |
| 🟡 P2 | Race condition updateOrCreate | Data corruption |
| 🟡 P2 | Pagination monitoring | Response size |
| 🟢 P3 | Best practices | Optimization |

---

*Generated: June 2026*