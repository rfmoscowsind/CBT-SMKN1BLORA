# 🐢 Laporan Diagnosa Performa — CBT-SMKN1-Blora

> **Dibuat:** 2 Juni 2026  
> **Analisis:** Menyeluruh (Controllers, Services, Models, DB Schema, Config)  
> **Severity:** 🔴 Kritis ditemukan di beberapa lokasi

---

## Ringkasan Eksekutif

Project ini lambat karena **kombinasi dari 7 masalah utama** yang saling memperparah satu sama lain. Masalah paling parah adalah **N+1 query di dalam loop** yang berjalan di setiap request, terutama di halaman ujian siswa dan halaman management admin. Selain itu ditemukan **query penuh tanpa pagination**, **permission check yang memaksa DB round-trip setiap request**, serta **tidak ada caching** di endpoint yang paling sering diakses.

---

## 🔴 MASALAH KRITIS

### 1. N+1 Query di `WebController::show()` — Paling Parah

**File:** [`app/Http/Controllers/WebController.php`](w:/app/Http/Controllers/WebController.php) — baris 163–196

```php
// Ambil semua soal dalam sesi (misal 50 soal)
$items = DB::table('sesi_ujian_soals')->where('sesi_ujian_id', $s->id)->orderBy('nomor_soal')->get();

// Lalu untuk SETIAP soal, ada 2 query terpisah:
$daftarSoal = $items->map(function($item) use ($answers) {
    $question = DB::table('bank_soals')->find($item->bank_soal_id);       // ← Query ke-1 (loop)
    // ...
    $rawOptions = DB::table('opsi_jawabans')->whereIn('id', $order)->get(); // ← Query ke-2 (loop)
    // ...
});
```

**Dampak:** Jika satu paket soal memiliki **50 soal PG**, maka endpoint `/ujian/sesi/{id}` akan menjalankan:
- 1 query `sesi_ujian_soals`
- **50 query** `bank_soals`
- **50 query** `opsi_jawabans`
- Total: **~102 query per request** setiap kali halaman ujian dibuka!

**Solusi:**

```php
// SEBELUM (N+1 — buruk):
$items->map(function($item) {
    $question = DB::table('bank_soals')->find($item->bank_soal_id); // loop!
});

// SESUDAH (eager load — optimal):
// Ambil semua soal sekaligus
$questionIds = $items->pluck('bank_soal_id');
$questions   = DB::table('bank_soals')->whereIn('id', $questionIds)->get()->keyBy('id');
$allOptionIds = $items->flatMap(fn($i) => json_decode($i->opsi_order, true) ?: [])->unique();
$allOptions  = DB::table('opsi_jawabans')->whereIn('id', $allOptionIds)->get()->keyBy('id');

$daftarSoal = $items->map(function($item) use ($questions, $allOptions, $answers) {
    $question = $questions->get($item->bank_soal_id); // O(1) lookup
    $order    = json_decode($item->opsi_order, true) ?: [];
    $options  = collect($order)
        ->filter(fn($id) => $allOptions->has($id))
        ->map(fn($id) => [
            'hash_id' => $this->ids->encode($id),
            'teks'    => $allOptions[$id]->teks_opsi,
        ])->values();
    // ...
});
// Total query: 4 saja, berapapun jumlah soal
```

---

### 2. N+1 Query di `ManageController::sessions()` — Dashboard Admin

**File:** [`app/Http/Controllers/ManageController.php`](w:/app/Http/Controllers/ManageController.php) — method `sessions()`

```php
private function sessions() {
    return DB::table('sesi_ujians as s')
        ->join('users as u', ...)
        ->join('jadwal_ujians as j', ...)
        ->leftJoin('jawaban_siswas as a', ...)
        ->leftJoin('sesi_ujian_soals as sq', ...)
        ->select('s.*', 'u.name', 'u.username', 'j.durasi_menit',
            DB::raw('count(distinct a.id) as terjawab'),
            DB::raw('count(distinct sq.id) as total_soal'))
        ->groupBy(...)
        ->orderByDesc('s.id')
        ->get()  // ← Ambil SEMUA sesi tanpa limit!
        ->map(function($x) {
            // Transformasi di PHP untuk semua record
        });
}
```

