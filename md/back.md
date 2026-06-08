# Dokumentasi Backend CBT SMKN 1 Blora

Tanggal pembaruan: 2026-06-06  
Status: ditulis ulang berdasarkan struktur project yang ada di repository saat ini.

Dokumen ini menjelaskan sisi backend Laravel: route, controller, service, model, job, command, migration, seeder, config backend, dan test. File frontend Vue/Blade/PWA dijelaskan terpisah di `front.md`, sedangkan kebutuhan server dan operasional dijelaskan di `req.md`.

## Ringkasan Arsitektur Backend

Project ini adalah aplikasi CBT berbasis Laravel dengan dua jalur akses utama:

1. Web session berbasis cookie Laravel untuk login browser, dashboard, management, dan ujian siswa.
2. API `/api/v1` berbasis JWT untuk client/aplikasi eksternal atau mode API.

Backend menyimpan data utama di PostgreSQL. Redis dipakai untuk session/cache/queue, terutama untuk menyimpan jawaban sementara sebelum dipersist ke tabel `jawaban_siswas`. Mekanisme ini mengurangi beban database ketika banyak siswa menyimpan jawaban secara bersamaan.

Alur utama ujian:

1. Siswa login melalui `WebController@login` atau `ApiController@login`.
2. Dashboard siswa mengambil jadwal aktif dari `ExamService@schedulesFor`.
3. Siswa memulai ujian melalui `ExamService@start`.
4. Service membuat `sesi_ujians` dan susunan soal di `sesi_ujian_soals`.
5. Frontend meminta soal per nomor dari `WebController@soal` atau `ApiController@question`.
6. Jawaban dikirim ke `ExamService@save`, masuk Redis `queue_jawaban:{sessionId}`.
7. `PersistAnswerSnapshot` memanggil `ExamService@flushOne` untuk menyimpan satu jawaban ke DB.
8. Saat submit, `ExamService@submit` memanggil `flushAll`, menghitung nilai, dan menandai sesi selesai.

## Stack Backend Aktual

- PHP `^8.3`.
- Laravel Framework `^13.8`.
- PostgreSQL sebagai database utama.
- Redis via extension `phpredis`.
- Queue Laravel dengan connection Redis.
- JWT API memakai `tymon/jwt-auth`.
- RBAC/permission memakai `spatie/laravel-permission`, tetapi project juga masih menyimpan kolom `users.role` untuk logika cepat dan kompatibilitas lama.
- Hash ID publik memakai `vinkla/hashids`.
- PDF laporan memakai `barryvdh/laravel-dompdf`.
- Import/export spreadsheet memakai `maatwebsite/excel`.
- Manipulasi gambar soal memakai `intervention/image`.
- Laravel Octane tersedia sebagai dependency, tetapi route saat ini tetap kompatibel dengan PHP-FPM biasa.

## Route Backend

### `routes/web.php`

File ini adalah pusat route web berbasis session Laravel.

Fungsi utama:

- Redirect `/` ke `/login`.
- Menyediakan login, logout, dan dashboard.
- Menyediakan endpoint CRUD management yang dipakai halaman Vue.
- Menyediakan endpoint ujian web untuk siswa.
- Menyediakan endpoint monitoring/radar.
- Menyediakan fallback SPA `/vue/{any}` ke view `app`.

Route autentikasi:

- `GET /login` menampilkan halaman login.
- `POST /login` memproses login dengan throttle `10,1`.
- `ANY /logout` menghapus session web.
- `GET /auth/user` memberi data user login untuk Vue.

Route management modern:

- `/kelola/data/siswa` untuk manajemen siswa.
- `/api/management/staff` dan `/kelola/data/staf` untuk manajemen staf.
- `/kelola/data/master-sekolah` untuk master tingkat, jurusan, rombel, kelas, mapel.
- `/kelola/data/jadwal-ujian` dan `/kelola/data/master-ujian` untuk jadwal.
- `/kelola/data/paket-soal` untuk bank soal.
- `/kelola/data/hasil-ujian` untuk hasil ujian aktif.
- `/kelola/data/download-hasil` untuk preview/download PDF hasil.
- `/kelola/data/arsip-hasil` untuk arsip hasil.
- `/kelola/data/device-fingerprints` untuk device lock.

