# Known Issues Audit - CBT

Tanggal audit: 2026-06-03  
Lingkup: audit kode statis, route, konfigurasi, dependency audit, dan cek runtime produksi read-only. Tidak dilakukan load test dan tidak ada perubahan kode aplikasi pada audit ini.

## Snapshot Produksi

- Stack produksi: Laravel 13.12.0, PHP 8.3.31, environment `production`, debug `OFF`.
- Cache produksi: config, route, event, dan view sudah `CACHED`.
- Driver runtime: database `pgsql`, cache `redis`, queue `redis`, session `redis`.
- Redis dipakai dari host jaringan internal `192.168.16.121:6379`; ping Laravel sukses.
- Queue worker berjalan untuk `answers,default`; queue `answers` dan `default` kosong saat dicek, `failed_jobs=0`.
- Proses aktif: `nginx`, `php8.3-fpm`, `php8.2-fpm`, `queue:work`, `schedule:work`, dan 2 proses `cbt:radar-worker`.
- `supervisor` lokal tidak aktif; worker tampak berjalan sebagai proses orphan dengan `PPID=1`, bukan service yang jelas dari `systemctl`.
- Data saat audit kecil: `users=6`, `users_siswa=2`, `sesi_ujians=7`, `jawaban_siswas=23`, `audit_logs=179`, `session_events=35`, `sesi_aktif=0`, `sesi_terkunci=1`, `users_locked=0`.
- Timing publik dari dalam server: `/login` sekitar 7-8 ms; `/`, `/dashboard`, dan `/vue/management/fingerprints` redirect auth sekitar 6-7 ms.
- Timing dari mesin audit lewat Cloudflare: `/login` warm sekitar 627-629 ms; redirect auth sekitar 520-612 ms.
- Dependency audit: `composer audit` bersih, `npm audit --omit=dev` bersih.
- Log produksi terbaru yang terlihat berisi error lama `No application encryption key has been specified` pada 2026-06-03 03:42-03:45 UTC; setelah itu `/login` sudah 200 dan `php artisan about` normal.

## Temuan Kritis dan Tinggi

### K1 - [SELESAI 2026-06-03] Device lock mematikan sesi Redis yang tercatat

Status: selesai untuk jalur web session. Produksi memakai `SESSION_DRIVER=redis`, jadi fix menambahkan registry session-id per user dan invalidasi lewat session handler Laravel, bukan hanya hapus tabel `sessions`.

Perbaikan:
- `WebController` sekarang mencatat session-id user setelah login dan saat dashboard dibuka.
- Saat device mismatch, akun terkunci, atau siswa terdeteksi sudah locked, semua session-id user yang tercatat dihancurkan lewat `$request->session()->getHandler()->destroy($sessionId)`.
- Manual lock dari panel SuperAdmin di `ManageController::lockDeviceFingerprint()` juga menghapus session web siswa yang tercatat.
- Cleanup tabel `sessions` tetap dipertahankan sebagai fallback untuk driver database.

Catatan:
- Session Redis lama yang sudah ada sebelum patch baru bisa dihapus global setelah user tersebut login ulang atau membuka dashboard sehingga session-id-nya masuk registry.
- K1 ini belum mencabut JWT/API token; itu tetap dicatat terpisah di K2.

### K2 - [SELESAI 2026-06-03] API JWT mengikuti device fingerprint

Status: selesai untuk endpoint siswa di `ApiController`.

Perbaikan:
- Login API siswa wajib menyertakan `device_fp` atau header `X-Device-Fingerprint`.
- Login API siswa ditolak dengan HTTP 423 jika akun sudah locked atau fingerprint berbeda.
- Request API siswa seperti `me`, `jadwal`, `masuk`, `soal`, `jawaban`, `sync`, `ping`, dan `submit` memverifikasi fingerprint setiap request.
- Jika fingerprint mismatch, user ditandai `is_device_locked=true`, sesi ujian aktif menjadi `terkunci`, current JWT dilogout, dan request ditolak 423.
- JWT lama tidak perlu dipercaya sebagai revoke source karena setiap request siswa sekarang dicek ulang ke database dan ditolak saat akun locked atau fingerprint tidak cocok.

