# 🚀 LAPORAN AUDIT KODE PROGRAM CBT SMKN 1 BLORA
## Analisis Performa & Fungsi untuk 1500 Siswa Concurrent

Dokumen ini berisi hasil analisis menyeluruh terhadap kode program (CBT) untuk menemukan bug, masalah fungsional, dan bottleneck performa, terutama ketika sistem diakses oleh 1500 siswa secara bersamaan.

---

### 1. 🔴 CRITICAL: N+1 Database Query pada Endpoint `/ujian/sesi/{id}` (Wasted Query Beban Tinggi)

* **Isu:**
  Saat siswa memuat halaman ujian, route `GET /ujian/sesi/{id}` memanggil fungsi `examPayload()`. Di dalamnya terdapat pemanggilan `$this->questionList($session, $items)` yang melakukan iterasi (looping) pada semua nomor soal (biasanya 40–50 soal).
  Di setiap iterasi, sistem memanggil `singleQuestionPayload()` yang menjalankan **4 query database** (`sesi_ujian_soals`, `bank_soals`, `jawaban_siswas`, dan `opsi_jawabans`).
  
  Artinya, jika ujian memiliki 40 soal, Laravel akan menjalankan:
  $$4 \text{ query} \times 40 \text{ soal} = 160 \text{ query database per siswa}$$
  
  Untuk **1500 siswa** yang memuat halaman ujian secara bersamaan, database akan dihujani oleh:
  $$160 \times 1500 = 240,000 \text{ query database!}$$
  
  **Konyolnya (Wasted Work):** Pada Vue SPA (`ExamInterface.vue`), fungsi `fetchExamData` **sama sekali tidak menggunakan data `res.data.soal`** tersebut! Vue hanya mengambil meta-data seperti `identitas`, `siswa`, `navigasi`, `total_soal`, dan `sisa_detik`, kemudian langsung memanggil `fetchSoal(1)` secara terpisah untuk memuat soal nomor 1.

* **Efek:**
  Database PostgreSQL akan mengalami lonjakan CPU hingga 100%, terjadi antrean query (connection pool exhaustion), waktu pemuatan halaman ujian menjadi sangat lambat (loading berputar terus menerus), hingga akhirnya server down dengan error HTTP 500 / 504 Gateway Timeout saat ujian dimulai serentak.

* **Solusi:**
  Hapus key `'soal' => $questions` dari payload yang dikembalikan oleh fungsi `examPayload()` di `WebController.php`. Karena Vue SPA tidak menggunakan data array soal lengkap ini di awal (Vue memuat soal secara dinamis per nomor via `/soal`), penghapusan ini aman secara fungsional dan akan menghemat ratusan ribu query database secara instan.

---

### 2. 🔴 CRITICAL: DB Write-Locks Terus-menerus Akibat Pembaruan Fingerprint pada Setiap Request

* **Isu:**
  Pada setiap aksi siswa (misalnya memuat soal baru, menyimpan jawaban, sinkronisasi, ping berkala, atau mencatat log aktivitas), sistem memanggil middleware/fungsi `enforceDeviceFingerprintForUser()`.
  Ketika fingerprint gawai siswa cocok (yang merupakan kondisi normal 99% siswa selama ujian), sistem tetap memanggil:
  ```php
  $this->rememberUserDevice($freshUser, $request);
  if ($session && isset($session->id)) {
      $this->rememberSessionDevice((int) $session->id, $request);
  }
  ```
  Ini memaksa Laravel untuk menjalankan **2 query UPDATE** ke database pada **setiap request**:
  1. `UPDATE users SET device_fingerprint = ..., device_fingerprint_raw = ... WHERE id = ...`
  2. `UPDATE sesi_ujians SET device_fingerprint = ..., device_fingerprint_raw = ... WHERE id = ...`

* **Efek:**
  Dengan 1500 siswa aktif mengirimkan ping, menyimpan jawaban, dan berpindah soal (misalnya rata-rata 1 request per 10 detik dari tiap siswa = 150 request per detik), database akan dipaksa melakukan **300 operasi menulis (write) per detik** pada baris tabel `users` yang sama.
  Ini menyebabkan masalah **Row-Level Write Lock Contention** di PostgreSQL. Transaksi lain yang ingin mengakses data user tersebut akan antre (blocking), menyebabkan server kehabisan resource dan crash total.