Route ujian web:

- `POST /ujian/{jadwal}/mulai` memulai ujian dari hash jadwal.
- `GET /ujian/sesi/{id}` mengambil payload awal sesi.
- `GET /ujian/sesi/{id}/soal` mengambil satu soal.
- `POST /ujian/sesi/{id}/simpan` menyimpan jawaban.
- `POST /ujian/sesi/{id}/sync` sinkronisasi batch jawaban.
- `POST /ujian/sesi/{id}/flag` menandai soal ragu-ragu.
- `POST /ujian/sesi/{id}/ping` heartbeat siswa.
- `POST /ujian/sesi/{id}/event` log event sesi.
- `POST /ujian/sesi/{id}/selesai` submit ujian.
- `GET /ujian/sesi/{id}/hasil` mengambil hasil akhir.

Route monitoring:

- `GET /monitoring/radar`.
- `GET /monitoring/stats`.
- `GET /monitoring/sessions`.

Route ini dibatasi oleh login dan permission/role di closure route.

### `routes/api.php`

File ini adalah route API JSON dengan prefix `/api/v1`.

Auth API:

- `POST /api/v1/auth/login` login JWT, throttle `10,1`.
- `GET /api/v1/auth/me` data user token.
- `POST /api/v1/auth/logout` logout token.

API siswa:

- `GET /api/v1/jadwal`.
- `POST /api/v1/ujian/{id}/masuk`.
- `GET /api/v1/ujian/sesi/{sid}/soal`.
- `POST /api/v1/ujian/sesi/{sid}/jawaban`.
- `POST /api/v1/ujian/sesi/{sid}/sync`.
- `POST /api/v1/ujian/sesi/{sid}/ping`.
- `POST /api/v1/ujian/sesi/{sid}/submit`.
- `POST /api/v1/ujian/sesi/{sid}/event`.

API monitoring dan grading:

- `GET /api/v1/monitoring/sesi-aktif`.
- `GET /api/v1/monitoring/live-score`.
- `GET /api/v1/grading/isian`.
- `POST /api/v1/grading/isian/{id}`.

API admin/guru:

- CRUD siswa, user, role, master, paket soal, soal, jadwal, token, reset sesi.
- Laporan di `/api/v1/laporan/{jadwal}` dan `/api/v1/laporan/{jadwal}/{format}`.

### `routes/console.php`

File route console Laravel. Saat ini dipakai sebagai tempat registrasi command berbasis closure jika diperlukan. Command custom utama ada di `app/Console/Commands/RadarWorker.php`.

## Controller Backend

### `app/Http/Controllers/Controller.php`

Base controller kosong bawaan Laravel. File ini menjadi parent class semua controller lain. Tidak ada logic bisnis di sini.

### `app/Http/Controllers/WebController.php`

Controller utama untuk web session.

Tanggung jawab:

- Login web, logout web, dashboard.
- Routing siswa ke dashboard sesuai role.
- Memulai ujian dari hash jadwal.
- Menampilkan/melayani data sesi ujian.
- Menyimpan jawaban, sync batch, flag ragu-ragu, ping, event, submit, dan hasil.
- Membentuk payload ujian untuk Vue.
- Audit event web penting.

Method penting:

- `loginForm()` menampilkan `resources/views/auth/login.blade.php`.
- `login()` memvalidasi username/password, menjalankan device fingerprint untuk siswa, menyimpan session, dan redirect.
- `logout()` menghapus session.
- `dashboard()` mengembalikan view atau JSON data dashboard sesuai request.
- `start()` decode hash jadwal, validasi device fingerprint, lalu memanggil `ExamService@start`.
- `show()` mengirim payload lengkap sesi aktif.
- `soal()` mengirim satu soal berdasarkan nomor.
- `save()` menyimpan satu jawaban lewat `ExamService@save`.
- `sync()` menyimpan beberapa jawaban sekaligus.
- `flag()` mengubah status `ditandai` pada `sesi_ujian_soals`.
- `ping()` memperbarui `last_seen_at`.
- `event()` mencatat event seperti tab switch/offline.
- `submit()` menyelesaikan ujian.
- `result()` mengambil ringkasan hasil.

