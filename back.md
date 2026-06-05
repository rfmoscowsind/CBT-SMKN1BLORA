# 🔧 BACKEND ARCHITECTURE & API ROUTES - CBT SMKN 1 BLORA

---

## 📐 BACKEND ARCHITECTURE OVERVIEW

```
Request Flow:
┌─ Client (Browser/APK)
│
├─ Nginx Load Balancer
│  └─ Rate Limiting (100 req/s global, 10 req/s login)
│
├─ Octane Reverse Proxy
│  └─ Route to available worker (8000, 8001, 8002, ...)
│
├─ Laravel Router
│  ├─ Middleware Stack
│  │  ├─ Auth Guard (JWT)
│  │  ├─ RBAC Middleware (Spatie)
│  │  ├─ Rate Limiting
│  │  └─ CORS (if needed)
│  │
│  └─ Controller Logic
│     ├─ Write Operations → PostgreSQL Primary (Server 1)
│     ├─ Read Operations (normal) → PostgreSQL Primary (Server 1)
│     ├─ Read Operations (live score) → PostgreSQL Standby (Server 2)
│     ├─ Cache/Session → Redis (Server 2)
│     └─ Queue Jobs → Redis Queue → Background Worker
│
├─ Database Layer
│  ├─ Primary (Server 2): All writes, normal reads
│  ├─ Standby (Server 2): Live score reads only (no load on primary)
│  └─ Replication: Streaming, lag < 100ms
│
└─ Response (JSON)
```

---

## 📦 LARAVEL PROJECT STRUCTURE

```
cbt/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AuthController.php
│   │   │   ├── UserController.php
│   │   │   ├── BankSoalController.php
│   │   │   ├── JadwalUjianController.php
│   │   │   ├── SesiUjianController.php
│   │   │   ├── LaporanController.php
│   │   │   └── AdminController.php
│   │   ├── Middleware/
│   │   │   ├── AuthenticateToken.php
│   │   │   ├── CheckRole.php
│   │   │   ├── CheckExamActive.php
│   │   │   └── LogAudit.php
│   │   └── Requests/
│   │       ├── LoginRequest.php
│   │       ├── CreateJawabanRequest.php
│   │       └── ...
│   ├── Models/
│   │   ├── User.php
│   │   ├── Jurusan.php
│   │   ├── KelasAktif.php
│   │   ├── MataPelajaran.php
│   │   ├── PaketSoal.php
│   │   ├── BankSoal.php
│   │   ├── OpsiJawaban.php
│   │   ├── MasterUjian.php
│   │   ├── JadwalUjian.php
│   │   ├── SesiUjian.php
│   │   ├── JawabanSiswa.php
│   │   ├── AuditLog.php
│   │   └── ...
│   ├── Services/
│   │   ├── ImageProcessorService.php
│   │   ├── QuestionRandomizer.php
│   │   ├── ScoringService.php
│   │   ├── OfflineSyncService.php
│   │   └── AuditLogService.php
│   ├── Jobs/
│   │   ├── ProcessAnswerBatch.php
│   │   ├── SyncOfflineAnswers.php
│   │   ├── CalculateLiveScore.php
│   │   └── GenerateReport.php
│   └── Events/
│       ├── ExamStarted.php
│       ├── AnswerSubmitted.php
│       ├── ExamFinished.php
│       └── ...
├── database/
│   ├── migrations/
│   │   └── [migration files]
│   ├── seeders/
│   │   ├── DatabaseSeeder.php
│   │   ├── JurusanSeeder.php
│   │   ├── UserSeeder.php
│   │   └── ...
│   └── factories/
├── routes/
│   ├── api.php
│   ├── web.php
│   └── channels.php
├── config/
│   ├── cbt.php (custom config)
│   ├── database.php
│   ├── cache.php
│   ├── queue.php
│   └── ...
└── storage/
    ├── app/
    │   └── soal-images/
    ├── logs/
    └── framework/
```

---

## 🗄️ DATABASE SCHEMA

### **A. Users & RBAC**

```sql
-- Jurusan (Department)
CREATE TABLE jurusans (
    id BIGINT PRIMARY KEY,
    nama_jurusan VARCHAR(100),
    kode_jurusan VARCHAR(10) UNIQUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Rombel (Class within Jurusan)
CREATE TABLE rombels (
    id BIGINT PRIMARY KEY,
    nama_rombel VARCHAR(10),
    created_at TIMESTAMP
);

-- Kelas Aktif (Active Class - Jurusan + Rombel + Tingkat)
CREATE TABLE kelas_aktifs (
    id BIGINT PRIMARY KEY,
    tingkat SMALLINT, -- 10, 11, 12
    jurusan_id BIGINT REFERENCES jurusans(id),
    rombel_id BIGINT REFERENCES rombels(id),
    nama_kelas VARCHAR(50), -- Generated: "10 DKV 1"
    created_at TIMESTAMP,
    UNIQUE(tingkat, jurusan_id, rombel_id)
);

-- Users (Spatie RBAC integrated)
CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE, -- NISN for students, NIP for staff
    email VARCHAR(100) UNIQUE,
    password VARCHAR(255),
    nama VARCHAR(100),
    kelas_aktif_id BIGINT REFERENCES kelas_aktifs(id),
    status_kehadiran VARCHAR(10) CHECK (status_kehadiran IN ('hadir', 'alpa', 'izin', 'sakit')) DEFAULT 'hadir',
    last_login_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
CREATE INDEX idx_users_username ON users (username);
CREATE INDEX idx_users_kelas_aktif ON users (kelas_aktif_id);
CREATE INDEX idx_users_kelas_role ON users (kelas_aktif_id);

-- Spatie RBAC tables
CREATE TABLE roles (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(50) UNIQUE, -- SuperAdmin, Admin, Guru, Pengawas, Siswa
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE permissions (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(100) UNIQUE,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE model_has_roles (
    role_id BIGINT REFERENCES roles(id),
    model_id BIGINT,
    model_type VARCHAR(50),
    PRIMARY KEY (role_id, model_id, model_type)
);

CREATE TABLE role_has_permissions (
    permission_id BIGINT REFERENCES permissions(id),
    role_id BIGINT REFERENCES roles(id),
    PRIMARY KEY (permission_id, role_id)
);
```

### **B. Question Bank (Soal)**

```sql
-- Mata Pelajaran (Subject)
CREATE TABLE mata_pelajarans (
    id BIGSERIAL PRIMARY KEY,
    kode_mapel VARCHAR(10) UNIQUE,
    nama_mapel VARCHAR(100),
    created_at TIMESTAMP DEFAULT NOW()
);

-- Paket Soal (Question Package)
CREATE TABLE paket_soals (
    id BIGSERIAL PRIMARY KEY,
    mapel_id BIGINT REFERENCES mata_pelajarans(id),
    pembuat_user_id BIGINT REFERENCES users(id),
    jumlah_pg INT, -- Number of PG questions
    jumlah_isian INT, -- Number of essay questions
    status VARCHAR(10) CHECK (status IN ('draft', 'ready')) DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
CREATE INDEX idx_paket_mapel ON paket_soals (mapel_id);
CREATE INDEX idx_paket_status ON paket_soals (status);

-- Bank Soal (Question)
CREATE TABLE bank_soals (
    id BIGSERIAL PRIMARY KEY,
    paket_soal_id BIGINT REFERENCES paket_soals(id) ON DELETE CASCADE,
    urutan INT, -- Question order in paket
    tipe_soal VARCHAR(10) CHECK (tipe_soal IN ('PG', 'ISIAN')),
    pertanyaan TEXT,
    gambar_url VARCHAR(500), -- CDN URL
    bobot_nilai DECIMAL(5,2), -- Score value (e.g., 5.5)
    pertanyaan_tsv tsvector
        GENERATED ALWAYS AS (to_tsvector('indonesian', coalesce(pertanyaan, ''))) STORED,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
CREATE INDEX idx_bank_soal_paket ON bank_soals (paket_soal_id);
CREATE INDEX idx_bank_soal_urutan ON bank_soals (paket_soal_id, urutan);
CREATE INDEX idx_bank_soal_fts ON bank_soals USING GIN (pertanyaan_tsv);

-- Opsi Jawaban (Answer Options)
CREATE TABLE opsi_jawabans (
    id BIGSERIAL PRIMARY KEY,
    bank_soal_id BIGINT REFERENCES bank_soals(id) ON DELETE CASCADE,
    urutan SMALLINT, -- A, B, C, D, E (1-5)
    teks_opsi TEXT,
    is_benar BOOLEAN, -- Correct answer
    created_at TIMESTAMP DEFAULT NOW()
);
CREATE INDEX idx_opsi_soal ON opsi_jawabans (bank_soal_id);
CREATE INDEX idx_opsi_benar ON opsi_jawabans (bank_soal_id, is_benar);
```

