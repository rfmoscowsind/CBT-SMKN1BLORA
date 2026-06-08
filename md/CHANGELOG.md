# 📋 CHANGELOG — Perubahan pada Dokumentasi CBT SMKN 1 Blora

---

## 2026-06-08 - GPT08061 Resume Session dan Device Lock Toggle

| File | Perubahan | Kategori |
|------|-----------|----------|
| `ExamService.php` | Pending answer Redis tetap flushable saat sesi `terkunci` | Fix data safety |
| `HandlesDeviceFingerprints.php` | Tambah setting global Device Lock, audit-only mode, dan histori fingerprint | Security / audit |
| `ManageController.php` | Tambah endpoint `POST /kelola/sesi/{id}/unlock-resume` dan `POST /kelola/data/device-lock/toggle` | Fitur |
| `AdminApiController.php` | Tambah endpoint API pengawas `POST /api/v1/pengawas/sesi/{id}/unlock-resume` | Fitur |
| `DeviceFingerprints.vue` | Tambah switch Device Lock ON/OFF, tombol `Buka Sesi / Lanjutkan`, dan tombol destruktif `Reset Ulang dari Nol` | UI |
| `2026_06_08_110000_add_device_lock_settings_and_history.php` | Tambah tabel `app_settings` dan `device_fingerprint_histories` | Database |
| `IMPLEMENTASI_GPT08061.md` | Dokumentasi teknis implementasi resume session dan toggle Device Lock | Dokumentasi |
| `gpt08061.md` / `known.md` | Tandai rekomendasi yang sudah selesai dan catatan sisa refactor | Dokumentasi |

Catatan operasional:

- Gunakan `Buka Sesi / Lanjutkan` untuk siswa yang terkunci/perlu lanjut tanpa kehilangan jawaban.
- Gunakan `Reset Ulang dari Nol` hanya jika jawaban siswa memang boleh dihapus.
- Jika Device Lock OFF, pindah perangkat tidak diblokir tetapi fingerprint tetap dicatat untuk audit.

---

## 🔄 Ringkasan Semua Perubahan

| # | File | Perubahan | Kategori |
|---|------|-----------|----------|
| 1 | `front.md` | Ping interval heartbeat 20 detik | ➕ Baru |
| 2 | `front.md` | Implementasi `muatJawabanLokal()` | 🔧 Fix |
| 3 | `back.md` | Fix `$user->with('roles')` → `$user->load('roles')` | 🔧 Fix |
| 4 | `back.md` | Schema MySQL → PostgreSQL (13 tabel) | 🔧 Fix |
| 5 | `back.md` | Sinkronisasi Performance Indexes | 🔧 Fix |
| 6 | `back.md` | Kolom `last_ping_at` + partial index | ➕ Baru |
| 7 | `back.md` | Endpoint `POST /ujian/sesi/{id}/ping` | ➕ Baru |
| 8 | `back.md` | Fix `masukUjian()` set `last_ping_at` | 🔧 Fix |
| 9 | `back.md` | Implementasi `getActiveSessions()` online/offline | 🔧 Fix |
| 10 | `back.md` | Fix double quotes `"PG"` → `'PG'` di getLiveScore | 🔧 Fix |
| 11 | `back.md` | Security Note #9: Ping-based Offline Detection | ➕ Baru |
| 12 | `front.md` | Hapus `downloadCertificate()` (button + stub) | 🔧 Hapus |
| 13 | `front.md` | Fix `identitasUjian` → `identitas` (runtime error) | 🔧 Fix |
| 14 | `front.md` | XSS prevention `v-html` → `v-text` | 🔧 Fix |
| 15 | `front.md` | Fix `console.log` → `catch(() => {})` | 🔧 Fix |
| 16 | `front.md` | User notification via Swal.fire di 3 catch blocks | 🔧 Fix |
| 17 | `back.md` | Clarify `sesi_ujian_soals` = Redis cache, bukan tabel fisik PostgreSQL | 🟡 Dokumen |
| 18 | `back.md` | Hapus duplicate ping endpoint `POST /api/ujian/ping` | 🔧 Fix |
| 19 | `back.md` | Hapus unused field `isian_pending` dari query getLiveScore | 🔧 Fix |

