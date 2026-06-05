# 🔴 3 Masalah Kritis pada Dokumentasi CBT SMKN 1 Blora

Dokumen ini mengidentifikasi **3 masalah serius** yang ditemukan di `front.md` dan `back.md` yang harus diperbaiki **sebelum development dimulai**. Masing-masing masalah disertai penjelasan dan solusi.

---

## Masalah 1: Missing Function `muatJawabanLokal()`

### Lokasi
**File:** `front.md` → **ExamInterface.vue** → `fetchSoal()` function (baris ~758)

### Penjelasan
Di dalam fungsi `fetchSoal()`, terdapat pemanggilan:
```js
// Restore jawaban dari localStorage jika server tidak punya (offline recovery)
muatJawabanLokal(nomor);
```

Namun, fungsi `muatJawabanLokal(nomor)` **tidak pernah didefinisikan** di mana pun dalam file. Tidak ada implementasi `const muatJawabanLokal = (nomor) => { ... }`.

Yang ada hanyalah komentar di baris 772:
```js
// loadJawabanLokal: diganti muatJawabanLokal(nomor) per-soal
```

Ini berarti **offline recovery tidak berfungsi**. Jika server kehilangan jawaban siswa (misalnya koneksi terputus lalu tersambung kembali), jawaban dari localStorage tidak akan pernah dipulihkan ke `currentSoal.value.jawaban_siswa`.

### Dampak
- Siswa yang mengalami offline dan jawabannya tersimpan di localStorage **tidak akan melihat jawabannya** saat soal dimuat ulang dari server.
- Siswa akan mengira jawabannya hilang dan mungkin menjawab ulang, menyebabkan duplikasi atau kebingungan.

### Solusi
Tambahkan implementasi fungsi `muatJawabanLokal()` sebelum dipanggil. Fungsi harus membaca localStorage per nomor soal dan mengembalikan jawaban ke `currentSoal` jika server tidak memiliki data:

```javascript
/** Muat jawaban dari localStorage untuk soal tertentu (offline recovery) */
const muatJawabanLokal = (nomor) => {
    const key = `${STORAGE_KEY}_${nomor}`;
    const saved = localStorage.getItem(key);
    if (!saved) return;

    try {
        const data = JSON.parse(saved);
        // Hanya restore jika server tidak punya jawaban
        if (!currentSoal.value.jawaban_siswa && data.jawaban_siswa) {
            currentSoal.value.jawaban_siswa = data.jawaban_siswa;
        }
        // Restore status ragu
        if (data.ragu !== undefined) {
            currentSoal.value.ragu = data.ragu;
        }
    } catch (e) {
        console.warn('Gagal parse localStorage untuk soal', nomor, e);
    }
};
```

---

## Masalah 2: Logic Bug di `AuthController@login`

### Lokasi
**File:** `back.md` → **AuthController** → `login()` method (baris ~569)

### Penjelasan
Kode yang bermasalah:
```php
$user = User::where('username', $request->username)->first();

// ... beberapa baris ...

return response()->json([
    'token' => $token,
    'user' => $user->with('roles')->first(),  // ❌ SALAH
    'expires_in' => 28800
]);
```

**Apa yang salah:**
- `$user` sudah merupakan **Eloquent Model instance** (hasil dari `->first()`).
- `with('roles')` adalah **Query Builder method** yang hanya bisa digunakan pada **Eloquent Builder** (sebelum `->first()` atau `->get()`).
- Memanggil `->with('roles')->first()` pada model instance akan menjalankan **query baru** ke database tanpa filter `where('username', ...)`, sehingga **mengambil user pertama di tabel** (bukan user yang login).

### Dampak
- Response `user` di login akan mengembalikan **data user yang salah** (user pertama di database), bukan user yang sedang login.
- Role dan permission yang dikembalikan tidak sesuai dengan user yang login.
- Role-based redirect di frontend (`switch (role)`) akan mengarahkan ke dashboard yang salah.

### Solusi
Ganti `$user->with('roles')->first()` dengan `$user->load('roles')` untuk **eager load relasi pada model instance yang sudah ada**:

```php
// Load relasi roles pada user yang sudah di-fetch
$user->load('roles');

return response()->json([
    'token' => $token,
    'user' => $user,  // ✅ Sudah termasuk roles
    'expires_in' => 28800
]);
```

Atau alternatif, jika ingin tetap menggunakan query builder, lakukan sejak awal:
```php
$user = User::with('roles')->where('username', $request->username)->first();
```

---

## Masalah 3: Database Syntax Conflict — PostgreSQL vs MySQL

### Lokasi
**File:** `back.md` → **Database Schema** (beberapa lokasi)

### Penjelasan
Arsitektur di awal dokumen menyatakan menggunakan **PostgreSQL**:
> "Write Operations → PostgreSQL Primary (Server 1)"

Namun, beberapa skema SQL menggunakan sintaks **MySQL/MariaDB** yang tidak kompatibel dengan PostgreSQL:

#### a) `AUTO_INCREMENT` (MySQL) — Tidak dikenal di PostgreSQL
```sql
-- ❌ MySQL syntax
CREATE TABLE session_events (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,  ...
```

**PostgreSQL menggunakan `SERIAL` atau `BIGSERIAL` / `IDENTITY`:**
```sql
-- ✅ PostgreSQL syntax
CREATE TABLE session_events (
    id BIGSERIAL PRIMARY KEY,  ...
```

#### b) `ENUM` (MySQL) — Tidak dikenal di PostgreSQL
```sql
-- ❌ MySQL syntax
status_kehadiran ENUM('hadir', 'alpa', 'izin', 'sakit'),
status ENUM('draft', 'ready'),
tipe_soal ENUM('PG', 'ISIAN'),
```

