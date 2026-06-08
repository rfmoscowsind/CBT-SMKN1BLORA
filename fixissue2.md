# Fix Issue 2 - Audit Lanjutan CBT-SMKN1BLORA

Dokumen ini berisi temuan tambahan setelah audit ulang lebih luas terhadap controller manajemen, bank soal, hasil ujian, download PDF, device fingerprint, service report, service image, serta konfigurasi cache/session/queue.

File yang direview:

- `.env.example`
- `config/cache.php`
- `config/session.php`
- `config/queue.php`
- `app/Http/Controllers/ManageController.php`
- `app/Http/Controllers/StudentManagementController.php`
- `app/Http/Controllers/StaffManagementController.php`
- `app/Http/Controllers/SchoolMasterController.php`
- `app/Http/Controllers/ScheduleManagementController.php`
- `app/Http/Controllers/QuestionBankManagementController.php`
- `app/Http/Controllers/ExamResultManagementController.php`
- `app/Http/Controllers/ExamResultArchiveController.php`
- `app/Http/Controllers/ExamResultDownloadController.php`
- `app/Http/Controllers/Concerns/HandlesDeviceFingerprints.php`
- `app/Services/ReportService.php`
- `app/Services/ImageService.php`
- `app/Services/IdCodec.php`

Catatan: daftar ini melanjutkan `issuegpt.md`. Beberapa issue inti seperti unique index sesi/jawaban, Redis queue, dan atomic upsert jawaban tetap mengacu ke dokumen pertama.

---

## Ringkasan Temuan Baru

### P0 - Berisiko saat produksi/ujian massal

1. `.env.example` masih mengarah ke SQLite, database queue, database cache, database session, dan `APP_DEBUG=true`.
2. Cache default database membuat `Cache::remember()` di flow jawaban ikut membebani DB.
3. Session default database membuat 1500 siswa menghasilkan beban read/write tabel `sessions`.
4. Job simpan jawaban belum unique/debounce, sehingga banyak save ke soal yang sama tetap menumpuk job.
5. Reset sesi memakai delete langsung dan belum membersihkan Redis pending answer/job lama.
6. Query report PDF di `ReportService` melakukan aggregate `jawaban_siswas` global sebelum difilter jadwal.
7. Manual grading bisa menghitung nilai dari DB yang belum sinkron dengan Redis pending answer.
8. Import siswa/soal masih blocking request dan load seluruh spreadsheet ke memory.

### P1 - Performa dan data consistency

1. Penambahan soal memakai `max(urutan)+1`, rawan duplicate urutan jika request paralel.
2. Endpoint device fingerprint memakai correlated subquery `max(s2.id)` per siswa.
3. Endpoint list jadwal/paket belum punya pagination/filter tanggal.
4. Archive memakai `whereYear()` pada kolom waktu, kurang ramah index dan rawan beda tahun UTC/WIB.
5. `orderByRaw(CAST(r.nama_rombel AS INTEGER))` bisa error jika nama rombel bukan angka.
6. Download PDF menandai hasil sudah diunduh sebelum PDF benar-benar terkirim sukses.
7. `ImageService` decode gambar langsung ke memory tanpa validasi dimensi/pixel limit.

### P2 - Hardening dan maintenance

1. Validasi paket ready belum memastikan teks opsi tidak kosong dan pertanyaan bukan placeholder.
2. Delete user/siswa/staf memakai hard delete; perlu aturan saat user sudah punya sesi/hasil ujian.
3. Device reset menulis event untuk semua sesi historis user, tidak hanya sesi aktif/terbaru.
4. `IdCodec` memakai `APP_KEY` sebagai salt; dokumentasikan bahwa mengganti `APP_KEY` akan mengubah semua hash URL.

---

# 1. Default Production Config Masih Database-Heavy

## Lokasi

`.env.example`

Saat ini terlihat default:

```env
APP_ENV=local
APP_DEBUG=true
DB_CONNECTION=sqlite
SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
```