---

## 17. Clarify `sesi_ujian_soals` — `back.md`

**Lokasi:** Section E (Performance Indexes), poin #2

**Sebelum:** Tidak ada penjelasan — `sesi_ujian_soals` disebut di query tanpa klarifikasi.

**Sesudah:** Ditambahkan catatan:
> Catatan: `sesi_ujian_soals` bukan tabel fisik PostgreSQL. Urutan soal & acakan opsi disimpan di **Redis cache** (`exam_soal:{sesi_id}`).

---

## 18. Hapus Duplicate Ping Endpoint — `back.md`

**Lokasi:** Setelah `POST /ujian/sesi/{id}/ping` implementation

**Sebelum:** Ada 2 endpoint ping: `POST /api/ujian/ping` (API route) dan `POST /ujian/sesi/{id}/ping` (web route)

**Sesudah:** `POST /api/ujian/ping` dihapus dari dokumentasi + catatan.

---

## 19. Hapus Field `isian_pending` — `back.md`

**Sebelum:**
```php
DB::raw('COUNT(CASE WHEN ... ISIAN ... pending_manual ...) as isian_pending'),
```

**Sesudah:** Field dihapus (tidak digunakan di response JSON). Ditambahkan catatan.

---

## 📝 File List

| File | Status | Baris |
|------|--------|-------|
| `front.md` | ✅ **Bersih** | ~1.830 |
| `back.md` | ✅ **Bersih** | ~1.800 |
| `CHANGELOG.md` | ✅ **Lengkap** | ini |
| `3problem.md` | ✅ | Laporan awal |

---

## 🚀 Audit & Optimization Fixes (June 2026)

