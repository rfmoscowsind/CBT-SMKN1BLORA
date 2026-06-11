# newgemini.md - Verifikasi Analisis Login, Cookie, Service Worker, dan Performa Ujian

Tanggal verifikasi: 2026-06-11

Dokumen ini merangkum hasil verifikasi kode aktual terhadap analisis Gemini tentang login, cookie/session, Service Worker, router fallback, monitoring PgBouncer, bulk submit jawaban, dan timer ujian.

Status umum: analisis Gemini sebagian valid, tetapi beberapa klaim perlu diluruskan berdasarkan kondisi kode saat ini. Setelah audit awal dibuat, fix prioritas sudah diimplementasikan pada 2026-06-11 dan dicatat di bagian status implementasi.

---

## Ringkasan

Prioritas audit awal yang sudah diimplementasikan:

```text
1. Hardening Axios untuk session/CSRF UX.
2. Evaluasi ulang Service Worker karena masih aktif di browser.
3. Pindahkan eksekusi psql PgBouncer dari request web ke job/scheduler.
4. Optimasi flushAll() agar tidak melakukan query per jawaban saat submit massal.
5. Ubah timer ujian menjadi berbasis deadline absolut.
6. Perbaiki mismatch redirect dashboard role Admin.
```

---

## Status Implementasi 2026-06-11

```text
DONE - resources/js/main.js:
Axios sekarang memakai withCredentials dan interceptor global untuk 401/419.

DONE - resources/js/router/index.js:
Route /vue/dashboard/admin ditambahkan sebagai alias ke dashboard panitia.

DONE - resources/views/app.blade.php dan resources/views/layouts/app.blade.php:
Registrasi Service Worker baru dihentikan, tetapi cleanup worker/cache lama tetap dijalankan.

DONE - routes/web.php:
/monitoring/stats tidak lagi menjalankan proc_open() psql di request web.
Route sekarang hanya membaca snapshot PgBouncer dari cache.

DONE - app/Console/Commands/RefreshPgbouncerSnapshot.php dan routes/console.php:
Command monitoring:pgbouncer-snapshot ditambahkan dan dijadwalkan tiap menit.

DONE - app/Services/ExamService.php:
flushAll() memakai bulk upsert PostgreSQL per sesi dengan guard server_updated_at newer-wins.

DONE - resources/js/pages/Exam/ExamInterface.vue:
Timer ujian memakai deadline absolut berbasis Date.now().
```

---

## FIXED - Axios Credentials

Lokasi:

```text
resources/js/main.js
```

Temuan awal:

```text
axios.defaults.headers.common.Accept = 'application/json';
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
```

Kode sekarang sudah memiliki:

```text
axios.defaults.withCredentials = true;
```

Catatan:

```text
Untuk request same-origin, browser umumnya tetap mengirim cookie.
Namun withCredentials tetap layak ditambahkan sebagai hardening agar perilaku Axios eksplisit,
terutama jika nanti ada perubahan subdomain, proxy, atau mode credential.
```

Status implementasi:

```text
axios.defaults.withCredentials = true sudah ditambahkan di main.js.
```

---

## FIXED - Axios Interceptor 401/419

Lokasi:

```text
resources/js/main.js
```

Temuan awal:

```text
main.js sudah punya showFatalError(), retryWithCountdown(), dan router.onError().
Namun saat audit awal belum ada axios.interceptors.response untuk menangani session expired/CSRF expired.
```

Risiko:

```text
Jika Laravel mengembalikan 401 Unauthorized atau 419 CSRF/session expired,
Vue bisa menampilkan error generic atau state tampak menggantung.
```

Status implementasi:

```text
Interceptor 401/419 sudah ditambahkan:
- membersihkan sessionStorage
- redirect ke /login?session_expired=true
```

Catatan:

```text
Jangan sembarang menghapus localStorage ujian/pending jawaban.
Jika perlu reset anchor device, pastikan tidak menghapus antrean jawaban lokal siswa.
```

---

## FIXED - Service Worker Tidak Diregister Ulang

Lokasi:

```text
resources/views/app.blade.php
resources/views/layouts/app.blade.php
public/sw.js
public/recover-login.html
```

Temuan awal:

```text
Template masih register /sw.js?v=8 saat audit awal.
Screenshot Network menunjukkan banyak request lewat (ServiceWorker).
```

Kondisi sw.js saat ini:

```text
- navigation request memakai network-first dan fallback ke /offline.html
- dynamic route seperti /dashboard, /ujian, /monitoring, /kelola tidak ikut cache statis
- asset statis tetap bisa di-cache
```

Kesimpulan:

```text
Service Worker versi sekarang sudah lebih aman daripada cache app-shell agresif.
Namun Service Worker lama di browser siswa masih bisa menyebabkan cache/state nyangkut
sampai user melakukan unregister dan clear site data.
```

Status implementasi:

```text
resources/views/app.blade.php dan resources/views/layouts/app.blade.php
sekarang hanya membersihkan registration/cache lama dan tidak register Service Worker baru.
```

---

## KURANG TEPAT - Cookie Secure/TrustProxies Bukan Tersangka Utama Saat Ini

Lokasi:

```text
.env
bootstrap/app.php
config/session.php
```

Verifikasi:

```text
.env sudah berisi SESSION_SECURE_COOKIE=true
bootstrap/app.php sudah berisi $middleware->trustProxies(at: '*')
config/session.php membaca SESSION_DOMAIN dan SESSION_SECURE_COOKIE dari env
cookie response sebelumnya terlihat sudah secure; httponly; samesite=lax
```

Kesimpulan:

```text
Klaim bahwa cookie pasti ditolak karena Laravel tidak tahu HTTPS kurang cocok
dengan kondisi server saat ini.
```

Catatan:

```text
SESSION_DOMAIN=".madnnet.my.id" valid untuk subdomain cbt.madnnet.my.id.
Jika hanya satu subdomain dipakai, SESSION_DOMAIN=null juga bisa dipertimbangkan,
tetapi itu bukan bukti akar masalah saat ini.
```

---

## KURANG TEPAT - Redirect Siswa Tidak Salah Kapital

Lokasi:

```text
app/Http/Controllers/WebController.php
resources/js/router/index.js
```

Verifikasi:

```text
WebController untuk role Siswa redirect ke /vue/dashboard/siswa.
Vue router juga punya route /vue/dashboard/siswa.
```

Kesimpulan:

```text
Klaim bahwa Laravel mengirim /vue/dashboard/Siswa huruf kapital tidak sesuai kode saat ini.
Untuk role Siswa, route sudah cocok.
```

---

## FIXED - Redirect Dashboard Role Admin Mismatch

Lokasi:

```text
app/Http/Controllers/WebController.php
resources/js/router/index.js
```

Temuan awal:

```text
WebController redirect role Admin ke /vue/dashboard/admin.
Saat audit awal Vue router tidak memiliki route /vue/dashboard/admin.
Vue router hanya memiliki:
- /vue/dashboard/superadmin
- /vue/dashboard/panitia
- /vue/dashboard/guru
- /vue/dashboard/pengawas
- /vue/dashboard/siswa
```

Dampak:

```text
User role Admin bisa masuk route fallback NotFound,
lalu fallback memaksa browser reload ke /dashboard.
Ini dapat menyebabkan loop redirect atau pengalaman login terlihat mental.
```

Status implementasi:

```text
Route /vue/dashboard/admin sudah ditambahkan sebagai alias ke PanitiaDashboard.
```

---

## FIXED - proc_open psql PgBouncer Dipindah dari Route Web

Lokasi:

```text
routes/web.php
Route GET /monitoring/stats
```

Temuan awal:

```text
Saat audit awal route /monitoring/stats memiliki closure $pgbouncerRows.
Closure tersebut menjalankan proc_open() untuk command psql:
- SHOW VERSION
- SHOW POOLS
- SHOW STATS
```

Risiko:

```text
Walaupun hasil PgBouncer sudah di-cache 10 detik,
cache miss tetap dapat membuka proses OS dari request HTTP.
Dalam Octane/RoadRunner, proses eksternal yang lambat bisa menahan worker.
Jika banyak pengawas membuka dashboard saat cache expired,
beberapa proses psql bisa berjalan paralel.
```

Status implementasi:

```text
Pengambilan PgBouncer dipindah ke command monitoring:pgbouncer-snapshot.
Command dijadwalkan tiap menit lewat Laravel Scheduler.
Route /monitoring/stats sekarang hanya membaca snapshot dari cache.
```

---

## FIXED - flushAll() Bulk Upsert

Lokasi:

```text
app/Services/ExamService.php
ExamService::flushAll()
ExamService::persistAnswerIfNewer()
```

Temuan awal:

```text
Saat audit awal flushAll() sudah batch-fetch bank_soals dan opsi benar.
Namun persist jawaban masih loop:
foreach ($rowsToPersist as ...) {
    $this->persistAnswerIfNewer(...);
}
```

Pada PostgreSQL, persistAnswerIfNewer() menjalankan raw query:

```text
INSERT INTO jawaban_siswas (...) VALUES (...)
ON CONFLICT (sesi_ujian_id, bank_soal_id) DO UPDATE ...
RETURNING id
```

Risiko:

```text
Saat auto-submit massal, jumlah query meningkat mengikuti:
jumlah siswa x jumlah pending jawaban.

Contoh:
1500 siswa x 50 jawaban = 75000 query upsert individual.
```

Status implementasi:

```text
flushAll() sekarang memakai bulk upsert PostgreSQL per sesi.
Guard "newer answer wins" berdasarkan server_updated_at tetap dipertahankan.
```

Catatan implementasi:

```text
Laravel upsert() native tidak langsung mendukung WHERE jawaban_siswas.server_updated_at
IS NULL OR EXCLUDED.server_updated_at >= jawaban_siswas.server_updated_at.
Untuk menjaga semantik existing, kemungkinan perlu raw PostgreSQL multi-values
INSERT ... ON CONFLICT ... DO UPDATE ... WHERE ...
```

---

## FIXED - Timer Frontend Deadline Absolut

Lokasi:

```text
resources/js/pages/Exam/ExamInterface.vue
ExamInterface.vue::startTimer()
```

Temuan awal:

```text
Saat audit awal timer masih memakai:
sisaWaktuDetik.value--;
dalam setInterval 1000 ms.
```

Risiko:

```text
Saat tab browser background/minimized,
Chrome bisa throttle setInterval.
Timer visual siswa dapat berjalan lebih lambat dari waktu server.
```

Mitigasi yang sudah ada:

```text
Frontend beberapa kali menyelaraskan ulang sisa waktu dari response server.
Namun tampilan timer tetap bisa ngaret di antara request sync tersebut.
```

Status implementasi:

```text
Timer sekarang berbasis deadline absolut:
- saat menerima sisa_detik dari server, simpan deadline = Date.now() + sisa_detik * 1000
- tiap interval, hitung sisa dari selisih deadline dan Date.now()
- saat server mengirim sisa_detik baru, update deadline dengan toleransi drift
```

---

## Status Rekomendasi Prioritas

```text
P0 - DONE:
1. Pindahkan proc_open psql PgBouncer dari route web ke scheduler + cache snapshot.
2. Ubah timer ujian menjadi deadline absolut.
3. Perbaiki redirect role Admin yang mismatch.

P1 - DONE:
1. Tambahkan axios.withCredentials dan interceptor 401/419.
2. Ubah flushAll() ke bulk upsert PostgreSQL dengan semantik newer-wins.

P2 - PARTIAL:
1. Registrasi Service Worker baru dihentikan dan cleanup lama tetap berjalan.
2. Snapshot PgBouncer sudah ditambahkan; monitoring Redis, worker queue, dan Octane tetap memakai jalur existing.
```

---

## Lokasi Kode yang Diverifikasi

```text
resources/js/main.js
resources/js/router/index.js
app/Http/Controllers/WebController.php
routes/web.php
app/Services/ExamService.php
resources/js/pages/Exam/ExamInterface.vue
```