### **C. Exam Scheduling**

```sql
-- Master Ujian (Exam Template)
CREATE TABLE master_ujians (
    id BIGSERIAL PRIMARY KEY,
    judul VARCHAR(200),
    paket_soal_id BIGINT REFERENCES paket_soals(id),
    acak_soal BOOLEAN DEFAULT false,
    acak_opsi BOOLEAN DEFAULT false,
    tampilkan_nilai_akhir BOOLEAN DEFAULT false,
    hasil_visibilitas VARCHAR(10) CHECK (hasil_visibilitas IN ('instant', 'manual', 'scheduled')),
    tanggal_rilis_hasil TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
CREATE INDEX idx_master_paket ON master_ujians (paket_soal_id);

-- Jadwal Ujian (Exam Schedule)
CREATE TABLE jadwal_ujians (
    id BIGSERIAL PRIMARY KEY,
    master_ujian_id BIGINT REFERENCES master_ujians(id),
    waktu_mulai TIMESTAMP,
    waktu_selesai TIMESTAMP,
    durasi_menit INT,
    gunakan_token BOOLEAN DEFAULT false,
    token VARCHAR(20) UNIQUE, -- 6-char token if required
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
CREATE INDEX idx_jadwal_master ON jadwal_ujians (master_ujian_id);
CREATE INDEX idx_jadwal_waktu ON jadwal_ujians (waktu_mulai, waktu_selesai);
CREATE INDEX idx_jadwal_token ON jadwal_ujians (token);

-- Jadwal Ujian Kelas (Pivot - which classes take this exam)
CREATE TABLE jadwal_ujian_kelas (
    jadwal_ujian_id BIGINT REFERENCES jadwal_ujians(id) ON DELETE CASCADE,
    kelas_aktif_id BIGINT REFERENCES kelas_aktifs(id) ON DELETE CASCADE,
    PRIMARY KEY (jadwal_ujian_id, kelas_aktif_id)
);
```

### **D. Exam Sessions & Answers**

```sql
-- Sesi Ujian (Exam Session per student)
CREATE TABLE sesi_ujians (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
    jadwal_ujian_id BIGINT REFERENCES jadwal_ujians(id),
    waktu_login TIMESTAMP,
    waktu_submit TIMESTAMP,
    status VARCHAR(15) CHECK (status IN ('aktif', 'selesai', 'force_closed')) DEFAULT 'aktif',
    ip_address VARCHAR(45),
    device_info JSONB, -- {browser, os, resolution}
    nilai_akhir DECIMAL(5,2),
    last_ping_at TIMESTAMP, -- Updated via heartbeat/ping (setiap 20 detik)
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
CREATE INDEX idx_sesi_user ON sesi_ujians (user_id);
CREATE INDEX idx_sesi_jadwal ON sesi_ujians (jadwal_ujian_id);
CREATE INDEX idx_sesi_status ON sesi_ujians (status);
CREATE INDEX idx_sesi_user_jadwal ON sesi_ujians (user_id, jadwal_ujian_id);
-- Partial index: fast lookup for active sessions
CREATE INDEX idx_sesi_aktif_partial ON sesi_ujians (jadwal_ujian_id, last_ping_at)
    WHERE status = 'aktif';

-- Jawaban Siswa (Student Answer)
CREATE TABLE jawaban_siswas (
    id BIGSERIAL PRIMARY KEY,
    sesi_ujian_id BIGINT REFERENCES sesi_ujians(id) ON DELETE CASCADE,
    bank_soal_id BIGINT REFERENCES bank_soals(id),
    opsi_jawaban_id BIGINT REFERENCES opsi_jawabans(id), -- Null for essay
    jawaban_essay TEXT, -- Essay answer
    tipe_soal VARCHAR(10) CHECK (tipe_soal IN ('PG', 'ISIAN')),
    skor DECIMAL(5,2) DEFAULT 0, -- Auto-filled for PG, manual for ISIAN
    scoring_status VARCHAR(20) CHECK (scoring_status IN ('auto_scored', 'pending_manual', 'manually_scored'))
        DEFAULT 'auto_scored',
    skor_manual DECIMAL(5,2),
    dinilai_oleh_user_id BIGINT REFERENCES users(id),
    tanggal_dinilai TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(sesi_ujian_id, bank_soal_id)
);
CREATE INDEX idx_jawab_sesi ON jawaban_siswas (sesi_ujian_id);
CREATE INDEX idx_jawab_soal ON jawaban_siswas (bank_soal_id);
CREATE INDEX idx_jawab_sesi_bank ON jawaban_siswas (sesi_ujian_id, bank_soal_id);
-- Partial index: pending manual scoring
CREATE INDEX idx_jawab_pending_scoring ON jawaban_siswas (sesi_ujian_id)
    WHERE scoring_status = 'pending_manual';
```

### **E. Performance Indexes for High Concurrency (1500 concurrent users)**

To support high concurrency loads (1500 students submitting answers at the same time), specific indexes have been added:

1. **`users` Table:**
   - `idx_users_kelas_aktif` on `kelas_aktif_id`
   - `idx_users_kelas_role` composite on `(kelas_aktif_id, status_kehadiran)`

2. **`sesi_ujians` Table:**
   - `idx_sesi_user` on `user_id`
   - `idx_sesi_jadwal` on `jadwal_ujian_id`
   - `idx_sesi_status` on `status`
   - **Partial Index:** `idx_sesi_aktif_partial` ON `sesi_ujians (jadwal_ujian_id, last_ping_at) WHERE status = 'aktif'`
     (fast lookup for active + recently pinged sessions — helps online/offline detection)
   - Catatan: `sesi_ujian_soals` bukan tabel fisik PostgreSQL. Urutan soal & acakan opsi disimpan di **Redis cache** (`exam_soal:{sesi_id}`).

3. **`bank_soals` Table:**
   - `idx_bank_soal_paket` on `paket_soal_id`
   - `idx_bank_soal_urutan` composite on `(paket_soal_id, urutan)`
   - **GIN Index:** `idx_bank_soal_fts` USING GIN `(pertanyaan_tsv)` — full-text search in Bahasa Indonesia

4. **`jawaban_siswas` Table:**
   - `idx_jawab_sesi` on `sesi_ujian_id`
   - `idx_jawab_sesi_bank` composite on `(sesi_ujian_id, bank_soal_id)`
   - **Partial Index:** `idx_jawab_pending_scoring` ON `jawaban_siswas (sesi_ujian_id) WHERE scoring_status = 'pending_manual'`
     (speeds up teacher's grading query)

5. **`opsi_jawabans` Table:**
   - `idx_opsi_soal` on `bank_soal_id`
   - `idx_opsi_benar` composite on `(bank_soal_id, is_benar)` — fast correct-answer lookup

6. **`session_events` Table:**
   - `idx_session_events_sesi` on `sesi_ujian_id`

### **F. Audit & Logging**

```sql
-- Audit Logs (detailed answer tracking - PostgreSQL Native Range Partitioned by Month)
CREATE TABLE audit_logs (
    id              BIGSERIAL,
    sesi_ujian_id   BIGINT REFERENCES sesi_ujians(id) ON DELETE CASCADE,
    user_id         BIGINT REFERENCES users(id) ON DELETE SET NULL,
    action          VARCHAR(255) NOT NULL,
    bank_soal_id    BIGINT REFERENCES bank_soals(id) ON DELETE NO ACTION,
    payload         JSONB,
    ip_address      VARCHAR(45),
    created_at      TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at      TIMESTAMP(0) WITHOUT TIME ZONE
) PARTITION BY RANGE (created_at);

-- Example partitions for year 2026:
CREATE TABLE audit_logs_2026_01 PARTITION OF audit_logs FOR VALUES FROM ('2026-01-01') TO ('2026-02-01');
CREATE TABLE audit_logs_2026_02 PARTITION OF audit_logs FOR VALUES FROM ('2026-02-01') TO ('2026-03-01');
...
CREATE TABLE audit_logs_2026_06 PARTITION OF audit_logs FOR VALUES FROM ('2026-06-01') TO ('2026-07-01');
CREATE TABLE audit_logs_2026_07 PARTITION OF audit_logs FOR VALUES FROM ('2026-07-01') TO ('2026-08-01');

-- Indexes on Partitioned Table (Auto-inherited on each partition)
CREATE INDEX idx_audit_user_id ON audit_logs (user_id);
CREATE INDEX idx_audit_sesi_id ON audit_logs (sesi_ujian_id);
CREATE INDEX idx_audit_created ON audit_logs (created_at);

-- Session Events (login, offline, etc)
CREATE TYPE event_type AS ENUM ('login', 'logout', 'offline_detected', 'back_online', 'tab_switch');
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

## 🔐 MIDDLEWARE STACK

### **1. Authentication Middleware**

```php
// app/Http/Middleware/AuthenticateToken.php
class AuthenticateToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        try {
            $payload = JWT::decode($token, new Key(env('JWT_SECRET'), 'HS256'));
            auth()->setUser(User::find($payload->user_id));
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid token'], 401);
        }
        
        return $next($request);
    }
}
```

### **2. RBAC Middleware**

```php
// app/Http/Middleware/CheckRole.php
class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (!auth()->user()->hasAnyRole($roles)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        
        return $next($request);
    }
}