Private helper penting:

- `ownedSession()` memastikan session milik user yang sedang login dan masih aktif jika diperlukan.
- `examPayload()` dan `singleQuestionPayload()` menyusun data yang dikirim ke frontend.
- `questionOptions()` menyusun opsi jawaban sesuai order di `sesi_ujian_soals`.
- `navigation()` memberi status nomor soal: terjawab, ragu-ragu, atau kosong.
- `pendingAnswer()` dan `pendingAnswers()` membaca jawaban pending dari Redis/DB.
- `studentSchedules()` menyusun jadwal dashboard siswa.

### `app/Http/Controllers/ApiController.php`

Controller untuk API `/api/v1`.

Tanggung jawab:

- Login JWT.
- Me/logout JWT.
- Jadwal API.
- Start ujian API.
- Ambil soal API.
- Save/sync/ping/submit API.
- Device fingerprint validation untuk siswa.

Controller ini paralel dengan `WebController`, tetapi memakai guard API dan response JSON. API siswa wajib membawa fingerprint melalui payload/header agar token tidak menjadi celah bypass device lock.

### `app/Http/Controllers/AdminApiController.php`

Controller API admin/guru/pengawas di prefix `/api/v1`.

Tanggung jawab:

- List dan CRUD siswa.
- Store/update/delete user.
- Role dan permission sync.
- Master data.
- Paket soal, soal, upload bulk.
- Master ujian dan jadwal.
- Regenerate token.
- Reset sesi siswa.
- Laporan.

File ini adalah API generik/legacy yang masih aktif untuk route `/api/v1/admin/*` dan `/api/v1/guru/*`.

### `app/Http/Controllers/OperationsApiController.php`

Controller operasi API untuk event, monitoring, grading, dan report.

Method penting:

- `event()` mencatat event sesi ujian.
- `active()` menampilkan sesi aktif.
- `scores()` menampilkan skor live.
- `pending()` menampilkan jawaban isian yang perlu dinilai.
- `grade()` memberi skor manual untuk isian.
- `report()` menghasilkan data laporan.

Controller ini memakai `IdCodec` dan `ReportService`.

### `app/Http/Controllers/ManageController.php`

Controller management legacy yang masih dipakai beberapa endpoint `/kelola/*`.

Fungsi:

- CRUD master mapel, jurusan, rombel, kelas.
- CRUD siswa/staf.
- Kehadiran siswa.
- Template/import siswa.
- Template/import soal.
- CRUD paket dan soal.
- Master ujian, jadwal, token.
- Reset sesi.
- Laporan.
- Grade isian.
- Sync permission role.
- Device fingerprint list/unlock/lock.

Walau beberapa fungsi sudah dipindah ke controller khusus, file ini tetap penting karena route legacy dan sebagian UI masih memanggil endpoint di sini.

### `app/Http/Controllers/StudentManagementController.php`

Controller khusus halaman `StudentManagement.vue`.

Fungsi:

- `index()` list siswa dengan filter kelas/pencarian dan data kelas.
- `store()` membuat user siswa.
- `update()` mengubah data siswa.
- `password()` reset password siswa.
- `destroy()` menghapus siswa.
- `ensureStudent()` memastikan target role siswa.

Endpoint utama: `/kelola/data/siswa`.

### `app/Http/Controllers/StaffManagementController.php`

Controller khusus halaman `StaffManagement.vue`.

Fungsi:

- List staf.
- Tambah staf.
- Update staf.
- Reset password staf.
- Hapus staf.

Endpoint utama:

- `/api/management/staff`.
- `/kelola/data/staf`.

### `app/Http/Controllers/SchoolMasterController.php`

Controller master data sekolah.

Fungsi:

- Menampilkan master tingkat, jurusan, rombel, kelas, mapel.
- Tambah/hapus tingkat.
- Tambah/update/hapus jurusan.
- Generate kelas aktif dari tingkat, jurusan, dan rombel.
- Import siswa langsung ke kelas yang baru dibuat.

Endpoint utama:

- `/kelola/data/master-sekolah`.
- `/kelola/data/tingkat`.
- `/kelola/data/jurusan`.
- `/kelola/data/kelas/generate`.
- `/kelola/data/kelas/{id}/import-siswa`.

### `app/Http/Controllers/ScheduleManagementController.php`

Controller jadwal ujian modern.

Fungsi:

- `index()` mengambil master ujian, paket ready, kelas, dan jadwal.
- `storeMaster()` membuat master ujian.
- `updateMaster()` mengubah master ujian.
- `storeSchedule()` membuat jadwal dan relasi kelas target.
- `updateSchedule()` mengubah jadwal.
- `regenerateToken()` membuat token baru.
- `destroy()` melakukan arsip/hapus sesuai aturan.
- `canArchive()` memastikan jadwal bisa diarsipkan setelah download hasil terpenuhi.

Endpoint utama: `/kelola/data/jadwal-ujian` dan `/kelola/data/master-ujian`.

### `app/Http/Controllers/QuestionBankManagementController.php`

Controller bank soal modern.

Fungsi:

- List paket soal dan mapel.
- Detail satu paket beserta soal/opsi.
- Tambah/update/hapus paket.
- Tambah/update/hapus soal.
- Upload gambar soal lewat `ImageService`.
- Import soal dari spreadsheet.
- Menandai paket sebagai `ready`.
- Menjaga paket siap pakai agar tidak diedit sembarangan.

Endpoint utama: `/kelola/data/paket-soal`.

### `app/Http/Controllers/ExamResultManagementController.php`

Controller hasil ujian aktif.

Fungsi:

- `options()` memberi daftar kelas yang memiliki jadwal belum arsip.
- `index()` tanpa `jadwal_id` hanya memberi daftar jadwal untuk kelas.
- `index()` dengan `jadwal_id` menghitung statistik dan hasil siswa.

Aturan utama:

- Hanya jadwal dengan `jadwal_ujians.diarsipkan_at IS NULL`.
- Detail nilai tidak dikirim sebelum user memilih satu ujian.
- Statistik dihitung lewat `ReportService`.

Endpoint utama:

- `/kelola/data/hasil-ujian/options`.
- `/kelola/data/hasil-ujian`.

### `app/Http/Controllers/ExamResultDownloadController.php`

Controller preview/download PDF hasil.

Fungsi:

- `options()` memberi kelas dan jadwal yang belum arsip.
- `preview()` stream PDF ke browser.
- `download()` download PDF dan mencatat ke `hasil_ujian_unduhans`.

Aturan:

- Hanya jadwal belum arsip.
- Download per jadwal dan kelas dicatat unik.
- PDF memakai view `resources/views/reports/pdf.blade.php`.

### `app/Http/Controllers/ExamResultArchiveController.php`

Controller arsip hasil.

Fungsi:

- `options()` memberi daftar tahun dan kelas yang punya jadwal sudah arsip.
- `index()` tanpa `jadwal_id` memberi daftar jadwal arsip.
- `index()` dengan `jadwal_id` memberi statistik dan hasil siswa.

Aturan:

- Hanya jadwal dengan `jadwal_ujians.diarsipkan_at IS NOT NULL`.
- Filter utama: tahun, tingkat, jurusan, rombel, lalu jadwal.
- PDF arsip memakai view `resources/views/reports/archive-pdf.blade.php` bila diperlukan.

### `app/Http/Controllers/CbtController.php`