| File | Perubahan | Kategori | Deskripsi |
|------|-----------|----------|-----------|
| `WebController.php` | Hapus N+1 query di `examPayload()` | ⚡ Performa | Menghapus pengiriman key `'soal'` yang mubazir karena Vue SPA memuat soal per nomor via `/soal`. |
| `HandlesDeviceFingerprints.php` | Cegah write-locks konstan di `enforceDeviceFingerprintForUser()` | ⚡ Performa | Hanya menjalankan query `UPDATE` jika sidik jari berbeda dari yang sudah tersimpan. |
| `HandlesDeviceFingerprints.php` | Dukungan JWT stateless di `rejectStaleDeviceResetSession()` | 🔒 Keamanan / Fungsi | Membaca timestamp reset dari cache Redis dan membandingkannya dengan claim `iat` pada JWT token. |
| `ApiController.php` | Tambah reset check ke API `apiDeviceCheck()` | 🔒 Keamanan / Fungsi | Memastikan siswa dengan JWT lama diblokir jika gawai mereka di-reset oleh admin. |
| `WebController.php` & `ApiController.php` | Hapus sinkronisasi `flushOne` ganda di `save()` & `sync()` | ⚡ Performa | Menghapus penulisan database sinkron yang mendahului antrean job asinkron `PersistAnswerSnapshot`. |
| `ExamService.php` | Caching static DB read pada `save()` | ⚡ Performa | Membungkus pencarian soal dan opsi jawaban PG dengan Cache Redis selama 2 jam. |
| `ApiController.php` | Penyelarasan logika & format respons `studentSchedules()` | 🔧 Fix | Menambahkan filter tanggal hari ini dan data `jumlah_soal`/`tipe_soal` agar sesuai dengan kebutuhan Vue frontend. |
| `QuestionBankManagementController.php` | Batch validation opsi soal PG di `ready()` | ⚡ Performa | Mengambil seluruh opsi untuk semua soal paket sekaligus untuk memotong N+1 query validasi. |
| `AdminApiController.php` | Penyelarasan logika & validasi `readyPackage()` | ⚡ Performa / Fungsi | Menyelaraskan validasi opsi soal PG secara batch di API admin dengan controller web. |
| `LiveRadar.vue` | [NEW] Halaman monitoring Radar Nilai Real-Time | ➕ Baru / ⚡ Performa | Halaman khusus SuperAdmin untuk memantau progress pengerjaan, live score, status gawai secara real-time. |
| `SuperAdminDashboard.vue` | Hapus tabel radar & tambah redirect banner | 🔧 UI / ⚡ Performa | Mengurangi beban polling di dashboard utama dan merujuk SuperAdmin ke halaman radar terdedikasi. |
| `AdminSidebar.vue` | Navigasi dinamis & otorisasi link radar | 🔒 Keamanan / UI | Sidebar mengambil data user via `/auth/user` untuk membatasi link radar hanya untuk SuperAdmin. |
| `index.js` (Router) | Registrasi route `/vue/monitoring/radar` | 🔧 Router | Mendaftarkan halaman `LiveRadar.vue` ke sistem routing SPA Vue. |
| `ManageController.php` & `AdminApiController.php` | Hapus Sesi dan Reset Gawai | ➕ Baru / 🔧 Fix | Menghapus baris `sesi_ujians` (dan cascading child data) saat admin melakukan reset sesi agar hilang dari radar dan siswa bisa login ulang bersih. |
| `2026_06_03_200000_add_columns_to_paket_soals_table.php` | Migrasi Kolom Metadata Baru | ➕ Baru | Menambahkan kolom `kode_paket`, `jumlah_pg`, dan `has_isian` ke tabel `paket_soals`. |
| `QuestionBankManagementController.php` | Validasi & Penamaan Otomatis Paket | 🔧 Fix | Mengotomatiskan kepemilikan paket (`Auth::id()`) dan generate `judul` dinamis (format `"[KODE_PAKET] - [NAMA_MAPEL]"`). |
| `QuestionBankManagementController.php` | Auto-generate Soal PG Kosong | ➕ Baru | Men-generate otomatis soal PG kosong (beserta opsi A-E) di database sejumlah `jumlah_pg` saat paket baru dibuat. |
| `QuestionBank.vue` | UI Modal Baru & Redirect Wizard | 🔧 UI / Fungsi | Mengubah input modal pembuatan paket (Mapel, Kode Paket, Jumlah PG, Switch Isian) dan otomatis masuk wizard jika berhasil disimpan. |
| `QuestionBank.vue` | Otomatisasi Isi Kode Paket | ⚡ Performa / UI | Mengisi nilai input `kode_paket` secara otomatis dari kode mata pelajaran pilihan dan menonaktifkan kolom ketik manualnya. |
| `QuestionBank.vue` | Otomatisasi Input Tipe Soal | ⚡ Performa / UI | Menyembunyikan input pilihan tipe soal dan menghitungnya secara otomatis berdasarkan urutan soal dan pengaturan batas paket. |
| `QuestionBankManagementTest.php` | Penyesuaian Skenario Pengujian Paket | 🔧 Fix | Menyelaraskan input pengujian dan penegasan format judul baru untuk pengujian manajemen bank soal. |
| `RolePermissionSeeder.php` | Pembersihan Cache Spatie dalam Pengujian | 🔧 Fix | Menambahkan fungsi penghapusan cache izin sebelum proses seeding berjalan untuk menghindari duplikasi cache. |
| `MasterDataManagement.vue` | Fitur CRUD Mata Pelajaran | ➕ Baru / 🔧 UI | Menambahkan antarmuka pembuatan, pengubahan, dan penghapusan mata pelajaran (Mapel) yang terhubung ke backend. |

---

*Dokumen final — June 2026*

---

## 🔒 Audit Hardening Fixes — issuegpt.md (June 2026)

Berdasarkan audit di `issuegpt.md`, dilakukan verifikasi terhadap kode aktual dan ditemukan 3 issue P0 yang valid dan belum terfix. Perubahan berikut telah diimplementasikan:

| # | Issue | File | Kategori | Status Sebelum | Perubahan |
|---|-------|------|----------|---------------|-----------|
| A | Race condition `start()` — double-click/retry bisa buat 2 sesi | `ExamService.php` | 🔒 P0 Race Condition | ❌ Check-then-insert tanpa lock | ✅ Wrap `DB::transaction()` + `lockForUpdate()` |
| B | Double-submit `submit()` — waktu habis + klik bersamaan | `ExamService.php` | 🔒 P0 Race Condition | ❌ Cek `if status !== aktif` tanpa lock | ✅ Wrap `DB::transaction()` + `lockForUpdate()` + `WHERE status='aktif'` |
| C | Heartbeat ping write-heavy ke DB — ~150 write/detik @1500 siswa | `WebController.php` | ⚡ P1 Performa | ❌ Setiap ping langsung UPDATE DB | ✅ Status online di Redis (TTL 45s), DB diupdate max 1x/60 detik via throttle key |

### Issue yang sudah OK (tidak perlu fix):

| # | Issue | Status |
|---|-------|--------|
| D | Queue masih pakai `database` | ✅ `.env` sudah `QUEUE_CONNECTION=redis` |
| E | Unique index tidak ada | ✅ Sudah ada: `sesi_ujians(user_id, jadwal_ujian_id)`, `jawaban_siswas(sesi_ujian_id, bank_soal_id)` |
| F | Endpoint monitoring sessions tanpa filter | ✅ Sudah pakai `OperationsApiController` dengan filter `jadwal_id` wajib |
| G | Index DB tidak lengkap | ✅ Sudah ada via migration `2026_06_03_012913_add_missing_indexes_for_1500_users.php` |

### Detail Fix A — `ExamService::start()` — Race Condition Session Creation

**Sebelum:**
```php
$existing = DB::table('sesi_ujians')
    ->where(['user_id' => $user->id, 'jadwal_ujian_id' => $j->id])
    ->first(); // ← baca tanpa lock, rentan race condition
if ($existing) { return $existing; }
$sessionId = DB::table('sesi_ujians')->insertGetId([...]); // ← insert tanpa transaction
```

**Sesudah:**
```php
return DB::transaction(function () use ($user, $j, $ip, $ua) {
    $existing = DB::table('sesi_ujians')
        ->where('user_id', $user->id)
        ->where('jadwal_ujian_id', $j->id)
        ->lockForUpdate() // ← row-level lock mencegah race condition
        ->first();
    if ($existing) { return $existing; }
    $sessionId = DB::table('sesi_ujians')->insertGetId([...]); // ← aman dalam transaksi
    // ... sisanya sama
});
```

### Detail Fix B — `ExamService::submit()` — Double-Submit Lock

**Sebelum:**
```php
public function submit(object $s): object {
    if ($s->status !== 'aktif') { return $s; } // ← cek dari memori, bisa stale
    $this->flushAll($s->id);
    DB::table('sesi_ujians')->where('id', $s->id)->update([...]); // ← update tanpa lock
}
```

**Sesudah:**
```php
public function submit(object $s): object {
    return DB::transaction(function () use ($s) {
        $session = DB::table('sesi_ujians')
            ->where('id', $s->id)
            ->lockForUpdate() // ← lock row sebelum cek
            ->first();
        if (!$session || $session->status !== 'aktif') { return $session ?: $s; }
        // ... flush & hitung skor
        DB::table('sesi_ujians')
            ->where('id', $session->id)
            ->where('status', 'aktif') // ← double guard agar aman dari concurrent update
            ->update([...]);
    });
}
```

### Detail Fix C — `WebController::ping()` — Heartbeat Redis Throttle

**Sebelum:**
```php
DB::table('sesi_ujians')
    ->where('id', $session->id)
    ->update(['last_seen_at' => now(), 'updated_at' => now()]); // ← setiap ping langsung ke DB
```

**Sesudah:**
```php
// Status online di Redis dengan TTL 45 detik
Redis::setex("cbt:session:online:{$session->id}", 45, now()->timestamp);

// DB hanya diupdate 1x per 60 detik (throttle via Redis key)
if (!Redis::exists("cbt:ping:db:{$session->id}")) {
    DB::table('sesi_ujians')->where('id', $session->id)
        ->update(['last_seen_at' => now(), 'updated_at' => now()]);
    Redis::setex("cbt:ping:db:{$session->id}", 60, 1);
}
```

**Dampak performa:** Dari ~150 DB write/detik → ~2-3 DB write/detik saat 1500 siswa aktif.

---
