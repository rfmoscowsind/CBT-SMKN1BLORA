# Dokumentasi Frontend CBT SMKN 1 Blora

Tanggal pembaruan: 2026-06-06  
Status: ditulis ulang berdasarkan struktur project yang ada di repository saat ini.

Dokumen ini menjelaskan frontend: Vue SPA, Blade view, CSS, PWA asset, public file, dan UI statis lama. Backend Laravel dijelaskan di `back.md`, sedangkan requirement server/deployment dijelaskan di `req.md`.

## Ringkasan Frontend Aktual

Frontend utama memakai Vue 3 dengan Vite. Aplikasi Vue dilayani oleh Laravel melalui view `resources/views/app.blade.php` dan route fallback `/vue/{any}`. Untuk halaman login dan beberapa halaman ujian/report, project masih memakai Blade.

Ada tiga lapisan UI:

1. Vue SPA modern di `resources/js`.
2. Blade Laravel di `resources/views`.
3. File HTML statis lama/prototipe di `ui` dan beberapa file recovery/offline di `public`.

Stack frontend:

- Vue 3 Composition API.
- Vue Router.
- Pinia terpasang, meskipun state saat ini mayoritas masih lokal per komponen.
- Axios untuk HTTP.
- Bootstrap 5 untuk layout dan komponen.
- Tailwind CSS v4 melalui `@tailwindcss/vite`.
- SweetAlert2 untuk dialog, toast, dan konfirmasi.
- Font Awesome class digunakan di template.
- Service worker sederhana untuk offline fallback dan caching asset.

## Entry Point Frontend

### `resources/js/main.js`

Entry point Vue SPA modern.

Fungsi:

- Membuat Vue app dari `App.vue`.
- Mendaftarkan Pinia.
- Mendaftarkan Vue Router.
- Mengatur axios default header:
  - `Accept: application/json`.
  - `X-Requested-With: XMLHttpRequest`.
  - `X-CSRF-TOKEN` dari meta tag.
- Import Bootstrap CSS.
- Menangani error global Vue.
- Menangani error dynamic import/chunk Vite.

Bagian penting:

- Jika chunk gagal dimuat setelah build baru, router error handler melakukan reload satu kali dengan query `asset_refresh`.
- Jika tetap error, overlay fatal error ditampilkan dengan tombol muat ulang dan link `/recover-login.html`.

### `resources/js/app.js`

Entry tambahan dari Vite config. Saat ini berisi placeholder/minimal. File tetap masuk build karena terdaftar di `vite.config.js`.

### `resources/js/App.vue`

Root component Vue.

Fungsi:

- Menampilkan `<router-view>`.
- Menjadi container semua halaman Vue.
- Menyimpan style global ringan untuk body.

### `resources/css/app.css`

Entry CSS utama.

Fungsi:

- Import Tailwind CSS dengan `@import 'tailwindcss';`.
- Menjadi input Vite untuk stylesheet global.

## Router Vue

### `resources/js/router/index.js`

File ini mendefinisikan semua route SPA.

Fungsi:

- Membuat router dengan `createWebHistory()`.
- Lazy-load semua halaman dashboard, management, ujian, dan monitoring.
- Menyediakan alias URL lama `/kelola/*`.
- Fallback unknown route melakukan hard redirect ke `/dashboard` agar Laravel bisa validasi role dan session.

Route dashboard:

- `/vue/dashboard/superadmin` -> `SuperAdminDashboard.vue`.
- `/vue/dashboard/panitia` -> `PanitiaDashboard.vue`.
- `/vue/dashboard/guru` -> `GuruDashboard.vue`.
- `/vue/dashboard/pengawas` -> `PengawasDashboard.vue`.
- `/vue/dashboard/siswa` -> `SiswaDashboard.vue`.

Route management:

- `/vue/management/staff` -> `StaffManagement.vue`.
- `/vue/management/siswa` -> `StudentManagement.vue`.
- `/vue/management/master` -> `MasterDataManagement.vue`.
- `/vue/management/soal` -> `QuestionBank.vue`.
- `/vue/management/jadwal` -> `ExamSchedule.vue`.
- `/vue/management/hasil` -> `ExamResults.vue`.
- `/vue/management/arsip-hasil` -> `ExamResultArchives.vue`.
- `/vue/management/download-hasil` -> `ExamResultDownloads.vue`.
- `/vue/management/fingerprints` -> `DeviceFingerprints.vue`.

Route ujian:

- `/vue/ujian/token` -> `ExamToken.vue`.
- `/vue/ujian` -> `ExamInterface.vue`.
- `/vue/ujian/selesai` -> `ExamFinish.vue`.

Route monitoring:

- `/vue/monitoring/radar` -> `LiveRadar.vue`.

Alias lama:

- `/kelola`.
- `/kelola/master`.
- `/kelola/users`.
- `/kelola/questions`.
- `/kelola/schedules`.

## Komponen Global

### `resources/js/components/AdminSidebar.vue`

Sidebar admin/management.

Fungsi:

- Menampilkan navigasi management.
- Mengambil user aktif dari `/auth/user`.
- Menentukan menu aktif dari `useRoute()`.
- Menjadi layout pendamping hampir semua halaman management.

Menu yang terkait:

- Staff.
- Siswa.
- Master data.
- Bank soal.
- Jadwal.
- Hasil.
- Download hasil.
- Arsip hasil.
- Fingerprint/perangkat.

## Utility Frontend

### `resources/js/utils/fingerprint.js`

Utility device fingerprint.

Fungsi:

- Menghasilkan fingerprint browser/device.
- Menggunakan sinyal seperti user agent, platform, timezone, resolusi layar, dan data environment.
- Mengelola local storage anchor agar perangkat yang sama lebih stabil dikenali.
- Dipakai saat login/mulai ujian dan request siswa tertentu.

File ini penting untuk device lock. Jika diubah sembarangan, siswa bisa terkunci massal atau device lock menjadi terlalu longgar.

## Halaman Dashboard

### `resources/js/pages/Dashboard/SuperAdminDashboard.vue`

Dashboard SuperAdmin.

Fungsi:

- Menampilkan ringkasan status sistem.
- Mengambil user dari `/auth/user`.
- Mengambil statistik dari `/monitoring/stats`.
- Menampilkan status server/database/monitoring.
- Menjalankan refresh berkala.
- Menjadi pusat navigasi cepat untuk pengelolaan.

Karakter UI:

- Dashboard operasional dengan kartu ringkasan.
- Fokus pada health, jumlah peserta, dan kondisi server.

### `resources/js/pages/Dashboard/PanitiaDashboard.vue`

Dashboard Panitia.

Fungsi:

- Mengambil user dari `/auth/user`.
- Mengambil statistik dari `/monitoring/stats`.
- Mengambil daftar session dari `/monitoring/sessions`.
- Menampilkan peserta aktif/offline.
- Menyediakan aksi reset sesi siswa lewat `/kelola/sesi/{id}/reset`.

Halaman ini dipakai untuk kontrol pelaksanaan ujian.

### `resources/js/pages/Dashboard/GuruDashboard.vue`

Dashboard Guru.

Fungsi:

- Mengambil user aktif.
- Mengambil paket soal guru dari `/api/v1/guru/paket-soal`.
- Mengambil jadwal terkait guru dari `/kelola/guru/jadwal-terkait`.
- Mengambil isian pending dari `/api/v1/grading/isian`.
- Menjadi pintu masuk guru untuk bank soal dan penilaian.

### `resources/js/pages/Dashboard/PengawasDashboard.vue`

Dashboard Pengawas.

Fungsi:

- Mengambil data sesi dari `/monitoring/sessions`.
- Menampilkan peserta yang sedang ujian.
- Menyediakan reset sesi siswa.
- Cocok untuk pengawas ruang yang fokus pada status peserta.

### `resources/js/pages/Dashboard/SiswaDashboard.vue`

Dashboard siswa.

Fungsi:

- Mengambil data dashboard dari `/dashboard` dalam mode JSON.
- Menampilkan jadwal ujian siswa.
- Membuat fingerprint perangkat dengan `generateDeviceFingerprint()`.
- Memulai ujian melalui `POST /ujian/{hash}/mulai`.
- Jika jadwal memakai token, mengarahkan ke `/vue/ujian/token`.
- Jika tidak, langsung mengarahkan ke `/vue/ujian?session=...`.

Halaman ini adalah pintu masuk siswa ke ujian.

## Halaman Ujian

### `resources/js/pages/Exam/ExamToken.vue`

Halaman input token ujian.

Fungsi:

- Membaca `jadwal` dari query route.
- Membuat fingerprint perangkat.
- Mengirim token ke `POST /ujian/{jadwalHash}/mulai`.
- Jika berhasil, redirect ke `/vue/ujian?session={session_hash}`.
- Menampilkan error dengan SweetAlert2 jika token salah atau ujian tidak aktif.

### `resources/js/pages/Exam/ExamInterface.vue`

Interface utama pengerjaan ujian.

Fungsi:

- Membaca `session` hash dari query.
- Mengambil payload sesi dari `GET /ujian/sesi/{hash}`.
- Mengambil soal per nomor dari `GET /ujian/sesi/{hash}/soal`.
- Menampilkan timer, identitas ujian, profil siswa, pertanyaan, gambar, opsi PG/isian, tombol navigasi, dan panel nomor soal.
- Menyimpan jawaban ke `POST /ujian/sesi/{hash}/simpan`.
- Submit ujian ke `POST /ujian/sesi/{hash}/selesai`.
- Menggunakan Bootstrap modal untuk konfirmasi selesai.
- Mengirim ping/event untuk menjaga status online.
- Memakai SweetAlert2 untuk notifikasi dan error.

Data yang ditampilkan:

- Mapel dan judul ujian.
- Timer sisa waktu.
- Nama/kelas siswa.
- Nomor soal.
- Tipe soal.
- Pertanyaan dan gambar.
- Opsi jawaban atau textarea isian.
- Status ragu-ragu.
- Grid navigasi soal.

Catatan penting:

- Frontend tidak boleh menerima kunci jawaban.
- Autosave harus tetap ringan karena backend menyimpan ke Redis/queue.
- Jika waktu habis, UI harus mendorong submit.

### `resources/js/pages/Exam/ExamFinish.vue`

Halaman selesai ujian.

Fungsi:

- Membaca `session` hash dari route/query.
- Mengambil hasil dari `GET /ujian/sesi/{sessionHash}/hasil`.
- Menampilkan status selesai, ringkasan jawaban, nilai jika boleh tampil, dan tombol kembali dashboard.

## Halaman Monitoring

### `resources/js/pages/Monitoring/LiveRadar.vue`

Halaman radar/live monitoring.

Fungsi:

- Mengambil user dari `/auth/user`.
- Mengambil data radar dari `/monitoring/radar`.
- Refresh berkala.
- Menampilkan status peserta real-time.
- Menyediakan reset sesi peserta melalui `/kelola/sesi/{id}/reset`.

Halaman ini dipakai untuk pemantauan luas, terutama oleh SuperAdmin/Panitia/Pengawas.

## Halaman Management

### `resources/js/pages/Management/StaffManagement.vue`

Manajemen staf.

Fungsi:

- List staf dari `/api/management/staff`.
- Tambah staf.
- Edit staf.
- Hapus staf.
- Reset password staf.
- Filter/pencarian lokal.

Endpoint:

- `GET /api/management/staff`.
- `POST /api/management/staff`.
- `PUT /api/management/staff/{id}`.
- `PATCH /api/management/staff/{id}/password`.
- `DELETE /api/management/staff/{id}`.

### `resources/js/pages/Management/StudentManagement.vue`

Manajemen siswa.

Fungsi:

- List siswa dari `/kelola/data/siswa`.
- Mengambil master kelas dari `/kelola/data/master-sekolah`.
- Tambah siswa.
- Edit siswa.
- Reset password siswa.
- Hapus siswa.
- Import Excel siswa.
- Filter tingkat/jurusan/rombel.

Endpoint:

- `GET /kelola/data/siswa`.
- `POST /kelola/data/siswa`.
- `PUT /kelola/data/siswa/{id}`.
- `PATCH /kelola/data/siswa/{id}/password`.
- `DELETE /kelola/data/siswa/{id}`.

### `resources/js/pages/Management/MasterDataManagement.vue`

Master data sekolah.

Fungsi:

- Menampilkan tingkat, jurusan, rombel, kelas aktif, dan mapel.
- Tambah/hapus tingkat.
- Tambah/edit/hapus jurusan.
- Tambah/edit/hapus mapel.
- Generate kelas.
- Import siswa ke kelas yang baru dibuat.

Endpoint:

- `/kelola/data/master-sekolah`.
- `/kelola/data/tingkat`.
- `/kelola/data/jurusan`.
- `/kelola/mapel`.
- `/kelola/master/mapel/{id}`.
- `/kelola/data/kelas/generate`.
- `/kelola/data/kelas/{id}/import-siswa`.

### `resources/js/pages/Management/QuestionBank.vue`

Bank soal.

Fungsi:

- List paket soal.
- Membuat paket soal.
- Edit/hapus paket.
- Detail paket dan daftar soal.
- Tambah/edit/hapus soal.
- Upload gambar soal.
- Menentukan opsi benar untuk PG.
- Import soal dari Excel/CSV.
- Menandai paket sebagai ready.

Endpoint:

- `GET /kelola/data/paket-soal`.
- `POST /kelola/data/paket-soal`.
- `GET /kelola/data/paket-soal/{id}`.
- `PUT /kelola/data/paket-soal/{id}`.
- `DELETE /kelola/data/paket-soal/{id}`.
- `POST /kelola/data/paket-soal/{id}/ready`.
- `POST /kelola/data/paket-soal/{id}/import`.
- `POST /kelola/data/paket-soal/{packageId}/soal`.
- `POST /kelola/data/paket-soal/{packageId}/soal/{id}`.
- `DELETE /kelola/data/paket-soal/{packageId}/soal/{id}`.

### `resources/js/pages/Management/ExamSchedule.vue`

Manajemen jadwal ujian.

Fungsi:

- Mengambil data master ujian, paket ready, kelas, dan jadwal dari `/kelola/data/jadwal-ujian`.
- Membuat master ujian.
- Edit master ujian.
- Membuat jadwal ujian.
- Edit jadwal ujian.
- Regenerate token.
- Arsip/hapus jadwal.

Endpoint:

- `GET /kelola/data/jadwal-ujian`.
- `POST /kelola/data/master-ujian`.
- `PUT /kelola/data/master-ujian/{id}`.
- `POST /kelola/data/jadwal-ujian`.
- `PUT /kelola/data/jadwal-ujian/{id}`.
- `POST /kelola/data/jadwal-ujian/{id}/token`.
- `DELETE /kelola/data/jadwal-ujian/{id}`.

### `resources/js/pages/Management/ExamResults.vue`

Hasil ujian aktif.

Fungsi:

- Mengambil options kelas dari `/kelola/data/hasil-ujian/options`.
- Filter tingkat, jurusan, rombel.
- Load daftar ujian aktif/belum arsip.
- Setelah user memilih ujian, load statistik dan tabel nilai.

Aturan:

- Hanya jadwal belum arsip.
- Query detail nilai dilakukan dua tahap agar halaman tidak berat.

Endpoint:

- `GET /kelola/data/hasil-ujian/options`.
- `GET /kelola/data/hasil-ujian`.

### `resources/js/pages/Management/ExamResultDownloads.vue`

Preview/download PDF hasil.

Fungsi:

- Mengambil options dari `/kelola/data/download-hasil/options`.
- Memilih kelas dan jadwal belum arsip.
- Preview PDF hasil.
- Download PDF hasil.
- Mengarsipkan jadwal lewat delete schedule jika syarat terpenuhi.