Controller pendukung CBT lama/alternatif. File ini perlu dipertahankan jika route lama atau view Blade lama masih merujuk ke alur CBT klasik. Perannya berada di sekitar operasi ujian dan dashboard lama.

### `app/Http/Controllers/Concerns/HandlesDeviceFingerprints.php`

Trait reusable untuk device lock.

Fungsi utama:

- Membaca fingerprint dari request/header.
- Mendaftarkan fingerprint pertama siswa.
- Membandingkan fingerprint request dengan fingerprint yang tersimpan.
- Mengunci user jika mismatch.
- Menghapus semua session user yang tercatat.
- Mengembalikan response `423 Locked` saat perangkat tidak sah.

Trait ini menjaga agar logic device lock tidak tersebar di banyak controller.

## Service Layer

### `app/Services/ExamService.php`

Service paling penting untuk domain ujian.

Fungsi:

- `schedulesFor(User $user)` mengambil jadwal relevan. Untuk siswa hanya jadwal kelasnya dan jadwal hari ini/aktif yang belum arsip.
- `start()` membuat sesi ujian secara transaksional dengan `lockForUpdate`, validasi waktu, token, kehadiran, dan kelas target.
- `event()` mencatat event ke `session_events`.
- `remaining()` menghitung sisa waktu berdasarkan waktu selesai jadwal dan durasi sejak login.
- `save()` validasi soal/opsi, simpan jawaban ke Redis, lalu dispatch job `PersistAnswerSnapshot`.
- `flushOne()` persist satu jawaban dari Redis ke `jawaban_siswas` dan `audit_logs`.
- `flushAll()` persist semua jawaban pending untuk sebuah sesi.
- `submit()` menyelesaikan sesi dalam transaksi, flush semua jawaban, hitung nilai, dan update status.

Catatan penting:

- Jawaban PG diskor otomatis berdasarkan opsi benar.
- Jawaban ISIAN masuk status `pending_manual`.
- Redis key utama: `queue_jawaban:{sessionId}`.
- Cache dipakai untuk data soal, opsi valid, dan daftar soal session.

### `app/Services/ReportService.php`

Service laporan hasil.

Fungsi:

- `rows($scheduleId)` mengambil hasil semua kelas target.
- `rowsForClass($scheduleId, $classId)` mengambil hasil per kelas.
- `stats(Collection $rows)` menghitung total target, sudah masuk, belum masuk, rata-rata, nilai tertinggi, nilai terendah.

Service ini dipakai oleh halaman hasil aktif, arsip hasil, download hasil, dan laporan.

### `app/Services/IdCodec.php`

Wrapper Hashids.

Fungsi:

- Encode ID database menjadi hash aman untuk URL.
- Decode hash URL kembali ke ID internal.

Service ini mencegah ID auto-increment mentah terekspos langsung di route ujian.

### `app/Services/ImageService.php`

Service upload/proses gambar.

Fungsi:

- Menerima file gambar soal.
- Menyimpan gambar ke storage publik.
- Menghasilkan path/url yang disimpan di `bank_soals.gambar_url`.

Service ini dipakai oleh bank soal modern dan import soal.

## Job dan Queue

### `app/Jobs/PersistAnswerSnapshot.php`

Job queue untuk persist jawaban.

Fungsi:

- Menerima `sessionId` dan `questionId`.
- Memanggil `ExamService@flushOne`.
- Dijalankan di queue `answers`.

Job ini membuat request simpan jawaban cepat kembali ke frontend. Database write dilakukan asynchronous oleh worker Redis.

## Console Command

### `app/Console/Commands/RadarWorker.php`

Command custom `cbt:radar-worker`.

Fungsi:

- Menjalankan loop worker untuk data radar/live monitoring.
- Membaca status session dan menyimpan state ringan ke cache/Redis.
- Dipakai service production `cbt-radar.service`.

Command ini terpisah dari queue worker agar monitoring bisa berjalan terus tanpa menunggu request browser.

## Model

### `app/Models/User.php`

Model user Laravel.

Fungsi:

- Auth user.
- Field penting: `username`, `name`, `email`, `password`, `role`, `kelas_aktif_id`, `status_kehadiran`, `last_login_at`, field fingerprint.
- Memakai trait Spatie permission.
- Menjadi pusat relasi login siswa/staf.

### `app/Models/ExamSchedule.php`

Model untuk tabel `jadwal_ujians`.

Fungsi:

- Representasi jadwal ujian.
- Berelasi ke master ujian dan sesi.
- Dipakai saat query Eloquent diperlukan.

### `app/Models/ExamSession.php`

Model untuk tabel `sesi_ujians`.

Fungsi:

- Representasi sesi ujian per siswa.
- Menyimpan status aktif/selesai/force closed, waktu login, submit, nilai akhir, fingerprint, dan heartbeat.

### `app/Models/Question.php`

Model untuk tabel `bank_soals`.

Fungsi:

- Representasi soal.
- Menyimpan tipe soal, pertanyaan, gambar, bobot, dan relasi opsi.

### `app/Models/StudentAnswer.php`

Model untuk tabel `jawaban_siswas`.

Fungsi:

- Representasi jawaban siswa.
- Menyimpan opsi PG, jawaban isian, skor otomatis/manual, status scoring, dan timestamp client/server.

## Provider

### `app/Providers/AppServiceProvider.php`

Provider aplikasi Laravel.

Fungsi:

- Registrasi binding, macro, atau bootstrapping global.
- Tempat konfigurasi global aplikasi yang tidak cocok di controller/service.

## Database dan Migration

### `database/migrations/0001_01_01_000000_create_users_table.php`

Migration dasar Laravel:

- `users`.
- `password_reset_tokens`.
- `sessions`.

Project lalu menambahkan kolom CBT ke tabel `users` melalui migration domain.

### `database/migrations/0001_01_01_000001_create_cache_table.php`

Membuat tabel cache Laravel:

- `cache`.
- `cache_locks`.

Meski production memakai Redis, tabel ini tetap berguna jika cache driver diganti ke database.

### `database/migrations/0001_01_01_000002_create_jobs_table.php`

Membuat tabel queue database:

- `jobs`.
- `job_batches`.
- `failed_jobs`.

Production memakai Redis queue, tetapi tabel ini tetap berguna untuk fallback atau pencatatan failed job.

### `database/migrations/2026_06_01_000100_create_cbt_domain.php`

Migration domain utama CBT.

Tabel/kolom yang dibuat:

- Kolom CBT di `users`: `username`, `role`, `kelas_aktif_id`, `status_kehadiran`, `last_login_at`.
- `jurusans`.
- `rombels`.
- `kelas_aktifs`.
- `mata_pelajarans`.
- `paket_soals`.
- `bank_soals`.
- `opsi_jawabans`.
- `master_ujians`.
- `jadwal_ujians`.
- `jadwal_ujian_kelas`.
- `sesi_ujians`.
- `sesi_ujian_soals`.
- `jawaban_siswas`.
- `audit_logs`.
- `session_events`.

Ini adalah schema inti seluruh aplikasi.

### `database/migrations/2026_06_01_000110_create_permission_tables.php`

Membuat tabel Spatie Permission:

- `permissions`.
- `roles`.
- `model_has_permissions`.
- `model_has_roles`.
- `role_has_permissions`.

Tabel ini digunakan untuk permission seperti `manage-users`, `manage-questions`, `manage-schedules`, dan `monitor-exams`.

### `database/migrations/2026_06_01_000120_add_answer_server_timestamp.php`

Menambah field timestamp server untuk jawaban. Field ini penting untuk konflik sync: jawaban yang lebih baru tidak boleh ditimpa snapshot lama.

### `database/migrations/2026_06_02_020000_create_tingkats_table.php`

Membuat master `tingkats` agar tingkat kelas tidak hanya angka hardcoded. Dipakai halaman master sekolah.

### `database/migrations/2026_06_02_030000_create_exam_result_downloads_table.php`