Catatan:
- API staf/admin tetap berbasis permission/role dan tidak diwajibkan device fingerprint.
- Jika ada mobile client resmi, client tersebut wajib mengirim fingerprint yang sama dengan web flow.

### K3 - [SELESAI 2026-06-03] Endpoint monitoring web memakai role/permission

Status: selesai.

Perbaikan:
- `/monitoring/stats` dan `/monitoring/sessions` sekarang menolak user tanpa permission `monitor-exams` dan tanpa role `SuperAdmin`, `Admin`, atau `Pengawas`.
- Route cache produksi sudah dibuat ulang setelah perubahan.

### K4 - [SELESAI 2026-06-03] Kredensial operasional plaintext di root sudah discrub

Status: selesai untuk file root `.py` dan `.md`.

Perbaikan:
- Literal password yang tersimpan di script dan dokumen root sudah diganti placeholder `<SET_CBT_SECRET_ENV>`.
- Verifikasi `rg` pada file root `.py`/`.md` tidak lagi menemukan literal password lama.

Catatan:
- Script ad-hoc yang masih dibutuhkan harus dihidupkan ulang dengan secret dari environment/secret manager, bukan ditulis kembali ke file.
- Password yang pernah tersimpan tetap harus dirotasi di luar aplikasi.
- File runtime/generated seperti `.env` atau cache konfigurasi produksi tidak disentuh dalam fix ini agar aplikasi tidak putus koneksi.

### K5 - Start ujian membuat/mengembalikan sesi sebelum validasi device selesai

`WebController::start()` memanggil `$this->exams->start(...)` dulu di `app/Http/Controllers/WebController.php:309`, baru mengecek `is_device_locked` dan fingerprint di `:313-320`. Jika abort terjadi, `catch` di `:331-336` mengubahnya menjadi HTTP 422 untuk JSON, bukan 423.

Dampak:
- Device bermasalah bisa membuat atau mendapatkan sesi lebih dulu sebelum ditolak.
- Frontend yang menunggu status 423 tidak selalu terpanggil pada flow start.
- `ExamService::start()` juga mengembalikan existing session tanpa memeriksa status `terkunci` (`app/Services/ExamService.php:71-78`).

Rekomendasi:
- Pindahkan validasi device sebelum `ExamService::start()`.
- Jangan tangkap `HttpException` 423 menjadi 422.
- Jika existing session status `terkunci`, jangan return sukses ke frontend.

### K6 - Partisi `audit_logs` akan habis pada 2027-01-01

Migration `database/migrations/2026_06_03_100000_partition_audit_logs.php:45-53` membuat partisi fixed sampai range akhir `2027-01-01`.

Dampak:
- Setelah 2027-01-01, insert audit log bisa gagal jika belum ada partisi baru.
- Karena answer save menulis `audit_logs`, ini bisa mengganggu ujian.

Rekomendasi:
- Buat job/migration rutin untuk menambah partisi bulanan sebelum periode baru.
- Tambahkan default partition atau alert sebelum partisi habis.

## Performa dan Kecepatan

### P1 - Endpoint soal punya kerja dobel dan debug log produksi

`WebController::soal()` memanggil `$this->owned($hash, $r)` dua kali (`app/Http/Controllers/WebController.php:485` dan `:490`) dan mengecek status aktif dua kali. Di tengahnya ada `Log::warning("WebController::soal debug", ...)` pada `:491-499`.

Dampak:
- Request soal adalah jalur paling sering dipanggil saat ujian.
- Pemanggilan `owned()` dobel berarti decode/query/fingerprint check dobel.
- Log warning per soal bisa membebani disk/log pipeline dan menyimpan metadata session/user.

Rekomendasi:
- Hapus debug log produksi.
- Pakai satu hasil `$s = $this->owned(...)`.

### P2 - Laporan PDF/XLSX masih full in-memory

`ReportService::rowsForClass()` mengambil semua row ke Collection (`app/Services/ReportService.php:14-48`). Export di `ManageController::report()` dan `AdminApiController::report()` juga membangun seluruh data sebelum dikirim.