**Masalah lanjutan — method `index()` ManageController:**

```php
public function index(...) {
    return view('manage.index', [
        'users'     => DB::table('users')->orderBy('role')->orderBy('name')->get(),      // Semua user
        'questions' => DB::table('bank_soals as b')->join(...)->orderByDesc('b.id')->get(), // Semua soal
        'sessions'  => $this->sessions(),                                                   // Semua sesi
        'pending'   => DB::table('jawaban_siswas as j')->join(...)->get(),                 // Semua jawaban pending
        // + 7 query lagi sekaligus
    ]);
}
```

**Dampak:** Satu request ke `/kelola` akan memuat **seluruh data** dari 10+ tabel sekaligus ke memori PHP. Jika ada 500 siswa × 100 soal × beberapa ujian, ini bisa muat ratusan ribu baris sekaligus → **PHP OOM atau response > 10 detik**.

**Solusi:**
```php
// Gunakan pagination + lazy loading
'users' => DB::table('users')->orderBy('role')->orderBy('name')->paginate(50),
'questions' => DB::table('bank_soals')->orderByDesc('id')->paginate(50),
// Pisahkan endpoint: jadikan JSON API per-section, bukan load semua di sekali render
```

---

### 3. Permission Check dengan `->fresh()` — Setiap Request

**File:** [`app/Http/Controllers/QuestionBankManagementController.php`](w:/app/Http/Controllers/QuestionBankManagementController.php) — baris 311

```php
private function authorizeQuestions(): void {
    abort_unless(Auth::user()?->fresh()->getAllPermissions()->contains('name', 'manage-questions'), 403);
}
```

**File:** [`app/Http/Controllers/ScheduleManagementController.php`](w:/app/Http/Controllers/ScheduleManagementController.php) — baris 250

```php
private function authorizeSchedules(): void {
    abort_unless(Auth::user()?->fresh()->getAllPermissions()->contains('name', 'manage-schedules'), 403);
}
```

**Masalah:** `->fresh()` memaksa Laravel me-reload ulang model User dari database di setiap request. Kemudian `->getAllPermissions()` memuat ulang semua permission dari tabel `permissions` via Spatie. Ini berarti **setiap request ke endpoint kelola** = minimal **2 query tambahan** hanya untuk cek izin.

**Dampak:** Semua endpoint management kena tambahan latensi ~10–50ms per request dari query duplikat.

**Solusi:**
```php
// Spatie sudah support caching — aktifkan di config/permission.php
'cache' => [
    'expiration_time' => \DateInterval::createFromDateString('24 hours'),
    'key' => 'spatie.permission.cache',
    'store' => 'redis', // gunakan Redis yang sudah ada
],

// Hapus ->fresh() yang tidak perlu:
private function authorizeQuestions(): void {
    // Gunakan Auth::user() saja — tidak perlu fresh() karena session sudah validasi
    abort_unless(Auth::user()?->can('manage-questions'), 403);
}
```

---

### 4. N+1 Query di `ExamResultManagementController::index()` — Halaman Hasil Ujian

**File:** [`app/Http/Controllers/ExamResultManagementController.php`](w:/app/Http/Controllers/ExamResultManagementController.php) — baris 69–80

```php
$schedules = DB::table('jadwal_ujians as j')
    ->...
    ->get(...)
    ->map(function ($schedule) {
        $rows = $this->reports->rows($schedule->id); // ← Query baru per jadwal!
        $schedule->statistik = $this->reports->stats($rows);
        $schedule->hasil = $rows->map(...);
        return $schedule;
    });
```

**Dampak:** Jika ada 20 jadwal ujian untuk satu kelas, maka ada **20 sub-query** `reports->rows()` (yang masing-masing join 4+ tabel). Ini adalah N+1 di level reporting.