Menambah:

- `jadwal_ujians.diarsipkan_at`.
- Tabel `hasil_ujian_unduhans`.

Fungsi:

- Memisahkan jadwal aktif dan arsip.
- Mencatat PDF hasil yang sudah diunduh per jadwal dan kelas.

### `database/migrations/2026_06_02_100000_add_performance_indexes.php`

Menambah index performa untuk query ujian, jadwal, session, dan jawaban.

Tujuan:

- Mempercepat dashboard siswa.
- Mempercepat validasi sesi aktif.
- Mempercepat laporan dan monitoring.

### `database/migrations/2026_06_03_012913_add_missing_indexes_for_1500_users.php`

Menambah index tambahan untuk beban besar sekitar 1500 user.

Area yang dioptimalkan:

- `sesi_ujian_soals`.
- `jawaban_siswas`.
- `audit_logs`.
- `session_events`.
- `sesi_ujians`.
- `users`.
- `opsi_jawabans`.

Migration memakai helper `indexExists()` agar aman saat index sudah ada.

### `database/migrations/2026_06_03_100000_partition_audit_logs.php`

Mengubah/menyiapkan `audit_logs` agar mendukung partitioning bulanan PostgreSQL.

Tujuan:

- Audit log dapat tumbuh besar tanpa memperlambat query utama.
- Data audit lebih mudah dipelihara per bulan.

### `database/migrations/2026_06_03_101000_add_device_fingerprint_to_sesi_ujians.php`

Menambah field fingerprint pada `sesi_ujians`.

Fungsi:

- Mengikat sesi ujian ke fingerprint perangkat.
- Membantu deteksi pergantian perangkat saat sesi berjalan.

### `database/migrations/2026_06_03_102000_add_fingerprint_to_users_table.php`

Menambah field fingerprint pada `users`.

Fungsi:

- Menyimpan fingerprint perangkat utama siswa.
- Menyimpan status lock/unlock device.
- Mendukung reset perangkat oleh admin/panitia.

### `database/migrations/2026_06_03_200000_add_columns_to_paket_soals_table.php`

Menambah kolom tambahan pada `paket_soals` untuk kebutuhan management bank soal saat ini.

Fungsi:

- Metadata paket lebih lengkap.
- UI bank soal bisa menampilkan status dan konteks paket dengan lebih baik.

### `database/migrations/2026_06_03_230000_add_mata_pelajaran_id_to_users_table.php`

Menambah relasi mata pelajaran pada user tertentu, terutama guru.

Fungsi:

- Guru dapat dikaitkan dengan mapel.
- Query paket/jadwal guru bisa difilter berdasarkan mapel.

## Seeder dan Factory

### `database/seeders/DatabaseSeeder.php`

Seeder demo/data awal.

Isi utama:

- Jurusan DKV.
- Rombel dan kelas aktif.
- User demo: superadmin, admin, guru, pengawas, siswa.
- Mapel Matematika.
- Paket soal demo.
- Beberapa soal PG dan opsi.
- Master ujian dan jadwal demo.

### `database/seeders/RolePermissionSeeder.php`

Seeder role dan permission.

Fungsi:

- Membuat role seperti SuperAdmin, Admin, Guru, Pengawas, Siswa.
- Membuat permission operasional.
- Memetakan permission ke role.

### `database/factories/UserFactory.php`

Factory user untuk test.

Fungsi:

- Membuat user palsu saat test.
- Mendukung state `unverified()`.

## Config Backend

### `config/app.php`

Konfigurasi aplikasi Laravel: nama app, environment, debug, timezone, locale, key, provider.

### `config/auth.php`

Konfigurasi guard auth.

Penting:

- Guard web untuk session browser.
- Guard API/JWT untuk `/api/v1`.

### `config/cache.php`

Konfigurasi cache store.

Production memakai Redis melalui `.env`:

- `CACHE_STORE=redis`.

### `config/database.php`

Konfigurasi koneksi database.

Penting:

- PostgreSQL utama.
- Redis client.
- Bisa menyimpan koneksi tambahan bila standby/read-only dipakai.

### `config/filesystems.php`

Konfigurasi storage Laravel.

Dipakai untuk:

- Gambar soal.
- File import sementara.
- Public storage.

### `config/logging.php`

Konfigurasi channel log Laravel.

Log production normal berada di `storage/logs`, tetapi file log runtime tidak boleh dicommit.

### `config/mail.php`

Konfigurasi email Laravel. Belum menjadi fitur utama CBT saat ini, tetapi disiapkan.

### `config/permission.php`

Konfigurasi Spatie Permission.

Penting untuk:

- Guard permission.
- Cache permission.
- Model role/permission.

### `config/queue.php`

Konfigurasi queue.

Production memakai:

- `QUEUE_CONNECTION=redis`.
- Queue penting: `answers`, `default`.

### `config/services.php`

Konfigurasi kredensial service eksternal. Secret tidak boleh ditulis langsung di file ini; gunakan `.env`.

### `config/session.php`

Konfigurasi session Laravel.

Production memakai:

- `SESSION_DRIVER=redis`.

Session web siswa/admin disimpan di Redis agar bisa dihancurkan lintas request saat device lock.

## Test

### `tests/TestCase.php`

Base class test Laravel.

### `tests/Feature/CompletionFlowTest.php`

Menguji alur penyelesaian ujian: mulai, simpan, submit, dan status selesai.

### `tests/Feature/DeviceFingerprintResetTest.php`

Menguji reset device fingerprint dan perilaku lock/unlock.

### `tests/Feature/ExamResultDownloadTest.php`

Menguji preview/download hasil dan pencatatan `hasil_ujian_unduhans`.

### `tests/Feature/ExamResultManagementTest.php`

Menguji hasil ujian aktif, terutama filter belum arsip dan load detail berdasarkan jadwal.

### `tests/Feature/ExampleTest.php`

Test contoh bawaan/placeholder.

### `tests/Feature/FinalFlowTest.php`

Menguji alur final ujian dan hasil akhir.

### `tests/Feature/QuestionBankManagementTest.php`

Menguji CRUD paket/soal, opsi, status ready, dan validasi bank soal.

### `tests/Feature/ScheduleManagementTest.php`

Menguji master ujian, jadwal, token, update, dan arsip.

### `tests/Feature/SchoolClassGenerationTest.php`

Menguji generate kelas dari tingkat/jurusan/rombel.

### `tests/Feature/SecurityRegressionTest.php`

Menguji regresi keamanan, misalnya akses tanpa izin, validasi data, atau endpoint sensitif.

### `tests/Feature/StudentDashboardScheduleTest.php`

Menguji jadwal yang muncul di dashboard siswa.

### `tests/Feature/StudentPasswordManagementTest.php`

Menguji reset/update password siswa dari halaman management.

### `tests/Unit/ExampleTest.php`

Unit test contoh bawaan/placeholder.

## Catatan File Runtime Backend

File/folder berikut bukan source backend dan tidak boleh dianggap bagian logic:

- `storage/logs/*.log`.
- `storage/framework/views/*.php`.
- `storage/framework/sessions/*`.
- `bootstrap/cache/packages.php`.
- `bootstrap/cache/services.php`.
- `.phpunit.result.cache`.

File tersebut boleh dibuat ulang oleh Laravel/PHPUnit. Jika muncul di git/status, itu artefak runtime.

## Catatan Keamanan Backend

- Jangan commit `.env`.
- Jangan commit secret dari `cred.txt`.
- Jangan expose `opsi_jawabans.is_benar` ke frontend siswa.
- Semua ID route ujian publik harus memakai `IdCodec`.
- Server time adalah sumber kebenaran durasi ujian.
- Device fingerprint siswa harus diverifikasi di web dan API.
- Gunakan permission/role sebelum endpoint management.
- Jawaban pending di Redis harus diflush saat submit agar nilai final tidak hilang.