// Usage: middleware('role:SuperAdmin,Admin')
```

### **3. Exam Active Middleware**

```php
// app/Http/Middleware/CheckExamActive.php
class CheckExamActive
{
    public function handle(Request $request, Closure $next)
    {
        $sesiId = $request->route('sesi_id');
        $sesi = SesiUjian::findOrFail($sesiId);
        
        if ($sesi->status !== 'aktif') {
            return response()->json(['error' => 'Exam not active'], 410);
        }
        
        if ($sesi->getRemainingSeconds() <= 0) {
            return response()->json(['error' => 'Exam time expired'], 410);
        }
        
        return $next($request);
    }
}
```

### **4. Audit Logging Middleware**

```php
// app/Http/Middleware/LogAudit.php
class LogAudit
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        
        // Log write operations
        if (in_array($request->method(), ['POST', 'PUT', 'DELETE'])) {
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => $request->path(),
                'method' => $request->method(),
                'ip_address' => $request->ip(),
                'payload' => $request->getContent(),
                'response_code' => $response->getStatusCode()
            ]);
        }
        
        return $response;
    }
}
```

---

## 🛣️ API ROUTES SPECIFICATION

### **GROUP 1: AUTHENTICATION & AUTHORIZATION**

#### **POST /api/auth/login**
```
Purpose: Student/Teacher login
Auth: None (public)
Rate Limit: 10 req/s per IP
Parameters:
  - username: String (NISN or NIP)
  - password: String

Response (200):
{
  "token": "eyJhbGc...",
  "user": {
    "id": 1,
    "nama": "Budi Santoso",
    "username": "123456789",
    "roles": ["Siswa"],
    "permissions": ["ambil_soal", "simpan_jawaban"],
    "kelas_aktif_id": 5
  },
  "expires_in": 28800  // 8 hours
}

Response (401):
{
  "error": "Invalid credentials"
}
```

**Implementation:**
```php
// routes/api.php
Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:login_limit')
    ->name('login');

// app/Http/Controllers/AuthController.php
public function login(LoginRequest $request)
{
    $user = User::where('username', $request->username)->first();
    
    if (!$user || !Hash::check($request->password, $user->password)) {
        return response()->json(['error' => 'Invalid credentials'], 401);
    }
    
    $token = JWT::encode(
        ['user_id' => $user->id, 'exp' => now()->addHours(8)->timestamp],
        env('JWT_SECRET'),
        'HS256'
    );
    
    $user->update(['last_login_at' => now()]);
    
    // Load roles dengan method yang benar (eager load pada model instance)
    $user->load('roles');
    
    return response()->json([
        'token' => $token,
        'user' => $user,
        'expires_in' => 28800
    ]);
}
```

---

#### **POST /api/auth/logout**
```
Purpose: Logout and invalidate session
Auth: Bearer token required
Parameters: None

Response (200):
{ "message": "Logged out successfully" }
```

---

#### **GET /api/auth/me**
```
Purpose: Get current user info
Auth: Bearer token required
Parameters: None

Response (200):
{
  "user": { ... user object ... },
  "roles": ["Siswa"],
  "permissions": ["ambil_soal", "simpan_jawaban"]
}
```

---

### **GROUP 2: USER MANAGEMENT**

#### **GET /api/admin/siswa**
```
Purpose: Get list of students with pagination & filters
Auth: Bearer token + role:Admin,SuperAdmin
Parameters:
  - page: Int (default 1)
  - per_page: Int (default 15, max 100)
  - kelas_aktif_id: Int (filter by class)
  - search: String (search by nama)
  - status_kehadiran: String (hadir, alpa, izin, sakit)

Response (200):
{
  "data": [
    {
      "id": 1,
      "nama": "Budi Santoso",
      "username": "123456789",
      "kelas_aktif": { "id": 5, "nama_kelas": "10 DKV 1" },
      "status_kehadiran": "hadir",
      "last_login_at": "2026-06-01 10:30:00"
    },
    ...
  ],
  "pagination": {
    "total": 1500,
    "per_page": 15,
    "current_page": 1,
    "last_page": 100
  }
}
```

---

#### **POST /api/admin/siswa/bulk-upload**
```
Purpose: Bulk import students from Excel
Auth: Bearer token + role:Admin,SuperAdmin
Content-Type: multipart/form-data
Parameters:
  - file: Excel file (.xlsx)
  - kelas_aktif_id: Int
  - overwrite: Boolean (default false)

Response (200):
{
  "imported": 150,
  "updated": 20,
  "failed": 5,
  "errors": [
    { "row": 10, "error": "Duplicate username" },
    ...
  ]
}
```

**Implementation:**
```php
public function bulkUploadSiswa(Request $request)
{
    $request->validate([
        'file' => 'required|file|mimes:xlsx,xls',
        'kelas_aktif_id' => 'required|exists:kelas_aktifs,id'
    ]);
    
    dispatch(new ImportStudentsJob($request->file('file'), $request->kelas_aktif_id));
    
    return response()->json(['message' => 'Import started in background']);
}
```

---

### **GROUP 3: BANK SOAL (QUESTION MANAGEMENT)**

#### **POST /api/guru/paket-soal/init**
```
Purpose: Create new question package (wizard start)
Auth: Bearer token + role:Guru,SuperAdmin
Parameters:
  - mapel_id: Int (required)
  - judul: String (optional, description)

Response (201):
{
  "paket_id": 123,
  "mapel": { "id": 1, "nama_mapel": "Matematika" },
  "pembuat": { "id": 5, "nama": "Bu Siti" },
  "step": 1,
  "next_endpoint": "/api/guru/paket-soal/123/tambah"
}
```

---

#### **POST /api/guru/paket-soal/{paket_id}/tambah**
```
Purpose: Add single question to package (wizard step 2+)
Auth: Bearer token + role:Guru,SuperAdmin
Content-Type: multipart/form-data
Parameters:
  - urutan: Int (question order)
  - tipe_soal: Enum (PG, ISIAN)
  - pertanyaan: String (question text)
  - gambar: File (optional, max 2MB)
  - bobot_nilai: Decimal (e.g., 5.5)
  - opsi: Array (for PG only) [
      { teks_opsi: "A", is_benar: false },
      { teks_opsi: "B", is_benar: true },
      ...
    ]