## Masalah

Untuk CBT 1500 siswa, default ini berbahaya kalau dijadikan dasar deploy produksi. Queue, cache, dan session akan memakai database.

## Dampak

- Tabel `jobs`, `cache`, dan `sessions` ikut membebani DB utama.
- Flow save jawaban sudah pakai Redis, tetapi job dan cache bisa tetap masuk DB jika `.env` tidak diubah.
- Saat ujian massal, bottleneck bisa muncul dari session/cache/queue, bukan dari query ujian saja.

## Rekomendasi

Buat `.env.production.example` khusus CBT:

```env
APP_NAME="CBT SMKN1BLORA"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://cbt.domain.sch.id
APP_TIMEZONE=Asia/Jakarta

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=cbt_system
DB_USERNAME=cbt_user
DB_PASSWORD=change_me

CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_STORE=redis
QUEUE_CONNECTION=redis
REDIS_QUEUE_CONNECTION=default
REDIS_QUEUE=answers

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1

LOG_LEVEL=warning
```

Tambahkan juga checklist deploy:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

---

# 2. Cache Default Database Membuat Flow Jawaban Tetap Menekan DB

## Lokasi

`config/cache.php`

```php
'default' => env('CACHE_STORE', 'database'),
```

`app/Services/ExamService.php`

Flow save menggunakan cache:

```php
Cache::remember("bank_soal:{$soalId}:v2", 7200, ...)
Cache::remember("session_soals:{$s->id}", 7200, ...)
Cache::remember("opsi_soal:{$soalId}", 7200, ...)
```

## Masalah

Jika `CACHE_STORE=database`, setiap cache lookup di save jawaban akan baca/tulis tabel cache. Padahal tujuan cache adalah mengurangi DB load.

## Dampak

- Save jawaban tetap menghasilkan traffic DB.
- Tabel cache menjadi hot table.
- Latency save bisa naik saat 1500 siswa aktif.

## Rekomendasi

Wajib set:

```env
CACHE_STORE=redis
REDIS_CACHE_CONNECTION=cache
```

Tambahkan guard di production boot/check:

```php
if (app()->environment('production') && config('cache.default') !== 'redis') {
    throw new RuntimeException('Production CBT wajib memakai Redis cache.');
}
```

---

# 3. Session Default Database Tidak Ideal untuk CBT Massal

## Lokasi

`config/session.php`

```php
'driver' => env('SESSION_DRIVER', 'database'),
```

## Masalah

Saat CBT, setiap request siswa membawa session. Jika session driver database, request soal, save, sync, ping, dan dashboard akan menyentuh tabel `sessions`.

## Dampak

- DB write/read meningkat drastis.
- Tabel `sessions` bisa menjadi bottleneck.
- Cleanup session database juga menambah beban.

## Rekomendasi

Gunakan Redis session:

```env
SESSION_DRIVER=redis
SESSION_STORE=redis
SESSION_LIFETIME=240
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
```

Jika tetap memakai database session, minimal pastikan index:

```sql
CREATE INDEX IF NOT EXISTS idx_sessions_last_activity
ON sessions (last_activity);

CREATE INDEX IF NOT EXISTS idx_sessions_user_id
ON sessions (user_id);
```

---

# 4. Job Jawaban Per Soal Belum Unique / Debounce

## Lokasi

`app/Services/ExamService.php`

```php
PersistAnswerSnapshot::dispatch($s->id, $q->id)->onQueue('answers');
```

`app/Jobs/PersistAnswerSnapshot.php`

Job hanya memanggil:

```php
$service->flushOne($this->sessionId, $this->questionId);
```

## Masalah

Walaupun Redis hash hanya menyimpan jawaban terbaru per soal, setiap klik/save tetap membuat job baru. Jika siswa mengganti jawaban soal yang sama berkali-kali, job lama akan tetap antre.

## Dampak

- Queue backlog membesar.
- Worker memproses job yang sebagian besar tidak lagi berguna.
- Saat ujian massal, Redis queue bisa penuh oleh job duplikat `(sessionId, questionId)`.