**PostgreSQL menggunakan `CREATE TYPE` atau `VARCHAR` + `CHECK` constraint:**
```sql
-- ✅ PostgreSQL syntax (Option 1: Custom Type)
CREATE TYPE status_kehadiran AS ENUM ('hadir', 'alpa', 'izin', 'sakit');
-- lalu: status_kehadiran status_kehadiran,

-- ✅ PostgreSQL syntax (Option 2: VARCHAR + CHECK — lebih portable)
status_kehadiran VARCHAR(10) CHECK (status_kehadiran IN ('hadir', 'alpa', 'izin', 'sakit')),
```

#### c) `FULLTEXT INDEX` (MySQL) — Tidak dikenal di PostgreSQL
```sql
-- ❌ MySQL syntax
FULLTEXT INDEX ft_pertanyaan (pertanyaan)
```

**PostgreSQL menggunakan `GIN` index dengan `tsvector`:**
```sql
-- ✅ PostgreSQL syntax
-- Tambah kolom tsvector
ALTER TABLE bank_soals ADD COLUMN pertanyaan_tsv tsvector
    GENERATED ALWAYS AS (to_tsvector('indonesian', coalesce(pertanyaan, ''))) STORED;

-- Buat GIN index
CREATE INDEX idx_pertanyaan_fts ON bank_soals USING GIN (pertanyaan_tsv);

-- Query full-text search
SELECT * FROM bank_soals WHERE pertanyaan_tsv @@ to_tsquery('indonesian', 'kata_kunci');
```

### Dampak
Jika skema SQL ini digunakan langsung, **migrasi database akan gagal total** karena:
1. PostgreSQL tidak mengenali `AUTO_INCREMENT`
2. PostgreSQL tidak mengenali `ENUM` inline di `CREATE TABLE`
3. PostgreSQL tidak mengenali `FULLTEXT INDEX`
4. Semua tabel yang menggunakan sintaks MySQL tidak akan terbuat

### Solusi Lengkap
Ganti semua sintaks MySQL dengan PostgreSQL yang kompatibel:

```sql
-- ============================================
-- REPLACEMENT untuk semua tabel yang bermasalah
-- ============================================

-- 1. Buat custom ENUM types (PostgreSQL native)
CREATE TYPE status_kehadiran AS ENUM ('hadir', 'alpa', 'izin', 'sakit');
CREATE TYPE status_paket AS ENUM ('draft', 'ready');
CREATE TYPE tipe_soal AS ENUM ('PG', 'ISIAN');
CREATE TYPE status_sesi AS ENUM ('aktif', 'selesai', 'force_closed');
CREATE TYPE hasil_visibilitas AS ENUM ('instant', 'manual', 'scheduled');
CREATE TYPE scoring_status AS ENUM ('auto_scored', 'pending_manual', 'manually_scored');
CREATE TYPE event_type AS ENUM ('login', 'logout', 'offline_detected', 'back_online', 'tab_switch');

-- 2. users table
CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE,
    email VARCHAR(100) UNIQUE,
    password VARCHAR(255),
    nama VARCHAR(100),
    kelas_aktif_id BIGINT REFERENCES kelas_aktifs(id),
    status_kehadiran status_kehadiran DEFAULT 'hadir',
    last_login_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
CREATE INDEX idx_users_username ON users (username);
CREATE INDEX idx_users_kelas_aktif ON users (kelas_aktif_id);

-- 3. bank_soals table with full-text search
CREATE TABLE bank_soals (
    id BIGSERIAL PRIMARY KEY,
    paket_soal_id BIGINT REFERENCES paket_soals(id) ON DELETE CASCADE,
    urutan INT,
    tipe_soal tipe_soal,
    pertanyaan TEXT,
    gambar_url VARCHAR(500),
    bobot_nilai DECIMAL(5,2),
    pertanyaan_tsv tsvector
        GENERATED ALWAYS AS (to_tsvector('indonesian', coalesce(pertanyaan, ''))) STORED,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
CREATE INDEX idx_bank_soal_paket ON bank_soals (paket_soal_id);
CREATE INDEX idx_bank_soal_urutan ON bank_soals (paket_soal_id, urutan);
CREATE INDEX idx_bank_soal_fts ON bank_soals USING GIN (pertanyaan_tsv);

-- 4. session_events table
CREATE TABLE session_events (
    id BIGSERIAL PRIMARY KEY,
    sesi_ujian_id BIGINT REFERENCES sesi_ujians(id) ON DELETE CASCADE,
    event_type event_type,
    event_data JSONB,
    timestamp TIMESTAMP DEFAULT NOW()
);
CREATE INDEX idx_session_events_sesi ON session_events (sesi_ujian_id);
CREATE INDEX idx_session_events_type ON session_events (event_type);
```

---

## Ringkasan Prioritas Perbaikan

| Prioritas | Masalah | File | Dampak |
|-----------|---------|------|--------|
| 🔴 **P1** | Database syntax conflict (MySQL vs PostgreSQL) | `back.md` | **Semua migrasi gagal** jika dijalankan |
| 🔴 **P2** | Logic bug `$user->with('roles')->first()` | `back.md` | **Login mengembalikan user salah** |
| 🔴 **P3** | Missing function `muatJawabanLokal()` | `front.md` | **Offline recovery tidak berfungsi** |

---
*Dokumen ini hanya untuk laporan — belum ada perubahan kode yang dilakukan.*
*Generated: June 2026*