Response (201):
{
  "soal_id": 456,
  "status": "saved",
  "image_url": "https://cdn.cbt.local/soal/xyz123.webp",
  "next_action": "Lanjut ke soal berikutnya"
}
```

**Implementation:**
```php
public function tambahSoal(Request $request, $paketId)
{
    $paket = PaketSoal::findOrFail($paketId);
    
    // Verify ownership
    if ($paket->pembuat_user_id !== auth()->id()) {
        return response()->json(['error' => 'Forbidden'], 403);
    }
    
    $soal = BankSoal::create([
        'paket_soal_id' => $paketId,
        'urutan' => $request->urutan,
        'tipe_soal' => $request->tipe_soal,
        'pertanyaan' => $request->pertanyaan,
        'bobot_nilai' => $request->bobot_nilai
    ]);
    
    // Process image if provided
    if ($request->hasFile('gambar')) {
        $imageUrl = app(ImageProcessorService::class)->processQuestionImage(
            $request->file('gambar'),
            $paketId
        );
        $soal->update(['gambar_url' => $imageUrl]);
    }
    
    // Add options if PG
    if ($request->tipe_soal === 'PG') {
        foreach ($request->opsi as $idx => $opsi) {
            OpsiJawaban::create([
                'bank_soal_id' => $soal->id,
                'urutan' => $idx + 1,
                'teks_opsi' => $opsi['teks_opsi'],
                'is_benar' => $opsi['is_benar']
            ]);
        }
    }
    
    return response()->json([
        'soal_id' => $soal->id,
        'image_url' => $soal->gambar_url,
        'status' => 'saved'
    ], 201);
}
```

---

#### **GET /api/guru/paket-soal**
```
Purpose: Get list of question packages created by guru
Auth: Bearer token + role:Guru
Parameters:
  - page: Int
  - status: Enum (draft, ready)

Response (200):
{
  "data": [
    {
      "id": 123,
      "judul": "Paket Matematika Kelas 10",
      "mapel": "Matematika",
      "jumlah_pg": 30,
      "jumlah_isian": 10,
      "status": "ready",
      "pembuat": "Bu Siti",
      "created_at": "2026-05-15"
    }
  ]
}
```

---

### **GROUP 4: JADWAL UJIAN (EXAM SCHEDULING)**

#### **POST /api/admin/master-ujian**
```
Purpose: Create exam template
Auth: Bearer token + role:Admin,SuperAdmin
Parameters:
  - judul: String
  - paket_soal_id: Int
  - acak_soal: Boolean
  - acak_opsi: Boolean
  - tampilkan_nilai_akhir: Boolean
  - hasil_visibilitas: Enum (instant, manual, scheduled)
  - tanggal_rilis_hasil: DateTime (if scheduled)

Response (201):
{
  "master_ujian_id": 789,
  "judul": "Ujian Mid Semester Matematika",
  "created_at": "2026-06-01"
}
```

---

#### **POST /api/admin/jadwal**
```
Purpose: Create exam schedule
Auth: Bearer token + role:Admin,SuperAdmin
Parameters:
  - master_ujian_id: Int
  - waktu_mulai: DateTime (e.g., "2026-06-15 08:00:00")
  - waktu_selesai: DateTime
  - durasi_menit: Int
  - gunakan_token: Boolean
  - target_kelas_ids: Array[Int] (e.g., [5, 6, 7])

Response (201):
{
  "jadwal_id": 456,
  "token": "ABC123" (if gunakan_token=true),
  "kelas_target": 3,
  "start_time": "2026-06-15 08:00:00",
  "duration_minutes": 120
}
```

**Implementation:**
```php
public function createJadwal(Request $request)
{
    $jadwal = JadwalUjian::create([
        'master_ujian_id' => $request->master_ujian_id,
        'waktu_mulai' => $request->waktu_mulai,
        'waktu_selesai' => $request->waktu_selesai,
        'durasi_menit' => $request->durasi_menit,
        'gunakan_token' => $request->gunakan_token,
        'token' => $request->gunakan_token ? Str::random(6) : null
    ]);
    
    // Attach target classes
    $jadwal->kelasAktifs()->sync($request->target_kelas_ids);
    
    return response()->json([
        'jadwal_id' => $jadwal->id,
        'token' => $jadwal->token,
        'kelas_target' => count($request->target_kelas_ids)
    ], 201);
}
```

---

#### **GET /api/admin/jadwal**
```
Purpose: Get list of exam schedules
Auth: Bearer token + role:Admin,SuperAdmin
Parameters:
  - page: Int
  - filter_status: Enum (upcoming, ongoing, completed)

Response (200):
{
  "data": [
    {
      "id": 456,
      "judul_ujian": "Ujian Mid Semester Matematika",
      "waktu_mulai": "2026-06-15 08:00:00",
      "waktu_selesai": "2026-06-15 10:00:00",
      "durasi_menit": 120,
      "status": "upcoming",
      "kelas_target": 3,
      "gunakan_token": true,
      "peserta_terdaftar": 150
    }
  ]
}
```

---

#### **POST /api/admin/jadwal/{id}/regenerate-token**
```
Purpose: Regenerate exam token
Auth: Bearer token + role:SuperAdmin
Parameters: None

Response (200):
{
  "token": "XYZ789"
}
```

---

### **GROUP 5: EXAM EXECUTION (SISWA)**

#### **POST /api/ujian/masuk**
```
Purpose: Student enter exam (login to exam session)
Auth: Bearer token + role:Siswa
Parameters:
  - jadwal_ujian_id: Int
  - token: String (optional, if gunakan_token=true)

Response (200):
{
  "sesi_id": 999,
  "status": "aktif",
  "waktu_mulai": "2026-06-15 08:00:00",
  "durasi_menit": 120,
  "sisa_waktu_detik": 7200,
  "total_soal": 40,
  "petunjuk": "Kerjakan soal sesuai waktu yang tersedia...",
  "next_endpoint": "/api/ujian/ambil-soal/{sesi_id}"
}

Response (403):
{
  "error": "Token tidak sesuai"
  // or
  "error": "Kelas Anda tidak terdaftar untuk ujian ini"
  // or
  "error": "Status kehadiran: alpa"
}

Response (410):
{
  "error": "Waktu ujian telah berakhir"
}
```

**Implementation:**
```php
public function masukUjian(Request $request)
{
    $request->validate([
        'jadwal_ujian_id' => 'required|exists:jadwal_ujians,id',
        'token' => 'nullable|string'
    ]);
    
    $jadwal = JadwalUjian::findOrFail($request->jadwal_ujian_id);
    $user = auth()->user();
    
    // Validate 1: Time check
    if (now() < $jadwal->waktu_mulai || now() > $jadwal->waktu_selesai) {
        return response()->json(['error' => 'Waktu ujian tidak valid'], 410);
    }
    
    // Validate 2: Token check
    if ($jadwal->gunakan_token && $request->token !== $jadwal->token) {
        return response()->json(['error' => 'Token tidak sesuai'], 403);
    }
    
    // Validate 3: Class enrollment
    $isEnrolled = $jadwal->kelasAktifs()->where('kelas_aktif_id', $user->kelas_aktif_id)->exists();
    if (!$isEnrolled) {
        return response()->json(['error' => 'Kelas tidak terdaftar'], 403);
    }
    
    // Validate 4: Attendance
    if ($user->status_kehadiran !== 'hadir') {
        return response()->json(['error' => 'Status kehadiran: ' . $user->status_kehadiran], 403);
    }
    
    // Create session
    $sesi = SesiUjian::create([
        'user_id' => $user->id,
        'jadwal_ujian_id' => $jadwal->id,
        'waktu_login' => now(),
        'status' => 'aktif',
        'last_ping_at' => now(),
        'ip_address' => $request->ip(),
        'device_info' => json_encode([
            'browser' => $request->header('User-Agent'),
            'os' => $request->header('User-Agent')
        ])
    ]);
    
    // Cache randomized questions to Redis
    $paket = $jadwal->masterUjian->paketSoal;
    $soalIds = $paket->bankSoals()->pluck('id')->toArray();
    
    if ($jadwal->masterUjian->acak_soal) {
        shuffle($soalIds);
    }
    
    $cacheKey = "exam_soal:{$sesi->id}";
    Redis::setex($cacheKey, $jadwal->durasi_menit * 60, json_encode($soalIds));
    
    // Log session event
    SessionEvent::create([
        'sesi_ujian_id' => $sesi->id,
        'event_type' => 'login',
        'timestamp' => now()
    ]);
    
    return response()->json([
        'sesi_id' => $sesi->id,
        'status' => 'aktif',
        'waktu_mulai' => $jadwal->waktu_mulai,
        'durasi_menit' => $jadwal->durasi_menit,
        'sisa_waktu_detik' => $jadwal->getRemainingSeconds(),
        'total_soal' => count($soalIds)
    ]);
}
```

---

#### **GET /ujian/sesi/{id}**
```
Purpose: Get exam session metadata & question navigation status (Anti-Leak)
Auth: Cookie/Session (Web Auth)
Parameters: None (expects JSON headers)