## Rekomendasi

Gunakan unique job berbasis sesi dan soal:

```php
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;

class PersistAnswerSnapshot implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    public int $uniqueFor = 30;

    public function uniqueId(): string
    {
        return $this->sessionId . ':' . $this->questionId;
    }
}
```

Alternatif lain: jangan dispatch setiap save. Gunakan periodic flush:

- save ke Redis saja
- worker scheduler flush pending answer setiap 3-5 detik
- submit tetap `flushAll()`

---

# 5. Reset Sesi Menghapus Row Langsung dan Tidak Membersihkan Redis/Job

## Lokasi

`app/Http/Controllers/ManageController.php`

```php
public function resetSession(Request $request, int $id)
{
    $session = DB::table('sesi_ujians')->find($id);
    $this->clearDeviceAccessForUser((int) $session->user_id, Auth::id(), 'session_reset');
    DB::table('sesi_ujians')->where('id', $id)->delete();
}
```

## Masalah

Reset sesi langsung menghapus `sesi_ujians`, tetapi pending jawaban di Redis masih mungkin ada:

```redis
queue_jawaban:{sessionId}
```

Job `PersistAnswerSnapshot` yang sudah telanjur antre juga masih bisa berjalan setelah sesi dihapus.

## Dampak

- Orphan data jawaban bisa muncul lagi jika foreign key tidak ketat.
- Job bisa error atau menulis data untuk sesi yang sudah di-reset.
- Audit/event menjadi tidak konsisten.

## Rekomendasi

Jangan hard delete sesi aktif. Gunakan status khusus:

```php
DB::transaction(function () use ($id, $session) {
    DB::table('sesi_ujians')
        ->where('id', $id)
        ->lockForUpdate()
        ->update([
            'status' => 'reset',
            'updated_at' => now(),
        ]);

    DB::table('jawaban_siswas')->where('sesi_ujian_id', $id)->delete();
    DB::table('sesi_ujian_soals')->where('sesi_ujian_id', $id)->delete();

    Redis::del("queue_jawaban:$id");
});
```

Tambahkan guard di `flushOne()` dan `flushAll()`:

```php
$session = DB::table('sesi_ujians')->where('id', $sessionId)->first();
if (!$session || !in_array($session->status, ['aktif', 'selesai'], true)) {
    Redis::del("queue_jawaban:$sessionId");
    return;
}
```

---

# 6. ReportService Aggregate Jawaban Global Sebelum Filter Jadwal

## Lokasi

`app/Services/ReportService.php`

```php
$answers = DB::table('jawaban_siswas')
    ->select(
        'sesi_ujian_id',
        DB::raw("coalesce(sum(case when tipe_soal='PG' then skor else 0 end),0) as nilai_pg"),
        DB::raw("coalesce(sum(case when tipe_soal='ISIAN' then skor else 0 end),0) as nilai_isian")
    )
    ->groupBy('sesi_ujian_id');

$query = DB::table('jadwal_ujian_kelas as jk')
    ...
    ->leftJoinSub($answers, 'a', 'a.sesi_ujian_id', '=', 's.id')
    ->where('jk.jadwal_ujian_id', $scheduleId);
```

## Masalah

Subquery `$answers` meng-aggregate seluruh `jawaban_siswas` tanpa filter `scheduleId` terlebih dahulu. PostgreSQL bisa memilih plan yang mahal, terutama jika tabel jawaban sudah besar.

## Dampak

- Generate PDF hasil ujian lambat.
- Download/preview PDF bisa membebani DB saat ujian berlangsung.
- Untuk arsip jangka panjang, query makin berat.

## Rekomendasi

Scope aggregate ke sesi pada jadwal terkait:

```php
$answers = DB::table('jawaban_siswas as a')
    ->join('sesi_ujians as s2', 's2.id', '=', 'a.sesi_ujian_id')
    ->where('s2.jadwal_ujian_id', $scheduleId)
    ->select(
        'a.sesi_ujian_id',
        DB::raw("coalesce(sum(case when a.tipe_soal='PG' then a.skor else 0 end),0) as nilai_pg"),
        DB::raw("coalesce(sum(case when a.tipe_soal='ISIAN' then a.skor else 0 end),0) as nilai_isian")
    )
    ->groupBy('a.sesi_ujian_id');
```

Index pendukung:

```sql
CREATE INDEX IF NOT EXISTS idx_sesi_ujians_jadwal_id
ON sesi_ujians (jadwal_ujian_id, id);

CREATE INDEX IF NOT EXISTS idx_jawaban_siswas_session_tipe
ON jawaban_siswas (sesi_ujian_id, tipe_soal);
```

---

# 7. Manual Grading Bisa Memakai Data Belum Sinkron

## Lokasi

`app/Http/Controllers/ManageController.php`

```php
$answer = DB::table('jawaban_siswas')->find($id);
...
DB::table('jawaban_siswas')->where('id', $id)->update([...]);
$total = DB::table('jawaban_siswas')->where('sesi_ujian_id', $answer->sesi_ujian_id)->sum('skor');
DB::table('sesi_ujians')->where('id', $answer->sesi_ujian_id)->update(['nilai_akhir' => $total]);
```

## Masalah

Jawaban terbaru siswa bisa masih berada di Redis queue dan belum masuk `jawaban_siswas`. Jika guru menilai essay terlalu cepat, nilai total bisa dihitung dari DB yang belum lengkap.

## Dampak

- Nilai akhir bisa kurang akurat.
- Setelah job jawaban berjalan, skor bisa berubah lagi.

## Rekomendasi

Sebelum manual grade, flush semua pending answer sesi tersebut:

```php
$this->exams->flushAll((int) $answer->sesi_ujian_id);
```

Atau batasi grading hanya untuk sesi `selesai` dan pastikan submit sudah `flushAll()` sukses.

Tambahkan guard:

```php
$session = DB::table('sesi_ujians')->find($answer->sesi_ujian_id);
abort_unless($session && $session->status === 'selesai', 422, 'Penilaian hanya untuk sesi selesai.');
```

---

# 8. Import Siswa dan Soal Masih Blocking Request

## Lokasi

`SchoolMasterController::importStudents()`

```php
$rows = IOFactory::load($request->file('file')->getRealPath())->getActiveSheet()->toArray();
foreach (array_slice($rows, 1) as $index => $row) { ... }
```

`QuestionBankManagementController::import()`

```php
$rows = IOFactory::load($request->file('file')->getRealPath())->getActiveSheet()->toArray();
foreach (array_slice($rows, 1) as $index => $row) { ... }
```

`ManageController::bulk()` juga membaca spreadsheet dan sync role per row.

## Masalah

Import membaca seluruh file ke memory dan diproses dalam request web biasa. Validasi file juga belum membatasi `max` ukuran pada beberapa endpoint.

## Dampak

- Request timeout jika file besar.
- Memory PHP bisa naik tinggi.
- Admin bisa tidak sengaja upload file besar dan mengganggu server CBT.
- `syncRoles()` per row menambah query tinggi.

## Rekomendasi

Minimal tambahkan batas file dan jumlah row:

```php
'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:2048']
```

Tambahkan limit row:

```php
abort_if(count($rows) > 2000, 422, 'Maksimal 2000 baris per import.');
```

Untuk produksi, pindahkan import ke queue:

- upload file
- simpan job import
- proses chunk 200-500 row
- tampilkan progress

Optimasi role:

- ambil role `Siswa` sekali
- gunakan batch insert/update jika memungkinkan
- panggil `syncRoles()` hanya untuk user yang berubah role

---

# 9. Penambahan Soal Pakai max(urutan)+1

## Lokasi

`ManageController::question()`

```php
'urutan' => (int) DB::table('bank_soals')->where('paket_soal_id', $data['paket_soal_id'])->max('urutan') + 1,
```