**Solusi:**
```php
// Gunakan single query dengan window function atau subquery agregat
// Contoh: ambil semua statistik sekaligus, bukan satu per jadwal
$allStats = DB::table('sesi_ujians as s')
    ->join('jadwal_ujians as j', ...)
    ->whereIn('j.id', $scheduleIds)
    ->select('j.id', DB::raw('count(s.id) as sudah_masuk'), DB::raw('avg(s.nilai_akhir) as rata_rata'))
    ->groupBy('j.id')
    ->get()->keyBy('id');
```

---

## 🟠 MASALAH TINGGI

### 5. Query Tidak Terindeks — `sesi_ujian_soals.bank_soal_id`

**File:** [`database/migrations/2026_06_01_000100_create_cbt_domain.php`](w:/database/migrations/2026_06_01_000100_create_cbt_domain.php)

Tabel `sesi_ujian_soals` hanya punya unique constraint `(sesi_ujian_id, nomor_soal)`. Kolom `bank_soal_id` **tidak punya index** padahal sering diquery:

```php
// WebController::show() — dijalankan setiap save/sync
DB::table('sesi_ujian_soals')
    ->where(['sesi_ujian_id' => $s->id, 'bank_soal_id' => $soalId])
    ->exists(); // ← Full scan pada bank_soal_id!
```

Demikian juga `jawaban_siswas.sesi_ujian_id` tidak punya index eksplisit (hanya dari unique constraint berpasangan). Pada PostgreSQL, foreign key tidak otomatis membuat index.

**Solusi — buat migration baru:**
```php
// database/migrations/2026_06_02_add_performance_indexes.php
public function up(): void {
    Schema::table('sesi_ujian_soals', function (Blueprint $table) {
        $table->index('bank_soal_id');
        $table->index(['sesi_ujian_id', 'bank_soal_id']); // composite
    });
    Schema::table('jawaban_siswas', function (Blueprint $table) {
        $table->index('sesi_ujian_id');
        $table->index(['sesi_ujian_id', 'bank_soal_id']);
    });
    Schema::table('audit_logs', function (Blueprint $table) {
        $table->index('user_id');
        $table->index('sesi_ujian_id');
    });
    Schema::table('session_events', function (Blueprint $table) {
        $table->index('sesi_ujian_id');
    });
    Schema::table('sesi_ujians', function (Blueprint $table) {
        $table->index('user_id');
        $table->index('jadwal_ujian_id');
        $table->index('status');
    });
}
```

---

### 6. `ExamService::remaining()` — Query Redundan Setiap Panggil

**File:** [`app/Services/ExamService.php`](w:/app/Services/ExamService.php) — baris 8

```php
public function remaining($s): int {
    $j = DB::table('jadwal_ujians')->find($s->jadwal_ujian_id); // ← Query setiap kali!
    $deadline = min(...);
    return max(0, now()->diffInSeconds($deadline, false));
}
```

`remaining()` dipanggil di:
- `WebController::show()` → 1 query per tampil soal
- `ExamService::save()` → 1 query per simpan jawaban  
- Sync endpoint → 1 query per sync

Ini berarti untuk setiap save/sync, ada **1 query tambahan** yang mengambil data jadwal yang sama berulang.

**Solusi:**
```php
// Pass $jadwal sebagai parameter atau cache di memory:
public function remaining($s, ?object $jadwal = null): int {
    $j = $jadwal ?? DB::table('jadwal_ujians')->find($s->jadwal_ujian_id);
    // ...
}

// Atau cache per request:
public function remaining($s): int {
    static $cache = [];
    $key = 'jadwal_' . $s->jadwal_ujian_id;
    $cache[$key] ??= DB::table('jadwal_ujians')->find($s->jadwal_ujian_id);
    $j = $cache[$key];
    // ...
}
```

---

### 7. `ExamService::start()` — Query Loop Saat Mulai Ujian

**File:** [`app/Services/ExamService.php`](w:/app/Services/ExamService.php) — baris 6

```php
foreach ($ids as $i => $soal) {
    $opsi = DB::table('opsi_jawabans')
        ->where('bank_soal_id', $soal)
        ->pluck('id')->all(); // ← Query per soal saat mulai ujian!
    
    DB::table('sesi_ujian_soals')->insert([...]); // ← Insert satu per satu!
}
```