Response (200):
{
  "status": "aktif",
  "sisa_detik": 7200,
  "identitas": {
    "mapel": "Matematika",
    "judul": "Ujian Mid Semester Matematika",
    "id": 12
  },
  "siswa": {
    "nama": "Budi Santoso",
    "kelas": "10 DKV 1"
  },
  "total_soal": 40,
  "navigasi": [
    { "nomor": 1, "terjawab": true, "ragu": false },
    { "nomor": 2, "terjawab": false, "ragu": true },
    ...
  ]
}
```

---

#### **GET /ujian/sesi/{id}/soal**
```
Purpose: Get single question by sequence number (Anti-Leak lazy loading)
Auth: Cookie/Session (Web Auth) + Rate Limit (300 req/min)
Parameters:
  - nomor: Int (required query param)

Response (200):
{
  "nomor": 1,
  "total_soal": 40,
  "sisa_detik": 7195,
  "hash_id": "kX7pQ2", 
  "tipe": "PG",
  "pertanyaan": "Berapakah hasil dari 2 + 2?",
  "gambar_url": null,
  "opsi": [
    { "hash_id": "aB1cD3", "teks": "3" },
    { "hash_id": "eF4gH5", "teks": "4" }
  ],
  "jawaban_siswa": "eF4gH5", // Hashed option ID (or text for essay)
  "ragu": false
}
```

**Implementation:**
```php
public function show(Request $r, string $hash) {
    $s = $this->owned($hash);

    if ($r->expectsJson() || $r->wantsJson()) {
        if ($s->status !== 'aktif') {
            return response()->json(['status' => $s->status, 'redirect' => true]);
        }

        // Verifikasi waktu server-side
        $sisa = $this->exams->remaining($s);
        if ($sisa <= 0) {
            $this->exams->submit($s);
            return response()->json(['status' => 'selesai', 'redirect' => true, 'reason' => 'time_expired']);
        }

        $jadwal    = DB::table('jadwal_ujians')->find($s->jadwal_ujian_id);
        $master    = DB::table('master_ujians')->find($jadwal->master_ujian_id);
        $className = DB::table('kelas_aktifs')->where('id', Auth::user()->kelas_aktif_id)->value('nama_kelas') ?? '-';

        // Kirim NAVIGASI saja (nomor + status) — BUKAN konten soal
        $navigasi = DB::table('sesi_ujian_soals as ss')
            ->leftJoin('jawaban_siswas as js', function ($j) use ($s) {
                $j->on('js.sesi_ujian_id', '=', 'ss.sesi_ujian_id')
                  ->on('js.bank_soal_id', '=', 'ss.bank_soal_id');
            })
            ->where('ss.sesi_ujian_id', $s->id)
            ->orderBy('ss.nomor_soal')
            ->get([
                'ss.nomor_soal as nomor',
                'ss.ditandai as ragu',
                DB::raw("CASE WHEN js.opsi_jawaban_id IS NOT NULL OR (js.jawaban_essay IS NOT NULL AND js.jawaban_essay <> '') THEN true ELSE false END as terjawab"),
            ])
            ->map(fn($i) => [
                'nomor'    => $i->nomor,
                'terjawab' => (bool) $i->terjawab,
                'ragu'     => (bool) $i->ragu,
            ])->values();

        return response()->json([
            'status'     => 'aktif',
            'sisa_detik' => $sisa,
            'identitas'  => [
                'mapel'  => $master->judul,
                'judul'  => $jadwal->nama_jadwal ?? $master->judul,
                'id'     => $s->jadwal_ujian_id,
            ],
            'siswa'      => [
                'nama'   => Auth::user()->name,
                'kelas'  => $className,
            ],
            'total_soal' => $navigasi->count(),
            'navigasi'   => $navigasi,
        ]);
    }
}

public function soal(Request $r, string $hash) {
    $s = $this->owned($hash);

    if ($s->status !== 'aktif') {
        return response()->json(['status' => $s->status, 'redirect' => true], 410);
    }

    $sisa = $this->exams->remaining($s);
    if ($sisa <= 0) {
        $this->exams->submit($s);
        return response()->json(['status' => 'selesai', 'redirect' => true, 'sisa_detik' => 0], 410);
    }

    $nomor = max(1, (int) $r->query('nomor', 1));
    $totalSoal = DB::table('sesi_ujian_soals')->where('sesi_ujian_id', $s->id)->count();
    $nomor = min($nomor, $totalSoal);

    $item = DB::table('sesi_ujian_soals')
        ->where('sesi_ujian_id', $s->id)
        ->where('nomor_soal', $nomor)
        ->first();

    abort_unless($item, 404, 'Soal tidak ditemukan.');

    $question = DB::table('bank_soals')->find($item->bank_soal_id);
    abort_unless($question, 404);

    $options = [];
    if ($question->tipe_soal === 'PG') {
        $order = json_decode($item->opsi_order, true) ?: [];
        $rawOptions = DB::table('opsi_jawabans')
            ->whereIn('id', $order)
            ->get(['id', 'teks_opsi'])
            ->keyBy('id');
        foreach ($order as $optId) {
            if ($rawOptions->has($optId)) {
                $options[] = [
                    'hash_id' => $this->ids->encode($optId),
                    'teks'    => $rawOptions[$optId]->teks_opsi,
                ];
            }
        }
    }

    $answer = DB::table('jawaban_siswas')
        ->where('sesi_ujian_id', $s->id)
        ->where('bank_soal_id', $item->bank_soal_id)
        ->first();

    $jawabanSiswa = null;
    if ($answer) {
        $jawabanSiswa = $answer->opsi_jawaban_id
            ? $this->ids->encode($answer->opsi_jawaban_id)
            : $answer->jawaban_essay;
    }

    return response()->json([
        'nomor'         => $nomor,
        'total_soal'    => $totalSoal,
        'sisa_detik'    => $sisa,
        'hash_id'       => $this->ids->encode($question->id),
        'tipe'          => $question->tipe_soal,
        'pertanyaan'    => $question->pertanyaan,
        'gambar_url'    => $question->gambar_url,
        'opsi'          => $options,
        'jawaban_siswa' => $jawabanSiswa,
        'ragu'          => (bool) $item->ditandai,
    ]);
}
```

---

#### **POST /ujian/sesi/{id}/simpan**
```
Purpose: Save student answer with strict server-side timer validation
Auth: Cookie/Session (Web Auth) + Rate Limit (120 req/min)
Parameters:
  - soal_hash: String (Hashed question ID)
  - opsi_hash: String (Hashed option ID, PG only)
  - essay: String (Essay content, ISIAN only)
  - ragu: Boolean (optional, flag doubt state)
  - client_updated_at: Timestamp (optional)

Response (200):
{
  "success": true,
  "sisa_detik": 7180
}

Response (410):
{
  "success": false,
  "status": "selesai",
  "sisa_detik": 0,
  "message": "Waktu ujian telah habis."
}
```

**Implementation:**
```php
public function save(Request $r, string $hash) {
    $s = $this->owned($hash);

    if ($s->status !== 'aktif') {
        if ($r->expectsJson() || $r->wantsJson())
            return response()->json(['success' => false, 'status' => $s->status, 'sisa_detik' => 0], 410);
        return redirect("/ujian/sesi/$hash/hasil");
    }

    // ── Verifikasi waktu dari server ─────────────────────────────────────────
    $sisa = $this->exams->remaining($s);
    if ($sisa <= 0) {
        $this->exams->submit($s);
        if ($r->expectsJson() || $r->wantsJson())
            return response()->json([
                'success'    => false,
                'status'     => 'selesai',
                'sisa_detik' => 0,
                'message'    => 'Waktu ujian telah habis.',
            ], 410);
        return redirect("/ujian/sesi/$hash/hasil");
    }

    $bankSoalId = $this->ids->decode($r->string('soal_hash'));
    $this->exams->save(
        $s,
        $bankSoalId,
        $r->filled('opsi_hash') ? $this->ids->decode($r->string('opsi_hash')) : null,
        $r->input('essay'),
        $r->input('client_updated_at')
    );

    if ($r->has('ragu')) {
        DB::table('sesi_ujian_soals')
            ->where(['sesi_ujian_id' => $s->id, 'bank_soal_id' => $bankSoalId])
            ->update(['ditandai' => $r->boolean('ragu')]);
    }

    if ($r->expectsJson() || $r->wantsJson())
        return response()->json(['success' => true, 'sisa_detik' => $sisa]);
    return redirect("/ujian/sesi/$hash?nomor=" . $r->integer('next_number', 1));
}
```

---

#### **POST /ujian/sesi/{id}/ping**
```
Purpose: Heartbeat — perbarui last_ping_at agar server tahu siswa masih aktif
Auth: Cookie/Session (Web Auth)
Parameters: None