`QuestionBankManagementController::storeQuestion()` dan `import()` juga memakai pola serupa.

## Masalah

Jika dua request menambah soal pada paket yang sama secara paralel, keduanya bisa mendapatkan nilai `max(urutan)` yang sama lalu insert `urutan` duplikat.

## Dampak

- Urutan soal tidak konsisten.
- `orderBy('urutan')` menghasilkan urutan ambigu.
- Import paralel bisa merusak susunan soal.

## Rekomendasi

Tambahkan unique index:

```sql
CREATE UNIQUE INDEX IF NOT EXISTS uq_bank_soals_paket_urutan
ON bank_soals (paket_soal_id, urutan);
```

Saat insert, lock paket:

```php
DB::transaction(function () use ($packageId, $data) {
    DB::table('paket_soals')->where('id', $packageId)->lockForUpdate()->first();

    $next = (int) DB::table('bank_soals')
        ->where('paket_soal_id', $packageId)
        ->max('urutan') + 1;

    DB::table('bank_soals')->insert([... 'urutan' => $next]);
});
```

---

# 10. Device Fingerprint Listing Memakai Correlated Subquery Per Siswa

## Lokasi

`ManageController::deviceFingerprints()`

```php
->leftJoin('sesi_ujians as s', function ($join) {
    $join->on('s.user_id', '=', 'u.id')
        ->whereRaw('s.id = (select max(s2.id) from sesi_ujians s2 where s2.user_id = u.id)');
})
```

## Masalah

Subquery `select max(s2.id)` dieksekusi sebagai correlated subquery terhadap user. Untuk 1500 siswa dan riwayat sesi besar, query bisa berat.

## Dampak

- Halaman device fingerprint lambat.
- Bisa mengganggu DB saat admin membuka monitoring device.

## Rekomendasi

Gunakan subquery aggregate sekali:

```php
$latestSessions = DB::table('sesi_ujians')
    ->selectRaw('user_id, max(id) as latest_session_id')
    ->groupBy('user_id');

DB::table('users as u')
    ->leftJoinSub($latestSessions, 'ls', 'ls.user_id', '=', 'u.id')
    ->leftJoin('sesi_ujians as s', 's.id', '=', 'ls.latest_session_id');
```

Index:

```sql
CREATE INDEX IF NOT EXISTS idx_sesi_ujians_user_id_id
ON sesi_ujians (user_id, id DESC);
```

---

# 11. Archive Menggunakan whereYear pada Kolom UTC

## Lokasi

`ExamResultArchiveController::index()`

```php
->whereYear('j.waktu_mulai', $data['tahun'])
```

## Masalah

`whereYear()` biasanya menghasilkan fungsi pada kolom tanggal. Ini kurang ramah index. Selain itu, `waktu_mulai` tampaknya disimpan UTC, sedangkan user memilih tahun dalam konteks WIB.

## Dampak

- Index waktu tidak maksimal.
- Jadwal sekitar pergantian tahun bisa masuk tahun yang berbeda antara UTC dan WIB.

## Rekomendasi

Gunakan range UTC dari tahun WIB:

```php
$start = Carbon::create($data['tahun'], 1, 1, 0, 0, 0, 'Asia/Jakarta')->utc();
$end = Carbon::create($data['tahun'] + 1, 1, 1, 0, 0, 0, 'Asia/Jakarta')->utc();

$query->where('j.waktu_mulai', '>=', $start)
      ->where('j.waktu_mulai', '<', $end);
```

Index:

```sql
CREATE INDEX IF NOT EXISTS idx_jadwal_ujians_archive_time
ON jadwal_ujians (diarsipkan_at, waktu_mulai);
```

---

# 12. CAST Rombel ke INTEGER Bisa Error

## Lokasi

Beberapa controller memakai:

```php
orderByRaw("CAST(r.nama_rombel AS INTEGER)")
```

## Masalah