Saat siswa menekan "Mulai Ujian" dengan 50 soal PG:
- 50 query `SELECT opsi_jawabans`
- 50 query `INSERT sesi_ujian_soals` (satu per satu!)
- Total: **100 query** hanya untuk memulai ujian → ini yang bikin halaman load lama saat mulai

**Solusi:**
```php
// Ambil semua opsi sekaligus
$allOptions = DB::table('opsi_jawabans')
    ->whereIn('bank_soal_id', $ids)
    ->get(['id', 'bank_soal_id'])
    ->groupBy('bank_soal_id');

// Insert batch sekaligus
$rows = [];
foreach ($ids as $i => $soal) {
    $opsi = $allOptions->get($soal, collect())->pluck('id')->toArray();
    if ($master->acak_opsi) shuffle($opsi);
    $rows[] = [
        'sesi_ujian_id' => $id,
        'bank_soal_id'  => $soal,
        'nomor_soal'    => $i + 1,
        'opsi_order'    => json_encode($opsi),
    ];
}
DB::table('sesi_ujian_soals')->insert($rows); // 1 query saja!
```

---

## 🟡 MASALAH SEDANG

### 8. BCRYPT_ROUNDS=12 — Login Lambat

**File:** [`.env`](w:/.env) — baris 17

```
BCRYPT_ROUNDS=12
```

Laravel default adalah 10. Setiap login perlu hash password dengan 12 rounds = **~300–500ms hanya untuk hash**. Di context sekolah dengan banyak siswa login bersamaan saat ujian dimulai, ini membuat antrian login sangat lambat.

**Solusi:**
```
BCRYPT_ROUNDS=10   # Laravel default, lebih dari cukup untuk keamanan
```

---

### 9. Tidak Ada HTTP Cache / Response Cache

Tidak ditemukan middleware `cache()` atau `response()->header('Cache-Control', ...)` di mana pun. Endpoint seperti:
- `GET /kelola/data/master-sekolah` — data yang jarang berubah
- `GET /dashboard` (data kelas siswa) — berubah paling sering harian
- `GET /kelola/data/paket-soal` — berubah hanya saat guru mengedit

Semua selalu query database meski data tidak berubah.

**Solusi:**
```php
// Di AppServiceProvider atau middleware — tambahkan cache untuk read endpoint
public function index(): JsonResponse {
    $data = Cache::remember('question-packages-' . Auth::id(), 60, function() {
        return $this->packageQuery()->get([...]);
    });
    return $this->ok($data);
}

// Invalidate cache saat ada perubahan
public function storePackage(...) {
    // ... simpan data
    Cache::forget('question-packages-' . Auth::id());
}
```

---

### 10. Log Level DEBUG di Production

**File:** [`.env`](w:/.env) — baris 22

```
LOG_LEVEL=debug
```

Di production (`APP_ENV=production`), log level `debug` berarti **setiap query SQL, setiap event, dan setiap detail internal dicatat ke disk**. Ini menyebabkan I/O disk yang besar dan memperlambat aplikasi.

**Solusi:**
```
LOG_LEVEL=warning   # Hanya log warning dan error di production
```

---

### 11. `ScheduleManagementController` — N+1 di `canArchive()` dalam loop

**File:** [`app/Http/Controllers/ScheduleManagementController.php`](w:/app/Http/Controllers/ScheduleManagementController.php) — baris 77

```php
->map(function ($schedule) {
    $schedule->bisa_diarsipkan = $this->canArchive($schedule->id); // ← 2 query per jadwal!
    return $schedule;
})
```

Dan `canArchive()`:
```php
private function canArchive(int $id): bool {
    $targets   = DB::table('jadwal_ujian_kelas')->where('jadwal_ujian_id', $id)->count(); // query 1
    $downloads = DB::table('hasil_ujian_unduhans')->where('jadwal_ujian_id', $id)->count(); // query 2
    return $targets > 0 && $downloads >= $targets;
}
```

Untuk 20 jadwal = **40 query** hanya untuk cek status "bisa diarsipkan".