Endpoint:

- `GET /kelola/data/download-hasil/options`.
- `GET /kelola/data/download-hasil/preview`.
- `GET /kelola/data/download-hasil/download`.
- `DELETE /kelola/data/jadwal-ujian/{id}`.

### `resources/js/pages/Management/ExamResultArchives.vue`

Arsip hasil ujian.

Fungsi:

- Mengambil tahun dan kelas arsip dari `/kelola/data/arsip-hasil/options`.
- Filter tahun, tingkat, jurusan, rombel.
- Load daftar ujian yang sudah arsip.
- Load detail nilai untuk satu jadwal arsip.

Aturan:

- Hanya jadwal dengan `diarsipkan_at` terisi.
- Tidak memakai endpoint download hasil aktif.

Endpoint:

- `GET /kelola/data/arsip-hasil/options`.
- `GET /kelola/data/arsip-hasil`.

### `resources/js/pages/Management/DeviceFingerprints.vue`

Manajemen device lock.

Fungsi:

- Mengambil daftar fingerprint dari `/kelola/data/device-fingerprints`.
- Menampilkan status siswa, fingerprint, lock status, sesi aktif, dan indikasi perangkat ganda.
- Unlock siswa.
- Lock siswa manual.
- Menggunakan Bootstrap modal dan SweetAlert2.

Endpoint:

- `GET /kelola/data/device-fingerprints`.
- `POST /kelola/data/device-fingerprints/{id}/unlock`.
- `POST /kelola/data/device-fingerprints/{id}/lock`.

## Blade View

### `resources/views/app.blade.php`

Shell utama Vue SPA.

Fungsi:

- Menyediakan elemen `#app`.
- Memuat asset Vite.
- Menyediakan meta CSRF.
- Menjadi response untuk route `/vue/{any}` dan `/vue/management/{any}`.

### `resources/views/layouts/app.blade.php`

Layout Blade umum.

Fungsi:

- Template dasar halaman Blade lama.
- Biasanya memuat struktur HTML, asset, dan slot/yield content.

### `resources/views/auth/login.blade.php`

Halaman login web.

Fungsi:

- Form username/password.
- Mengirim ke `POST /login`.
- Menampilkan error validasi/login.

### `resources/views/dashboard/index.blade.php`

Fallback/halaman dashboard Blade.

Fungsi:

- Menjadi view dashboard jika request bukan JSON atau belum diarahkan ke Vue dashboard.

### `resources/views/exams/show.blade.php`

View ujian Blade lama.

Fungsi:

- Menampilkan interface ujian non-SPA/legacy.
- Tetap berguna untuk fallback atau referensi layout.

### `resources/views/exams/result.blade.php`

View hasil ujian Blade lama.

Fungsi:

- Menampilkan hasil ujian berbasis server-rendered page.

### `resources/views/exams/final.blade.php`

View final/konfirmasi ujian.

Fungsi:

- Menampilkan halaman akhir atau konfirmasi sebelum selesai dalam flow Blade lama.

### `resources/views/exams/final-result.blade.php`

View hasil final.

Fungsi:

- Menampilkan hasil final dengan format server-rendered.

### `resources/views/manage/index.blade.php`

Shell management lama.

Fungsi:

- Menjadi view untuk route `/kelola` dan fallback management lama.

### `resources/views/manage/package-preview.blade.php`

Preview paket soal.

Fungsi:

- Menampilkan paket soal dan isi soal dalam format preview.

### `resources/views/questions/index.blade.php`

View bank soal lama.

Fungsi:

- Halaman server-rendered untuk soal sebelum Vue management modern.

### `resources/views/schedules/index.blade.php`

View jadwal lama.

Fungsi:

- Halaman server-rendered untuk jadwal sebelum Vue management modern.

### `resources/views/reports/pdf.blade.php`

Template PDF hasil aktif.

Fungsi:

- Dipakai `ExamResultDownloadController` untuk preview/download hasil.
- Menampilkan identitas jadwal, kelas, statistik, dan tabel siswa.

### `resources/views/reports/archive-pdf.blade.php`

Template PDF arsip.

Fungsi:

- Format PDF untuk data arsip hasil ujian.

### `resources/views/welcome.blade.php`

View bawaan/landing sederhana.

Fungsi:

- Tidak menjadi UI utama karena `/` diarahkan ke `/login`.

## Public Asset dan PWA

### `public/index.php`

Front controller Laravel.

Fungsi:

- Semua request web masuk ke file ini melalui web server.
- Mem-bootstrap Laravel.

### `public/manifest.json`

Manifest PWA.

Fungsi:

- Nama aplikasi.
- Warna tema.
- Start URL.
- Icon metadata.

### `public/sw.js`

Service worker.

Fungsi:

- Cache asset statis tertentu.
- Fallback ke `/offline.html` saat fetch gagal.
- Membantu pengalaman offline terbatas.

### `public/offline.html`

Halaman fallback offline.

Fungsi:

- Ditampilkan service worker saat request gagal dan halaman tidak tersedia dari cache.

### `public/recover-login.html`

Halaman recovery cache/login.

Fungsi:

- Membantu user membersihkan cache/chunk lama setelah deploy.
- Dipakai link dari fatal error overlay di `main.js`.

### `public/robots.txt`

Instruksi crawler.

Fungsi:

- Mengatur akses bot/search engine.

### `public/favicon.ico`

Icon browser.

Catatan:

- Saat ini file terlihat kosong/0 byte di listing. Jika ingin branding rapi, perlu diganti favicon valid.

### `public/icons/cbt.svg`

Icon SVG CBT.

Fungsi:

- Asset icon aplikasi.

### `public/build`

Output build Vite.

Fungsi:

- Berisi manifest dan asset hash hasil `npm run build`.
- Jangan diedit manual.
- Bisa dibuat ulang.

File penting:

- `public/build/manifest.json`.
- `public/build/fonts-manifest.json`.
- `public/build/assets/*.js`.
- `public/build/assets/*.css`.
- `public/build/assets/*.woff/woff2`.

### `public/storage`

Symlink/folder public storage Laravel.

Fungsi:

- Mengakses file dari `storage/app/public`.
- Dipakai gambar soal atau file publik lain.

### `public/T3V7OJ~5` dan `public/T68XVV~A`

Artefak runtime yang salah tempat/terkunci permission.

Catatan:

- `T3V7OJ~5` berisi log.
- `T68XVV~A` berisi compiled Blade error view.
- Bukan source frontend.
- Sudah di-ignore di `.gitignore`.
- Perlu dihapus dari sisi server/container pemilik file jika ingin bersih total.

## UI Statis Lama

### `ui/dashboardsuperadmin.html`

Prototype dashboard SuperAdmin statis.

Fungsi:

- Referensi desain lama.
- Tidak menjadi route utama production Vue.

### `ui/dashboardpanitia.html`

Prototype dashboard Panitia statis.

Fungsi:

- Referensi desain monitoring panitia.

### `ui/ujian.html`

Prototype halaman ujian statis.

Fungsi:

- Referensi layout ujian sebelum implementasi Vue.

### `ui/token.html`

Prototype halaman token.

Fungsi:

- Referensi input token ujian.

### `ui/selesai ujian.html`

Prototype halaman selesai ujian.

Fungsi:

- Referensi tampilan akhir ujian.

## Catatan UX dan Maintenance

- Halaman management memakai pola layout sidebar + konten utama.
- Halaman hasil/arsip memakai pola dua tahap agar query nilai tidak langsung berat.
- Halaman ujian harus stabil di mobile dan desktop.
- Jangan memakai `v-html` untuk pertanyaan dari user kecuali sudah disanitasi.
- Jangan tampilkan kunci jawaban di response frontend.
- Setelah deploy Vite, jika user melihat halaman gagal dimuat, gunakan `/recover-login.html` atau reload hard.