Dampak:
- Untuk banyak siswa/jadwal, memory dan response time akan naik.

Rekomendasi:
- Pakai pagination untuk preview.
- Untuk XLSX gunakan streaming/chunk.
- Untuk PDF batasi per kelas atau generate async.

### P3 - Legacy `ManageController::index()` memuat hampir semua data sekaligus

`app/Http/Controllers/ManageController.php:85-104` mengambil users, mapel, jurusan, rombel, kelas, paket, soal, master, jadwal, sesi, roles, permissions, dan pending essay dalam satu render.

Dampak:
- Aman untuk data kecil, tetapi akan berat saat bank soal dan user besar.
- Route legacy `/kelola` masih ada dan bisa dipakai.

Rekomendasi:
- Arahkan admin ke endpoint Vue yang sudah lebih terpecah.
- Pagination/chunk untuk users, questions, sessions, dan pending essay.

### P4 - Import Excel/CSV dibaca penuh ke memori

Beberapa import memakai `IOFactory::load(...)->getActiveSheet()->toArray()`, misalnya:
- `ManageController::bulk()` di `app/Http/Controllers/ManageController.php:181-193`
- `ManageController::importQuestions()` di `:235-250`
- `QuestionBankManagementController::import()` di `app/Http/Controllers/QuestionBankManagementController.php:183-230`
- `SchoolMasterController::importStudents()` di `app/Http/Controllers/SchoolMasterController.php:153-191`

Dampak:
- File besar dapat membuat memory spike.
- Beberapa flow melakukan query per baris dan role sync per user.

Rekomendasi:
- Tambahkan batas `max` file.
- Pakai chunk reader/queued import.
- Batch insert/update jika memungkinkan.

### P5 - Radar worker berjalan 2 proses dan loop tiap 2 detik

`RadarWorker` melakukan loop `while (true)` dan `sleep(2)` (`app/Console/Commands/RadarWorker.php:17-115`). Runtime menunjukkan 2 proses `cbt:radar-worker` berjalan bersamaan.

Dampak:
- Query dan write Redis dobel setiap 2 detik.
- Dengan banyak siswa login hari itu, query harian `last_login_at >= startOfDay` makin besar.
- Karena worker tidak jelas dikelola supervisor/systemd, risiko duplikasi atau mati diam-diam lebih besar.

Rekomendasi:
- Jalankan satu instance saja dengan lock (`Cache::lock`) atau service manager.
- Jadikan systemd/supervisor unit yang jelas.
- Pertimbangkan interval adaptif atau filter hanya jadwal aktif.

### P6 - Beberapa endpoint masih N+1 atau query per item

Contoh:
- `QuestionBankManagementController::ready()` menghitung opsi dan kunci per soal (`app/Http/Controllers/QuestionBankManagementController.php:170-176`).
- `AdminApiController::previewPackage()` memuat opsi per soal dalam map.
- Import soal menghitung `max('urutan')` per baris.

Rekomendasi:
- Batch count/groupBy opsi per paket.
- Hitung urutan awal sekali, lalu increment in-memory saat import.

### P7 - Permission check masih ada yang memaksa `fresh()`

Contoh:
- `StudentManagementController.php:15-20`
- `SchoolMasterController.php:17-22`
- `AdminApiController` API permission helper memakai `fresh()->getAllPermissions()`.

Dampak:
- Menambah query user/permission tiap request.

Rekomendasi:
- Gunakan `Auth::user()?->can(...)` agar memanfaatkan cache Spatie seperti beberapa controller lain.

### P8 - Asset eksternal mempengaruhi waktu render

Logo dan CDN dipakai dari domain eksternal/CDN:
- Logo `smkn1blora.sch.id` di login, dashboard, layout, dan komponen Vue.
- Bootstrap/FontAwesome/Google Fonts dari CDN di layout lama.

Dampak:
- Jika internet/CDN lambat atau diblokir jaringan ujian, UI bisa lambat/ikon hilang.
- Browser siswa juga melakukan request ke pihak eksternal.

Rekomendasi:
- Simpan logo lokal di `public/`.
- Bundle CSS/icon yang diperlukan lewat Vite atau host lokal.