Jika `nama_rombel` berisi non angka, misalnya `A`, `TKJ-1`, atau `XII`, PostgreSQL bisa error saat cast.

## Dampak

- Endpoint class/options bisa gagal.
- Data master menjadi bergantung pada format nama rombel angka murni.

## Rekomendasi

Tambahkan kolom numeric khusus:

```sql
ALTER TABLE rombels ADD COLUMN IF NOT EXISTS sort_order integer;
CREATE INDEX IF NOT EXISTS idx_rombels_sort_order ON rombels (sort_order);
```

Lalu order:

```php
->orderBy('r.sort_order')
->orderBy('r.nama_rombel')
```

---

# 13. Download PDF Menandai Sudah Diunduh Sebelum PDF Berhasil

## Lokasi

`ExamResultDownloadController::download()`

```php
DB::table('hasil_ujian_unduhans')->updateOrInsert([...]);
return $this->pdf($schedule, $class)->download(...);
```

## Masalah

Status download dicatat sebelum proses PDF benar-benar selesai dikirim ke client. Jika PDF generation gagal setelah update, jadwal bisa tercatat sudah diunduh.

## Dampak

- Syarat arsip bisa dianggap terpenuhi padahal file belum berhasil diunduh.
- Admin bisa mengarsipkan jadwal terlalu cepat.

## Rekomendasi

Minimal generate PDF dulu, baru catat:

```php
$pdf = $this->pdf($schedule, $class);

DB::table('hasil_ujian_unduhans')->updateOrInsert([...]);

return $pdf->download($this->filename($schedule, $class));
```

Lebih aman lagi: buat tabel download request dengan status `processing/success/failed`, dan generate PDF sebagai file tersimpan.

---

# 14. ImageService Rentan Memory Spike

## Lokasi

`app/Services/ImageService.php`

```php
$src = imagecreatefromstring(file_get_contents($file->getRealPath()));
imagewebp($src, $dir.'/'.$name, 82);
```

## Masalah

File image dibaca penuh ke memory dan didecode ke bitmap. File kecil dengan dimensi sangat besar bisa memakai memory tinggi.

## Dampak

- PHP worker bisa kehabisan memory.
- Upload gambar soal bisa mengganggu request lain.

## Rekomendasi

Tambahkan validasi dimensi:

```php
'gambar' => ['nullable', 'image', 'max:2048', 'dimensions:max_width=2500,max_height=2500']
```

Tambahkan guard di service:

```php
[$width, $height] = getimagesize($file->getRealPath());
abort_if($width * $height > 6_000_000, 422, 'Resolusi gambar terlalu besar.');
```

Pertimbangkan resize sebelum WebP:

- max width 1200-1600 px
- quality 75-82
- strip metadata

---

# 15. Validasi Paket Ready Belum Mengecek Teks Kosong/Placeholder

## Lokasi

`QuestionBankManagementController::storePackage()` membuat soal dan opsi default:

```php
'pertanyaan' => 'Pertanyaan soal nomor ' . $i,
...
'teks_opsi' => '',
'is_benar' => $code === 'A',
```

`ready()` hanya cek minimal opsi dan tepat satu kunci:

```php
abort_if($opts->count() < 2, 422, ...);
abort_unless($opts->where('is_benar', true)->count() === 1, 422, ...);
```

## Masalah

Paket bisa saja dianggap ready walaupun:

- teks opsi masih kosong
- pertanyaan masih placeholder
- bobot nilai belum dicek normal

## Dampak

- Siswa bisa menerima soal kosong.
- CBT terlihat error saat ujian.

## Rekomendasi

Tambahkan validasi di `ready()`:

```php
abort_if(trim($question->pertanyaan) === '', 422, 'Pertanyaan tidak boleh kosong.');
abort_if(str_starts_with($question->pertanyaan, 'Pertanyaan soal nomor'), 422, 'Masih ada soal placeholder.');
abort_if((float) $question->bobot_nilai <= 0, 422, 'Bobot soal harus lebih dari 0.');

foreach ($opts as $opt) {
    abort_if(trim((string) $opt->teks_opsi) === '', 422, 'Teks opsi tidak boleh kosong.');
}
```