* **Solusi:**
  Modifikasi `enforceDeviceFingerprintForUser()` di `HandlesDeviceFingerprints.php` agar hanya menjalankan `rememberUserDevice` dan `rememberSessionDevice` jika sidik jari sebelumnya memang bernilai kosong (`null`). Jika sidik jari sudah terdaftar dan bernilai sama (cocok), lewati (skip) proses penulisan ke database.

---

### 3. 🟡 HIGH: Double Execution `flushOne()` (Sinkron & Asinkron Ganda)

* **Isu:**
  Di `ExamService::save()`, ketika siswa memilih jawaban, sistem menyimpan jawaban ke Redis dan memicu job antrean `PersistAnswerSnapshot` untuk memproses penulisan ke database secara asinkron (background).
  Namun, pada `WebController::save()` dan `ApiController::save()`, setelah memanggil `$this->exams->save()`, kode program langsung memanggil `$this->exams->flushOne(...)` secara sinkron (blocking) dalam request HTTP yang sama.
  
* **Efek:**
  Hal ini menyebabkan fungsi `flushOne()` dijalankan **dua kali** untuk setiap klik jawaban siswa:
  1. Pertama secara sinkron saat request HTTP diproses.
  2. Kedua secara asinkron di antrean queue worker Redis.
  
  Ini melipatgandakan jumlah penulisan jawaban siswa ke database dan log audit secara sia-sia. Dengan 1500 siswa, lalu lintas menulis (write load) pada tabel `jawaban_siswas` dan `audit_logs` akan menjadi **2x lipat lebih berat** dari yang seharusnya, dan membuat antrean Redis queue dipenuhi oleh job duplikat yang mubazir.

* **Solusi:**
  Hapus pemanggilan `$this->exams->flushOne(...)` dari `WebController::save()`, `WebController::sync()`, `ApiController::save()`, dan `ApiController::sync()`. Biarkan background queue worker yang memproses penyimpanan ke database secara asinkron lewat job `PersistAnswerSnapshot` agar response API/Web ke siswa menjadi sangat cepat (< 50ms).

---

### 4. 🟡 HIGH: Tiga Read Query Database Sebelum Menulis ke Redis pada `ExamService::save()`

* **Isu:**
  Tujuan utama menggunakan Redis queue di `ExamService::save()` adalah untuk mempercepat proses penyimpanan jawaban (ingestion) tanpa membebani database PostgreSQL secara langsung. Namun sebelum menulis ke Redis, fungsi `save()` masih melakukan **3 query read** ke Postgres:
  1. Mengambil data soal (`DB::table('bank_soals')->find($soalId)`).
  2. Memeriksa keberadaan soal dalam sesi (`DB::table('sesi_ujian_soals')->where(...)`).
  3. Memeriksa kevalidan opsi jawaban (`DB::table('opsi_jawabans')->where(...)`).

* **Efek:**
  Manfaat penulisan cepat Redis menjadi terhambat karena setiap klik jawaban siswa tetap memicu 3 read query ke PostgreSQL. Pada beban 1500 siswa serentak, database Postgres masih harus melayani ribuan read request per menit hanya untuk validasi jawaban.

* **Solusi:**
  Gunakan Laravel Cache (yang disokong oleh Redis) untuk menyimpan struktur soal dan opsi jawaban (karena bersifat statis selama ujian berlangsung). Validasi eksistensi sesi dan opsi dapat dilakukan melalui cache Redis tanpa harus menyentuh database relasional Postgres pada setiap klik jawaban siswa.

---

### 5. 🟡 MEDIUM: Fungsi `rejectStaleDeviceResetSession` Tidak Berjalan pada Koneksi API

* **Isu:**
  Fungsi keamanan `rejectStaleDeviceResetSession()` bergantung pada PHP Session (`session("cbt_device_reset_ack...")`) untuk mendeteksi apakah reset device sudah dikonfirmasi.
  Namun, pada koneksi API (`ApiController`), Laravel berjalan secara stateless (tanpa PHP session, melainkan menggunakan JWT). Selain itu, fungsi `rejectStaleDeviceResetSession()` tidak pernah dipanggil di endpoint API siswa.

* **Efek:**
  Jika admin mereset gawai siswa, siswa yang login menggunakan API/SPA tidak akan dipaksa logout dari peramban lamanya secara otomatis. Mereka tetap bisa melanjutkan ujian di gawai lama dan berpotensi memicu konflik sidik jari ganda jika berpindah ke gawai baru tanpa logout bersih.