## Device Fingerprint dan Device Lock

### D1 - Server-issued anchor belum benar-benar menjadi sumber utama

Login Blade membuat localStorage anchor sendiri sebelum submit (`resources/views/auth/login.blade.php:63-72`). Setelah login, server juga mengirim query `device_anchor`, tetapi `fingerprint.js` akan memakai localStorage yang sudah ada dan mengabaikan server anchor (`resources/js/utils/fingerprint.js:91-100`).

Dampak:
- Desain "server generate UUID lalu simpan di localStorage" belum murni terjadi.
- Saat ini anchor efektifnya browser-generated pada login pertama.

Rekomendasi:
- Jika ingin server-issued anchor, buat endpoint pre-login atau ubah login flow agar server anchor dipakai sebelum fingerprint dikunci.
- Lebih kuat lagi: pakai signed device token/HMAC, bukan UUID polos.

### D2 - Deteksi cloud phone masih informatif, belum enforcement

`fingerprint.js` menghitung `riskScore`, `riskFlags`, dan `cloudPhoneSuspected` (`resources/js/utils/fingerprint.js:53-58`), tetapi backend tidak memakai nilai itu untuk block/review.

Dampak:
- Cloud phone bisa terdeteksi di detail admin, tetapi tidak otomatis dicegah.

Rekomendasi:
- Tentukan policy: block otomatis untuk risk tinggi, atau masuk daftar review SuperAdmin.
- Simpan event khusus `cloud_phone_suspected`.

### D3 - Fingerprint client-side dapat dipalsukan

Hash dibuat di browser dari data yang juga berasal dari browser. LocalStorage anchor juga bisa disalin dari device A ke device B.

Dampak:
- Sistem ini bagus sebagai deterrent dan audit, tetapi bukan identitas hardware yang tidak bisa ditembus.

Rekomendasi:
- Gabungkan dengan signed server token, IP/session anomaly, dan monitoring real-time.
- Jangan menganggap fingerprint sebagai bukti tunggal.

### D4 - Clear storage/incognito akan dianggap device baru

Karena anchor tersimpan di localStorage, hapus cache/site data, browser berbeda, mode incognito, atau WebView berbeda dapat menghasilkan anchor baru.

Dampak:
- Siswa sah bisa terkunci dan butuh reset admin.

Rekomendasi:
- Buat SOP admin: reset device hanya setelah verifikasi siswa/ruang.
- Tampilkan alasan lock yang jelas di panel.

### D5 - Unlock tidak membersihkan session `terkunci`

`lockStudentDevice()` mengubah semua sesi aktif user menjadi `status='terkunci'` (`app/Http/Controllers/WebController.php:50-53`). `unlockDeviceFingerprint()` hanya mencari session aktif (`app/Http/Controllers/ManageController.php:406-411`), jadi session yang sudah `terkunci` tidak diubah.

Runtime saat audit: `sesi_terkunci=1`, tetapi `users_locked=0`.

Dampak:
- Panel bisa menunjukkan angka nol jika menghitung dari user lock, padahal ada sesi berstatus `terkunci`.
- Siswa yang di-unlock bisa tetap punya session ujian stuck.

Rekomendasi:
- Saat unlock, tentukan aksi eksplisit: hapus session terkunci, reset ke aktif, atau tutup sebagai reset.
- Samakan metrik dashboard antara `users.is_device_locked` dan `sesi_ujians.status`.

### D6 - Kolom lock di session belum konsisten dipakai

Migration menambah `is_device_locked` di `sesi_ujians`, tetapi flow utama memakai `users.is_device_locked` dan `sesi_ujians.status='terkunci'`.

Dampak:
- Ada potensi kebingungan data karena tiga representasi lock: user flag, session status, dan session flag.

Rekomendasi:
- Pilih satu sumber kebenaran, lalu turunkan status lain dari situ.

## Security Hardening Lain

### S1 - `/logout` menerima semua method

`routes/web.php:16` memakai `Route::any('/logout', ...)`.

Dampak:
- Logout bisa dipicu via GET dari link/image/cross-site. Ini bukan pencurian data, tetapi bisa mengganggu siswa saat ujian.