Response (200):
{
  "success": true,
  "sisa_detik": 7180
}

Response (410):
{
  "success": false,
  "status": "selesai",
  "sisa_detik": 0
}
```

**Implementation:**
```php
public function ping(Request $r, string $hash) {
    $s = $this->owned($hash);

    if ($s->status !== 'aktif') {
        return response()->json(['success' => false, 'status' => 'selesai', 'sisa_detik' => 0], 410);
    }

    // Update last_ping_at — server bisa hitung selisih waktu untuk deteksi offline
    DB::table('sesi_ujians')
        ->where('id', $s->id)
        ->update(['last_ping_at' => now()]);

    $sisa = $this->exams->remaining($s);
    if ($sisa <= 0) {
        $this->exams->submit($s);
        return response()->json(['success' => false, 'status' => 'selesai', 'sisa_detik' => 0], 410);
    }

    return response()->json(['success' => true, 'sisa_detik' => $sisa]);
}
```

---

> **Catatan:** Endpoint `POST /api/ujian/ping` sudah dihapus. Gunakan `POST /ujian/sesi/{id}/ping` untuk heartbeat. Yang lama adalah API route yang tidak memiliki implementasi — frontend sudah menggunakan endpoint yang baru (`POST /ujian/sesi/{hash}/ping`).

#### **POST /api/ujian/sync-jawaban-batch**
```
Purpose: Offline sync - bulk send cached answers
Auth: Bearer token + role:Siswa
Parameters:
  - sesi_id: Int
  - answers: Array[{bank_soal_id, opsi_jawaban_id, jawaban_essay}]
  - client_timestamp: Int (ms since epoch)

Response (200):
{
  "synced": 5,
  "remaining_time": 6500,
  "message": "Semua jawaban tersinkronisasi"
}

Response (409):
{
  "conflict": true,
  "question_id": 456,
  "client_answer": 789,
  "server_answer": 790,
  "message": "Ada konflik jawaban, server answer menang"
}

Response (410):
{
  "error": "Waktu ujian berakhir"
}
```

---

#### **POST /api/ujian/submit**
```
Purpose: Submit/finish exam
Auth: Bearer token + role:Siswa
Parameters:
  - sesi_id: Int

Response (200):
{
  "status": "selesai",
  "submitted_at": "2026-06-15 10:00:00",
  "total_soal": 40,
  "soal_terjawab": 39,
  "next_action": "view_results"  // Depends on hasil_visibilitas
}

If hasil_visibilitas='instant':
{
  "status": "selesai",
  "score_pg": 95,
  "score_isian": "Pending manual grading",
  "total": "N/A"
}

If hasil_visibilitas='manual' or 'scheduled':
{
  "status": "selesai",
  "message": "Hasil ujian akan ditampilkan setelah dinilai guru"
}
```

---

### **GROUP 6: MONITORING & PROCTORING**

#### **GET /api/pengawas/sesi-aktif**
```
Purpose: Get active exam sessions (for proctor) — dengan deteksi online/offline via last_ping_at
Auth: Bearer token + role:Pengawas,SuperAdmin
Parameters:
  - jadwal_ujian_id: Int

Response (200):
{
  "data": [
    {
      "sesi_id": 999,
      "siswa_nama": "Budi Santoso",
      "status": "online",
      "soal_terjawab": 15,
      "total_soal": 40,
      "progress_persen": 37.5,
      "sisa_waktu_detik": 6500,
      "last_ping_at": "2026-06-15 09:45:10",
      "device_info": { "browser": "Chrome", "resolution": "1920x1080" }
    },
    ...
  ],
  "total_peserta": 50,
  "sedang_mengerjakan": 48,
  "offline": 2
}
```

**Implementation:**
```php
public function getActiveSessions($jadwalUjianId)
{
    // Threshold: jika last_ping_at > 30 detik yang lalu, anggap offline
    $threshold = now()->subSeconds(30);

    // Step 1: Get sessions first (fast, uses idx_sesi_jadwal, idx_sesi_status)
    $sessions = DB::table('sesi_ujians')
        ->join('users', 'users.id', '=', 'sesi_ujians.user_id')
        ->where('sesi_ujians.jadwal_ujian_id', $jadwalUjianId)
        ->where('sesi_ujians.status', 'aktif')
        ->select([
            'sesi_ujians.id as sesi_id',
            'users.nama as siswa_nama',
            'sesi_ujians.last_ping_at',
            'sesi_ujians.device_info'
        ])
        ->get();

    // Step 2: Count answers separately (uses existing idx_jawab_sesi index)
    $sesiIds = $sessions->pluck('sesi_id');
    $jawabanCounts = DB::table('jawaban_siswas')
        ->whereIn('sesi_ujian_id', $sesiIds)
        ->groupBy('sesi_ujian_id')
        ->selectRaw('sesi_ujian_id, COUNT(*) as count')
        ->pluck('count', 'sesi_ujian_id');

    // Step 3: Merge
    $result = $sessions->map(function ($sesi) use ($jawabanCounts, $threshold) {
            $lastPing = $sesi->last_ping_at;
            $count = $jawabanCounts[$sesi->sesi_id] ?? 0;
            $totalSoal = $this->getTotalSoal($sesi->sesi_id);
            $status = ($lastPing && $lastPing >= $threshold) ? 'online' : 'offline';

            return [
                'sesi_id'          => $sesi->sesi_id,
                'siswa_nama'       => $sesi->siswa_nama,
                'status'           => $status,
                'soal_terjawab'    => (int) $count,
                'total_soal'       => $totalSoal,
                'progress_persen'  => $totalSoal > 0 ? round(($count / $totalSoal) * 100, 1) : 0,
                'sisa_waktu_detik' => $this->exams->remaining(SesiUjian::find($sesi->sesi_id)),
                'last_ping_at'     => $lastPing,
                'device_info'      => json_decode($sesi->device_info),
            ];
        });

    $totalPeserta     = $result->count();
    $sedangMengerjakan = $result->where('status', 'online')->count();
    $offline           = $totalPeserta - $sedangMengerjakan;

    return response()->json([
        'data'               => $result,
        'total_peserta'      => $totalPeserta,
        'sedang_mengerjakan' => $sedangMengerjakan,
        'offline'            => $offline,
    ]);
}
```

---

#### **GET /api/superadmin/live-score**
```
Purpose: Real-time score monitoring (SuperAdmin ONLY)
Auth: Bearer token + role:SuperAdmin + middleware:LiveScoreGuard
Parameters:
  - jadwal_ujian_id: Int

Response (200):
{
  "data": [
    {
      "sesi_id": 999,
      "siswa_nama": "Budi Santoso",
      "score_pg": 85.5,
      "score_isian": "Pending",
      "total_score": "N/A",
      "status": "online",
      "updated_at": "2026-06-15 09:45:30"
    },
    ...
  ],
  "last_update": "2026-06-15 09:45:30"
}
```

**Implementation:**
```php
public function getLiveScore($jadwalUjianId)
{
    // Query STANDBY database (read-only, tidak membebani primary)
    $scores = DB::connection('pgsql_standby')
        ->table('jawaban_siswas')
        ->join('sesi_ujians', 'sesi_ujians.id', '=', 'jawaban_siswas.sesi_ujian_id')
        ->join('users', 'users.id', '=', 'sesi_ujians.user_id')
        ->where('sesi_ujians.jadwal_ujian_id', $jadwalUjianId)
        ->select([
            'sesi_ujians.id as sesi_id',
            'users.nama as siswa_nama',
            DB::raw('SUM(CASE WHEN jawaban_siswas.tipe_soal = \'PG\' THEN jawaban_siswas.skor ELSE 0 END) as score_pg'),
            'sesi_ujians.status'
        ])
        ->groupBy('sesi_ujians.id', 'users.id', 'users.nama', 'sesi_ujians.status')
        ->get();
    
    return response()->json([
        'data' => $scores,
        'last_update' => now()
    ]);
}
```
> Catatan: Field `isian_pending` sudah dihapus dari query karena tidak digunakan di response JSON.

---

#### **POST /api/pengawas/sesi/{sesi_id}/reset**
```
Purpose: Reset student session (allow login from another device)
Auth: Bearer token + role:Pengawas,SuperAdmin
Parameters: None