**Solusi:**
```php
// Hitung sekali dengan subquery agregat sebelum map()
$archiveStatus = DB::table('jadwal_ujian_kelas as jk')
    ->selectRaw('jk.jadwal_ujian_id, count(jk.kelas_aktif_id) as targets')
    ->whereIn('jk.jadwal_ujian_id', $scheduleIds)
    ->groupBy('jk.jadwal_ujian_id')
    ->get()->keyBy('jadwal_ujian_id');

// ... dst, gunakan lookup O(1) di map()
```

---

## 📊 Estimasi Jumlah Query Per Endpoint (Saat Ini vs Target)

| Endpoint | Saat Ini | Target |
|---|---|---|
| `GET /ujian/sesi/{id}` (50 soal PG) | ~102 query | 4–5 query |
| `POST /ujian/{jadwal}/mulai` (50 soal PG) | ~102 query | 4–5 query |
| `GET /kelola` (500 user, 200 soal) | ~10 query + massive data load | Paginate + API split |
| `GET /kelola/data/jadwal-ujian` (20 jadwal) | ~40+ query | 3–4 query |
| `GET /kelola/data/hasil-ujian` (1 kelas, 5 jadwal) | ~5+ query | 2 query |
| `POST /login` | ~4 query + 300ms bcrypt | ~3 query + 150ms bcrypt |

---

## ✅ Prioritas Perbaikan

| # | Masalah | Effort | Impact | Prioritas |
|---|---|---|---|---|
| 1 | N+1 di `WebController::show()` | 🟡 Medium | 🔴 Kritis | **SEGERA** |
| 2 | Batch insert di `ExamService::start()` | 🟢 Rendah | 🔴 Kritis | **SEGERA** |
| 3 | Hapus `->fresh()` di authorize methods | 🟢 Rendah | 🟠 Tinggi | **Minggu ini** |
| 4 | Tambah database indexes | 🟢 Rendah | 🟠 Tinggi | **Minggu ini** |
| 5 | `BCRYPT_ROUNDS=10` | 🟢 Rendah | 🟠 Tinggi | **Minggu ini** |
| 6 | `LOG_LEVEL=warning` | 🟢 Rendah | 🟡 Sedang | **Minggu ini** |
| 7 | Fix N+1 di `canArchive()` loop | 🟡 Medium | 🟡 Sedang | **Bulan ini** |
| 8 | Pagination di ManageController | 🔴 Tinggi | 🟠 Tinggi | **Bulan ini** |
| 9 | Cache permission Spatie | 🟢 Rendah | 🟡 Sedang | **Bulan ini** |
| 10 | Response caching endpoint statis | 🟡 Medium | 🟡 Sedang | **Bulan ini** |

---

## 🛠 Langkah Perbaikan Cepat (Quick Wins)

### Step 1 — Ubah `.env` sekarang (tidak perlu deploy ulang kode):
```bash
# Edit .env
BCRYPT_ROUNDS=10
LOG_LEVEL=warning

# Lalu clear cache
php artisan config:clear
php artisan cache:clear
```

### Step 2 — Aktifkan cache Spatie Permission:
```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
# Edit config/permission.php: 'store' => 'redis'
php artisan permission:cache-reset
```

### Step 3 — Buat migration index:
```bash
php artisan make:migration add_performance_indexes_to_cbt_tables
# Isi sesuai contoh di Masalah #5 di atas
php artisan migrate
```

### Step 4 — Refactor `WebController::show()` dan `ExamService::start()` sesuai contoh di atas.

---

## 🔍 Tools Debugging Lanjutan

Untuk memantau query secara real-time:
```php
// Tambahkan di AppServiceProvider::boot() saat debugging:
if (config('app.debug')) {
    DB::listen(function ($query) {
        \Log::info('SQL', [
            'sql'      => $query->sql,
            'bindings' => $query->bindings,
            'time'     => $query->time . 'ms',
        ]);
    });
}
```

Atau gunakan Laravel Telescope / Debugbar untuk visualisasi query per request.

---

*Laporan ini dihasilkan melalui analisis statik kode sumber. Pengukuran aktual dapat bervariasi tergantung volume data production.*