Rekomendasi:
- Batasi logout ke `POST` dengan CSRF.
- Untuk link keluar, pakai form POST.

### S2 - Password default siswa masih mudah ditebak

Beberapa import memakai default `siswa123` saat password kosong, misalnya `ManageController.php:188` dan `SchoolMasterController.php:181`.

Dampak:
- Jika siswa belum mengganti password, akun mudah ditebak.

Rekomendasi:
- Generate password random per siswa atau paksa ganti password saat login pertama.
- Export password awal ke file admin sekali saja.

### S3 - Header security belum lengkap

Nginx sudah mengirim `X-Frame-Options: SAMEORIGIN` dan `X-Content-Type-Options: nosniff`. Header seperti `Strict-Transport-Security` dan `Content-Security-Policy` belum terlihat di response `/login`.

Dampak:
- Hardening browser belum maksimal.
- Karena ada CDN/script eksternal, CSP bisa membantu membatasi supply-chain risk.

Rekomendasi:
- Tambahkan HSTS di layer HTTPS/Cloudflare.
- Tambahkan CSP bertahap, mulai dari `report-only`.

### S4 - Upload gambar belum membatasi dimensi pixel

Validasi gambar membatasi ukuran file 2048 KB, tetapi `ImageService::webp()` memakai `imagecreatefromstring(file_get_contents(...))` (`app/Services/ImageService.php:4`) tanpa batas dimensi.

Dampak:
- Gambar terkompresi dengan dimensi sangat besar dapat menyebabkan memory spike.

Rekomendasi:
- Validasi `dimensions:max_width,max_height`.
- Gunakan library image yang bisa resize aman sebelum decode penuh jika memungkinkan.

### S5 - Banyak script diagnostik/ad-hoc di root repo

Root project berisi banyak script `check_*.py`, `diag*.py`, `patch_*.py`, `fix_*.py`, `test_*.php`, `plink.exe`, dan catatan sementara.

Dampak:
- Tidak terlihat publik selama Nginx root benar ke `/public`, tetapi tetap menaikkan risiko operasional dan salah deploy.

Rekomendasi:
- Pindahkan ke folder internal yang tidak ikut deploy, atau hapus setelah dipakai.
- Pastikan backup/artifact publik tidak membawa file ini.

## Hal yang Sudah Bagus

- Production debug sudah OFF.
- Config, routes, events, dan views sudah cached.
- Queue jawaban memakai Redis dan worker `answers,default` sedang berjalan.
- `failed_jobs=0` dan queue kosong saat audit.
- Web ujian sudah mengambil soal satu per satu, bukan dump seluruh soal.
- `is_benar` tidak dikirim pada endpoint soal web.
- Throttle sudah ada pada login web/API dan endpoint ujian utama.
- Cookie session terlihat `secure`, `httponly`, dan `samesite=lax`.
- Nginx sudah gzip dan cache immutable untuk `/build/assets/`.
- Migrations performance index sudah ran.
- Dependency audit composer dan npm bersih.

## Prioritas Tindakan

1. [SELESAI untuk web session] Perbaiki invalidasi session Redis saat device lock; cabut JWT masih masuk K2.
2. [SELESAI untuk API siswa] Tambahkan device-lock middleware/check untuk API siswa.
3. [SELESAI] Lindungi `/monitoring/stats` dan `/monitoring/sessions` dengan role/permission.
4. Pindahkan validasi device sebelum `ExamService::start()` dan pertahankan HTTP 423.
5. Hapus debug log dan pemanggilan `owned()` dobel di endpoint soal.
6. [SELESAI untuk file root] Bersihkan kredensial plaintext di script root; rotasi password tetap wajib dilakukan di luar aplikasi.
7. Rapikan state unlock: user lock, session `terkunci`, dan counter dashboard harus konsisten.
8. Jadikan queue/radar/scheduler service yang supervised dan pastikan hanya satu radar worker aktif.
9. Siapkan maintenance partisi `audit_logs` sebelum 2027-01-01.
10. Tambahkan pagination/streaming untuk laporan, import, dan legacy management.
11. Host asset penting secara lokal dan tambahkan CSP/HSTS bertahap.