Response (200):
{
  "message": "Sesi direset, siswa dapat login dari device lain"
}
```

---

### **GROUP 7: GRADING (GURU)**

#### **GET /api/guru/isian-belum-dinilai**
```
Purpose: Get ISIAN questions pending manual grading
Auth: Bearer token + role:Guru
Parameters:
  - jadwal_ujian_id: Int
  - page: Int

Response (200):
{
  "data": [
    {
      "jawaban_id": 5555,
      "siswa_nama": "Budi Santoso",
      "soal_pertanyaan": "Jelaskan konsep...",
      "jawaban_essay": "Jawaban siswa...",
      "soal_id": 456,
      "dinilai": false
    },
    ...
  ],
  "total": 150,
  "per_page": 15,
  "current_page": 1
}
```

---

#### **POST /api/guru/score-isian/{jawaban_id}**
```
Purpose: Score essay question
Auth: Bearer token + role:Guru
Parameters:
  - skor_manual: Decimal (0-100)
  - komentar: String (optional)

Response (200):
{
  "jawaban_id": 5555,
  "skor": 85.5,
  "status": "scored",
  "sesi_total_score_updated": 92.3
}
```

---

### **GROUP 8: REPORTING & ANALYTICS**

#### **GET /api/guru/laporan-ujian/{jadwal_ujian_id}**
```
Purpose: Get exam report
Auth: Bearer token + role:Guru,SuperAdmin
Parameters:
  - format: Enum (json, excel, pdf) [default: json]

Response (200) - JSON:
{
  "jadwal": { ... },
  "statistik": {
    "total_peserta": 150,
    "yang_ujian": 148,
    "rata_rata_nilai": 78.5,
    "nilai_tertinggi": 98.5,
    "nilai_terendah": 42.0
  },
  "hasil_per_siswa": [
    {
      "no_urut": 1,
      "nama": "Budi Santoso",
      "nilai_pg": 85,
      "nilai_isian": 90,
      "total": 87.5,
      "ranking": 5
    },
    ...
  ]
}
```

---

## 🔄 BACKGROUND JOBS (QUEUE WORKERS)

### **1. ProcessAnswerBatch Job**

```php
// app/Jobs/ProcessAnswerBatch.php
class ProcessAnswerBatch implements ShouldQueue
{
    public function handle()
    {
        // Run every 10 seconds (via scheduler)
        // Process by Redis keys only — avoids costly query of 1500 sessions
        $queueKeys = Redis::keys('queue_jawaban:*');
        
        foreach ($queueKeys as $key) {
            $sesiId = str_replace('queue_jawaban:', '', $key);
            $jawabans = Redis::hgetall($key);
            
            if (empty($jawabans)) continue;
            
            DB::transaction(function () use ($sesiId, $jawabans) {
                foreach ($jawabans as $bankSoalId => $jawabanJson) {
                    $jawaban = json_decode($jawabanJson);
                    
                    // For PG: auto-calculate score
                    if ($jawaban->tipe_soal === 'PG') {
                        $soal = BankSoal::find($bankSoalId);
                        $isCorrect = OpsiJawaban::where([
                            'id' => $jawaban->opsi_jawaban_id,
                            'is_benar' => true
                        ])->exists();
                        
                        $skor = $isCorrect ? $soal->bobot_nilai : 0;
                        
                        JawabanSiswa::updateOrCreate(
                            ['sesi_ujian_id' => $sesiId, 'bank_soal_id' => $bankSoalId],
                            [
                                'opsi_jawaban_id' => $jawaban->opsi_jawaban_id,
                                'tipe_soal' => 'PG',
                                'skor' => $skor,
                                'scoring_status' => 'auto_scored'
                            ]
                        );
                    }
                    // For ISIAN: mark as pending
                    else {
                        JawabanSiswa::updateOrCreate(
                            ['sesi_ujian_id' => $sesiId, 'bank_soal_id' => $bankSoalId],
                            [
                                'jawaban_essay' => $jawaban->jawaban_essay,
                                'tipe_soal' => 'ISIAN',
                                'scoring_status' => 'pending_manual'
                            ]
                        );
                    }
                }
            });
            
            // Update live score in Redis
            $totalScore = JawabanSiswa::where([
                'sesi_ujian_id' => $sesiId,
                'scoring_status' => 'auto_scored'
            ])->sum('skor');
            
            Redis::hset("live_score:$sesiId", 'score_pg', $totalScore);
            
            // Remove processed items from queue
            Redis::del($queueKey);
        }
    }
}
```

---

### **2. LogAuditAnswer Job**

```php
// app/Jobs/LogAuditAnswer.php
class LogAuditAnswer implements ShouldQueue
{
    public function __construct(
        public int $sesiId,
        public int $bankSoalId,
        public ?int $opsiId,
        public string $ipAddress
    ) {}
    
    public function handle()
    {
        AuditLog::create([
            'sesi_ujian_id' => $this->sesiId,
            'user_id' => SesiUjian::find($this->sesiId)->user_id,
            'action' => 'answer_submitted',
            'bank_soal_id' => $this->bankSoalId,
            'new_answer_id' => $this->opsiId,
            'ip_address' => $this->ipAddress
        ]);
    }
}
```

---

## 📊 RESPONSE WRAPPER

All API responses follow consistent format:

```php
// Success (2xx)
{
  "success": true,
  "data": { ... },
  "message": "Optional message"
}

// Error (4xx, 5xx)
{
  "success": false,
  "error": "Error message",
  "code": "ERROR_CODE",
  "details": { ... } // Optional
}
```

---

## 🔒 SECURITY NOTES

1. **No Answer Keys in Frontend**: `is_benar` field never exposed to frontend
2. **Hashids Encoding**: All IDs hashed before sending to client
3. **Server Time Authority**: Client timestamp never trusted, server time always used
4. **Database Connection**: Only Server 1 connects to primary DB, standby used for read-only live score
5. **Rate Limiting**: Applied at Nginx + Laravel level
6. **CORS**: Configured only for trusted origins
7. **JWT**: Tokens expire after 8 hours
8. **Audit Logging**: All sensitive actions logged with IP, device info
9. **Ping-based Offline Detection**: Server determines student online/offline status
   by checking if `last_ping_at` is within the last 30 seconds. Threshold configurable.

---

*Last Updated: June 2026*
*Status: Ready for Development*
# BACKEND IMPLEMENTATION STATUS - CBT SMKN 1 BLORA

## Status Implementasi Saat Ini - 2026-06-04

Bagian ini adalah acuan backend terbaru. Isi lama di bawah tetap disimpan sebagai rancangan awal/historis.

### Stack Backend Aktual

Backend production memakai:
- Laravel di `/var/www/html`.
- PHP-FPM `php8.3-fpm` dan `php8.2-fpm`.
- PostgreSQL primary di `192.168.16.121`.
- Redis lokal di server CBT `127.0.0.1:6379`.
- Queue Redis dengan worker systemd.
- Vue SPA dilayani dari Laravel view `app`.

### Redis, Session, Cache, Queue

Konfigurasi `.env` production:

```env
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

Redis dipakai untuk:
- Session web.
- Cache Laravel.
- Queue Laravel.
- Registry session untuk device lock/session invalidation.
- Data runtime tertentu seperti radar/live state.

Catatan:
- Redis remote DB server tidak dipakai lagi untuk aplikasi.
- Jangan membuat config cache dari Windows share karena dapat membuat path `T:\...` di Linux.
- Jika perlu cache, jalankan dari server Linux.