---

# 16. Delete User/Siswa/Staf Masih Hard Delete

## Lokasi

`StudentManagementController::destroy()`

```php
$student->delete();
```

`StaffManagementController::destroy()`

```php
$staf->delete();
```

`ManageController::deleteUser()`

```php
DB::table('users')->where('id', $id)->delete();
```

## Masalah

Jika user sudah punya sesi, jawaban, audit log, atau role relation, hard delete bisa menghapus identitas historis hasil ujian atau memicu foreign key error.

## Dampak

- Riwayat hasil ujian kehilangan nama/user.
- Report lama bisa tidak lengkap.
- Audit trail lemah.

## Rekomendasi

Gunakan soft disable, bukan delete:

```php
DB::table('users')->where('id', $id)->update([
    'status_kehadiran' => 'alpha',
    'is_active' => false,
    'updated_at' => now(),
]);
```

Jika tetap delete, cek dulu:

```php
abort_if(DB::table('sesi_ujians')->where('user_id', $id)->exists(), 422, 'User sudah memiliki riwayat ujian. Nonaktifkan saja.');
```

---

# 17. Device Reset Menulis Event untuk Semua Sesi Historis

## Lokasi

`HandlesDeviceFingerprints::clearDeviceAccessForUser()`

```php
$sessionIds = DB::table('sesi_ujians')->where('user_id', $userId)->pluck('id')->all();
...
DB::table('session_events')->insert($events);
```

## Masalah

Jika siswa memiliki banyak sesi historis, satu unlock device akan menulis event ke semua sesi lama.

## Dampak

- Tabel `session_events` cepat membesar.
- Unlock device siswa lama bisa menulis banyak row tidak perlu.

## Rekomendasi

Batasi hanya sesi aktif/terkunci/terbaru:

```php
$sessionIds = DB::table('sesi_ujians')
    ->where('user_id', $userId)
    ->whereIn('status', ['aktif', 'terkunci'])
    ->pluck('id')
    ->all();
```

Jika perlu audit user-level, buat `audit_logs` satu row saja, bukan event per sesi historis.

---

# 18. Device Fingerprint Fresh Query di Setiap Request Siswa

## Lokasi

`HandlesDeviceFingerprints::enforceDeviceFingerprintForUser()`

```php
$freshUser = User::query()->find($user->id);
```

## Masalah

Setiap request siswa ke dashboard, soal, save, sync, ping bisa melakukan query ulang user. Ini memang lebih aman untuk mendeteksi lock terbaru, tetapi menambah beban DB.

## Dampak

- 1500 siswa dengan ping/save/soal aktif bisa menambah banyak query user.
- Beban DB naik untuk validasi device.

## Rekomendasi

Cache pendek 5-10 detik untuk status fingerprint/lock:

```php
$freshUser = Cache::remember("cbt:user-device:{$user->id}", 10, function () use ($user) {
    return User::query()->find($user->id);
});
```

Saat admin lock/unlock, hapus cache:

```php
Cache::forget("cbt:user-device:{$userId}");
```

Untuk endpoint kritis seperti `start()` dan `submit()`, tetap boleh bypass cache.

---

# 19. IdCodec Bergantung APP_KEY

## Lokasi

`app/Services/IdCodec.php`

```php
$this->hashids = new Hashids(config('app.key'), 8);
```

## Masalah

Hash URL sesi/jadwal/soal bergantung pada `APP_KEY`. Jika `APP_KEY` diganti saat aplikasi sudah berjalan, hash lama tidak bisa didecode.

## Dampak

- Link sesi ujian yang sudah terbuka bisa gagal.
- Siswa bisa mendapat 404 setelah rotasi APP_KEY.

## Rekomendasi

Gunakan salt khusus:

```env
ID_CODEC_SALT=isi_salt_stabil_jangan_diganti
```

```php
$this->hashids = new Hashids(config('app.id_codec_salt'), 8);
```

Dokumentasikan: jangan mengganti salt saat ujian aktif.

---

# 20. Endpoint List Jadwal/Paket Perlu Pagination/Filter

## Lokasi

- `ScheduleManagementController::index()` mengambil semua jadwal non-arsip.
- `QuestionBankManagementController::index()` mengambil semua paket.
- `ExamResultDownloadController::schedules()` mengambil semua jadwal non-arsip.

## Masalah

Saat data bertahun-tahun bertambah, endpoint admin bisa makin berat.

## Dampak

- Dashboard admin lambat.
- Query grouping count soal/download makin mahal.

## Rekomendasi

Tambahkan filter:

- tahun ajaran
- semester
- mapel
- status
- tanggal mulai/sampai

Atau pagination:

```php
->paginate(50)
```

Untuk list jadwal download, default tampilkan 90 hari terakhir atau jadwal aktif saja.

---

# Checklist Fix Tambahan

- [ ] Buat `.env.production.example` khusus CBT.
- [ ] Production wajib `CACHE_STORE=redis`.
- [ ] Production wajib `SESSION_DRIVER=redis`.
- [ ] Production wajib `QUEUE_CONNECTION=redis`.
- [ ] Tambah unique/debounce job `PersistAnswerSnapshot`.
- [ ] Reset sesi tidak hard delete tanpa clear Redis.
- [ ] `flushOne()` cek sesi masih valid.
- [ ] `ReportService` aggregate jawaban difilter jadwal dulu.
- [ ] Manual grading flush pending answer atau hanya untuk sesi selesai.
- [ ] Import siswa/soal dibatasi ukuran dan row count.
- [ ] Import besar dipindah ke queue/chunk.
- [ ] Insert soal lock paket dan unique `(paket_soal_id, urutan)`.
- [ ] Device fingerprint listing pakai subquery aggregate latest session.
- [ ] Archive tahun pakai range UTC dari tahun WIB, bukan `whereYear()`.
- [ ] Hilangkan `CAST(r.nama_rombel AS INTEGER)` dari order query.
- [ ] PDF download tidak menandai sukses terlalu awal.
- [ ] Image upload validasi dimensi/pixel limit.
- [ ] Ready paket cek teks opsi kosong dan placeholder soal.
- [ ] User yang sudah punya riwayat ujian jangan hard delete.
- [ ] Device reset jangan insert event ke semua sesi historis.
- [ ] Dokumentasikan risiko ganti `APP_KEY` terhadap hash URL.

---

# Prioritas Eksekusi

## Dikerjakan dulu

1. `.env.production.example` + enforce Redis untuk cache/session/queue.
2. Unique/debounce `PersistAnswerSnapshot`.
3. Reset session aman: clear Redis, guard job, jangan hard delete tanpa cleanup.
4. Scope aggregate `ReportService` ke jadwal.
5. Lock insert urutan soal.

## Setelah itu

1. Import queue/chunk.
2. Optimasi device fingerprint listing.
3. Validasi ready paket lebih ketat.
4. Pagination/filter list jadwal dan paket.
5. Hardening image upload.

---

# Catatan Akhir

Secara fitur, project sudah jauh dari skeleton dan sudah mengarah ke CBT produksi. Setelah audit lanjutan, risiko terbesar tambahan ada pada konfigurasi default yang masih database-heavy dan beberapa proses admin/report yang bisa mengganggu DB ketika ujian berlangsung.

Untuk skenario SMKN 1 Blora dengan 1500 siswa, target arsitektur sebaiknya:

- PostgreSQL hanya untuk data utama dan query hasil.
- Redis untuk queue, cache, session, heartbeat, dan pending answer.
- Worker queue khusus jawaban.
- Report/PDF berat dijalankan di luar jam ujian atau dibuat async.
- Import besar tidak diproses langsung di request web.
