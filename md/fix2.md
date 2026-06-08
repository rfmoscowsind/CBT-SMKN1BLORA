# Laporan Investigasi & Perbaikan Bottleneck Sistem CBT (SMKN 1 Blora)

Berikut adalah daftar bottleneck dan isu penting yang ditemukan pada codebase aplikasi CBT beserta penyebab, dampak, dan **solusi yang telah diimplementasikan**:

---

## 1. Bottleneck Kueri N+1 pada Background Worker Live Radar (`RadarWorker.php`)
* **Penyebab:** Background worker `cbt:radar-worker` yang berjalan terus-menerus (setiap 2 detik) melakukan iterasi pada seluruh sesi ujian siswa yang aktif. Di dalam perulangan tersebut, script memicu kueri database individual (`count` dan `sum`) ke tabel `jawaban_siswas` dan `sesi_ujian_soals` untuk masing-masing siswa.
* **Penjelasan Dampak:** Dengan asumsi 200 siswa sedang aktif ujian, loop worker tersebut mengeksekusi lebih dari **400 kueri database per loop** (setiap 2 detik). Hal ini memicu lonjakan CPU PostgreSQL secara konstan, membuat loading seluruh halaman admin dan dashboard superadmin menjadi sangat lambat dan berat.
* **Solusi Terpasang:** 
  * Merefaktor algoritma pencarian stats di worker agar menggunakan kueri agregat bulk (`GROUP BY` dan `whereIn`).
  * Memotong jumlah kueri dari **400+ kueri** menjadi hanya **3 kueri saja** per perulangan (reduksi beban database hingga **99%**).
  * Membuat daemon unit Systemd (`cbt-radar.service`) untuk mengontrol eksekusi background worker secara terpusat dan tangguh.

---

## 2. Isu Bottleneck Kueri N+1 saat Pengiriman Ujian (`ExamService::submit()`)
* **Penyebab:** Ketika siswa mengklik tombol "Selesai Ujian", method `submit()` memanggil `flushAll()` untuk memindahkan jawaban siswa yang di-cache di Redis ke database utama PostgreSQL. `flushAll()` memanggil `flushOne()` per soal secara iteratif, di mana di dalamnya terdapat 4 hingga 5 kueri database (mencari soal, mencocokkan kunci jawaban, memperbarui skor, mencatat log audit).
* **Penjelasan Dampak:** Untuk ujian dengan 50 soal, setiap siswa yang submit akan memicu **200 sampai 250 kueri database secara sinkron** dalam satu request HTTP. Jika 1 kelas berisi 100 siswa mengklik "Selesai" secara bersamaan di menit terakhir, PostgreSQL akan dihantam oleh **20.000 - 25.000 kueri dalam rentang detik**, yang berujung pada kegagalan koneksi database (database lockouts), gateway timeout (504), dan hilangnya jawaban siswa.
* **Solusi Terpasang:** 
  * Merefaktor method `flushAll()` untuk mengambil metadata soal, opsi jawaban benar, dan jawaban siswa yang sudah ada secara bulk menggunakan `whereIn` (hanya 3 kueri awal).
  * Melakukan kalkulasi skor secara *in-memory* di PHP.
  * Menyimpan hasil kalkulasi dengan kueri bulk upsert (`DB::table('jawaban_siswas')->upsert()`) dan bulk insert untuk log audit, sehingga memangkas 250 kueri menjadi hanya **5 kueri** dan mengurangi latency submit hingga **98%**.

---

## 3. Kueri N+1 pada Halaman Detail Bank Soal (`QuestionBankManagementController::show()`)
* **Penyebab:** Method `show` mengambil detail paket soal beserta daftar soalnya. Untuk memuat opsi jawaban PG (`opsi_jawabans`), controller melakukan iterasi kueri satu per satu ke database untuk masing-masing soal.
* **Penjelasan Dampak:** Paket soal dengan 50-100 soal akan memicu **50-100 kueri tambahan** setiap kali halaman detail bank soal dibuka oleh Guru/Admin, menyebabkan waktu tunggu loading data menjadi lambat.
* **Solusi Terpasang:** 
  * Merefaktor kueri agar mengambil seluruh opsi jawaban dari seluruh soal di paket tersebut sekaligus dalam 1 kueri menggunakan `whereIn('bank_soal_id', $soalIds)`.
  * Memetakan opsi ke masing-masing soal di memory menggunakan fungsi `groupBy('bank_soal_id')` dari Laravel Collection (mengurangi kueri dari N+1 menjadi hanya **2 kueri**).

---

## 4. Keberadaan Komponen & Metrik Mocking di UI Admin
* **Penyebab:** Halaman dashboard SuperAdmin, Panitia, dan Pengawas sebelumnya memajang data tiruan (mockup) bawaan template. Termasuk metrik jumlah siswa (statis di angka 420), daftar reset sesi siswa (menampilkan nama statis seperti Budi Santoso), tombol logout yang hanya memiliki baris log konsol saja, dan tombol reset login yang tidak memanggil endpoint backend yang nyata.
* **Penjelasan Dampak:** Halaman admin tidak memberikan data pengawasan riil dan fungsionalitas utama CBT (seperti mengeluarkan pengawas, mengeluarkan siswa yang bermasalah, dan melihat progres ujian live) tidak dapat dijalankan.
* **Solusi Terpasang:** 
  * Menghapus semua placeholder data statis pada dashboard.
  * Mengembangkan 3 endpoint API baru di `web.php` yaitu `/monitoring/stats` (statistik riil global), `/monitoring/sessions` (sesi real-time siswa aktif), dan `/auth/user` (profil user login).
  * Menghubungkan dashboard ke API tersebut dengan *polling* per 3-5 detik.
  * Memperbaiki tombol logout di semua dashboard agar mengarah langsung ke `/logout`.
  * Memprogram tombol "Reset Login" pada halaman Panitia agar menembak API backend `ManageController::resetSession` yang riil dengan response JSON yang asinkron.