### Worker Production

Service permanen:

```text
cbt-worker.service      -> queue worker answers/default
cbt-scheduler.service   -> schedule worker
cbt-radar.service       -> radar live worker
```

Restart:

```bash
sudo systemctl restart cbt-worker.service cbt-scheduler.service cbt-radar.service
```

### Route Web/API Management Aktual

Route penting di `routes/web.php`:

```php
Route::get('/kelola/data/hasil-ujian/options', [ExamResultManagementController::class, 'options']);
Route::get('/kelola/data/hasil-ujian', [ExamResultManagementController::class, 'index']);

Route::get('/kelola/data/arsip-hasil/options', [ExamResultArchiveController::class, 'options']);
Route::get('/kelola/data/arsip-hasil', [ExamResultArchiveController::class, 'index']);

Route::get('/kelola/data/download-hasil/options', [ExamResultDownloadController::class, 'options']);
Route::get('/kelola/data/download-hasil/preview', [ExamResultDownloadController::class, 'preview']);
Route::get('/kelola/data/download-hasil/download', [ExamResultDownloadController::class, 'download']);
```

### Hasil Ujian Aktif Backend

Controller:
- `app/Http/Controllers/ExamResultManagementController.php`

Fungsi:
- Menyediakan data halaman `/vue/management/hasil`.
- Hanya memuat jadwal belum arsip.
- Filter utama: `jadwal_ujians.diarsipkan_at IS NULL`.

Endpoint `options()`:
- Mengembalikan daftar kelas yang punya jadwal belum arsip.
- Query kelas memakai `whereExists` ke `jadwal_ujian_kelas` dan `jadwal_ujians` dengan `whereNull('ju.diarsipkan_at')`.

Endpoint `index()` tanpa `jadwal_id`:
- Validasi:
  - `tingkat` required integer.
  - `jurusan_id` required integer exists.
  - `rombel_id` required integer exists.
  - `jadwal_id` nullable integer exists.
- Mengambil data kelas.
- Mengambil daftar jadwal ujian untuk kelas tersebut dengan `whereNull('j.diarsipkan_at')`.
- Tidak menghitung hasil siswa.
- Tidak mengirim field `hasil`.

Endpoint `index()` dengan `jadwal_id`:
- Tetap wajib cocok dengan kelas.
- Tetap wajib belum arsip.
- Mengambil satu jadwal.
- Menghitung seluruh hasil siswa untuk jadwal tersebut.
- Mengembalikan `statistik` dan `hasil`.

Data statistik:
- `total_target`
- `sudah_masuk`
- `belum_masuk`
- `rata_rata_nilai`
- `nilai_tertinggi`
- `nilai_terendah`

Data hasil siswa:
- `username`
- `name`
- `status_kehadiran`
- `status`
- `waktu_submit`
- `nilai_pg`
- `nilai_isian`
- `nilai_akhir`
- `sesi_id`
- `ranking`

### Download Hasil Backend

Controller:
- `app/Http/Controllers/ExamResultDownloadController.php`

Fungsi:
- Preview PDF hasil.
- Download PDF hasil.
- Mencatat hasil download per jadwal dan kelas.
- Menentukan apakah jadwal bisa diarsipkan.

Aturan:
- Hanya jadwal belum arsip.
- Filter: `whereNull('j.diarsipkan_at')`.
- Catatan download disimpan di `hasil_ujian_unduhans`.

Endpoint:
- `options()` memuat kelas dan daftar jadwal yang belum arsip.
- `preview()` stream PDF.
- `download()` download PDF dan `updateOrInsert` ke `hasil_ujian_unduhans`.

Tabel terkait:

```sql
hasil_ujian_unduhans:
- jadwal_ujian_id
- kelas_aktif_id
- diunduh_oleh_user_id
- diunduh_at
- created_at
- updated_at
```

### Arsip Hasil Backend

Controller:
- `app/Http/Controllers/ExamResultArchiveController.php`

Fungsi:
- Menyediakan data halaman `/vue/management/arsip-hasil`.
- Hanya memuat jadwal yang sudah diarsipkan.
- Filter utama: `jadwal_ujians.diarsipkan_at IS NOT NULL`.

Endpoint `options()`:
- Mengembalikan:
  - `years`
  - `classes`
- `years` berasal dari `extract(year from jadwal_ujians.waktu_mulai)`.
- Hanya tahun yang memiliki jadwal terarsip.
- `classes` hanya kelas yang punya jadwal terarsip.

Endpoint `index()` tanpa `jadwal_id`:
- Validasi:
  - `tahun` required integer min 2000 max 2100.
  - `tingkat` required integer.
  - `jurusan_id` required exists.
  - `rombel_id` required exists.
  - `jadwal_id` nullable exists.
- Mengambil daftar jadwal arsip untuk kelas dan tahun.
- Tidak menghitung hasil siswa.
- Tidak mengirim field `hasil`.

Endpoint `index()` dengan `jadwal_id`:
- Mengambil detail satu arsip hasil.
- Menghitung statistik dan tabel hasil siswa.
- Mengembalikan metadata arsip:
  - `diarsipkan_at`
  - `diunduh_at`
  - `sudah_diunduh`
  - `sudah_diarsipkan`

Catatan:
- Endpoint download PDF aktif tidak digunakan di halaman arsip karena endpoint tersebut memang khusus jadwal belum arsip.

### Pembagian Status Jadwal

Satu jadwal ujian berada dalam salah satu status operasional berikut:

1. Belum arsip:
   - `jadwal_ujians.diarsipkan_at IS NULL`
   - Muncul di `Hasil Ujian`.
   - Muncul di `Download Hasil`.
   - Bisa di-preview/download PDF.

2. Sudah arsip:
   - `jadwal_ujians.diarsipkan_at IS NOT NULL`
   - Tidak muncul di `Hasil Ujian`.
   - Tidak muncul di `Download Hasil`.
   - Muncul di `Arsip Hasil`.

### Device Lock Backend

Komponen terkait:
- `app/Http/Controllers/WebController.php`
- `app/Http/Controllers/ApiController.php`
- `app/Http/Controllers/ManageController.php`
- `resources/js/utils/fingerprint.js`
- `resources/js/pages/Management/DeviceFingerprints.vue`

Prinsip:
- Siswa ditautkan ke satu perangkat.
- Fingerprint browser/device dikombinasikan dengan local storage anchor.
- Jika fingerprint berbeda, user dapat dikunci.
- Jika terkunci, semua sesi aktif diputus dan siswa harus login ulang.

Perilaku session:
- Session web Redis dihancurkan melalui session handler.
- Fallback DB `sessions` tetap dibersihkan jika ada.
- Registry session user disimpan via Cache agar semua session ID user bisa dihapus.

Perilaku API JWT:
- Login siswa API wajib menyertakan `device_fp` atau header `X-Device-Fingerprint`.
- Request siswa API seperti `me`, `jadwal`, `masuk`, `soal`, `jawaban`, `sync`, `ping`, dan `submit` memverifikasi fingerprint.
- Jika mismatch, user dikunci, sesi aktif ditandai, token/logout API diputus, dan response dapat berupa `423`.
- Staff/admin API tidak diwajibkan memakai fingerprint siswa.

### Monitoring Permission

Endpoint monitoring web:
- `/monitoring/stats`
- `/monitoring/sessions`

Akses:
- User harus login.
- User harus punya permission `monitor-exams`, atau role `SuperAdmin`, `Admin`, `Pengawas`.

### Keamanan Secret

Kebijakan:
- Secret tidak boleh ditulis plaintext di repo.
- File operasional seperti `cred.txt` harus dianggap sementara.
- Jangan commit kredensial.
- Dokumentasi memakai placeholder seperti `<SET_CBT_SECRET_ENV>`.

### Validasi Production Terakhir

Yang sudah diverifikasi:
- PHP lint untuk controller hasil/arsip/download route terkait.
- Vite build production sukses di server.
- `/login` status `200`.
- `/dashboard` redirect login saat belum login.
- Redis Laravel config:
  - host `127.0.0.1`
  - password kosong/null
  - ping berhasil
- Worker permanen aktif:
  - `cbt-worker.service`
  - `cbt-scheduler.service`
  - `cbt-radar.service`

---