* **Solusi:**
  Implementasikan validasi reset sidik jari berbasis cache Redis/database di dalam middleware API siswa, atau simpan timestamp reset terakhir di kolom database `users` lalu bandingkan dengan claim `iat` (issued at) pada JWT Token. Jika token dibuat sebelum waktu reset, tolak request dan paksa login ulang.

---

### 6. 🟡 MEDIUM: Ketidaksesuaian Format Payload dan Filter Tanggal Jadwal pada `ApiController`

* **Isu:**
  Ada dua ketidaksesuaian logika antara `ApiController::studentSchedules()` dan `WebController::studentSchedules()`:
  1. `ApiController` mengambil **semua** jadwal tanpa filter tanggal hari ini, sedangkan `WebController` membatasi hanya untuk hari ini (`waktu_mulai <= endOfDay` dan `waktu_selesai >= startOfDay`).
  2. `ApiController` tidak menyertakan field `jumlah_soal` dan `tipe_soal` pada array responsnya, sedangkan Vue SPA membutuhkannya untuk menampilkan informasi detail di dashboard.

* **Efek:**
  Siswa yang mengakses jadwal lewat API akan melihat daftar ujian lama/masa depan yang tidak relevan, dan aplikasi Vue frontend kemungkinan akan mengalami error javascript (blank page/undefined error) karena hilangnya properti `jumlah_soal` dan `tipe_soal`.

* **Solusi:**
  Samakan logika query dan format output `studentSchedules()` pada `ApiController` agar memiliki filter tanggal yang sama serta menyertakan field `jumlah_soal` dan `tipe_soal` yang dihitung secara efisien menggunakan batch query (seperti yang sudah diterapkan di `WebController`).

---

### 7. 🟢 INFO: N+1 Query pada Validasi Paket Soal `QuestionBankManagementController::ready()`

* **Isu:**
  Saat guru/admin mengubah status paket soal menjadi ready, fungsi `ready()` melakukan iterasi terhadap seluruh soal di dalam paket untuk memvalidasi opsi jawaban PG:
  ```php
  foreach ($questions as $question) {
      if ($question->tipe_soal === 'PG') {
          // Query 1: Menghitung jumlah opsi
          DB::table('opsi_jawabans')->where('bank_soal_id', $question->id)->count();
          // Query 2: Memastikan tepat ada satu kunci jawaban yang benar
          DB::table('opsi_jawabans')->where(['bank_soal_id' => $question->id, 'is_benar' => true])->count();
      }
  }
  ```
  Ini menghasilkan $2 \times N$ query database (misal 80 query untuk 40 soal).

* **Efek:**
  Tombol "Set Ready" pada panel admin/guru terasa lambat dan memberikan beban baca yang tinggi pada database, meskipun ini terjadi di sisi admin dan tidak berdampak langsung pada siswa.

* **Solusi:**
  Lakukan penarikan data opsi secara massal (batch fetch) dengan `whereIn('bank_soal_id', $soalIds)` sebelum looping, kelompokkan menggunakan `groupBy('bank_soal_id')`, lalu lakukan validasi jumlah opsi dan kunci jawaban di memori PHP (menggunakan Collection Laravel).

---

### Kesimpulan Prioritas Perbaikan

| Prioritas | Masalah | Tipe | Dampak |
| :--- | :--- | :--- | :--- |
| **🔴 P1 (Mendesak)** | N+1 Query pada `/ujian/sesi/{id}` (Wasted Query) | Performa | **Database crash serentak** saat ujian dimulai |
| **🔴 P1 (Mendesak)** | Write-locks konstan update fingerprint | Performa | **Postgres deadlock** akibat write lock tabel `users` |
| **🟡 P2 (Tinggi)** | Double execution `flushOne()` | Performa | Beban database & Redis membengkak 2x lipat |
| **🟡 P2 (Tinggi)** | 3 DB read query di Redis `save()` | Performa | Mengurangi efisiensi caching Redis |
| **🟡 P3 (Sedang)** | `rejectStaleDeviceResetSession` mati di API | Keamanan | Reset gawai admin tidak memutus sesi aktif SPA |
| **🟡 P3 (Sedang)** | Inkonsistensi format jadwal `ApiController` | Fungsi | Error tampilan pada dashboard Vue SPA siswa |
| **🟢 P4 (Rendah)** | N+1 Query pada set paket soal ready | Performa | Panel guru lambat saat memproses paket soal |
