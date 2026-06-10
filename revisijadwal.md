# Revisi Jadwal Ujian - Batch Multi-Grup

Dokumen ini menjelaskan rencana revisi alur pembuatan jadwal ujian agar lebih singkat, fleksibel, dan cocok untuk operasional sekolah.

Tanggal: 2026-06-08
Repo: `rfmoscowsind/CBT-SMKN1BLORA`

> Status: dokumen perencanaan. Belum mengubah kode aplikasi.

---

## 1. Masalah Alur Saat Ini

Alur saat ini:

```text
1. Buat Paket Soal
2. Finalisasi Paket Soal sampai status ready
3. Buat Master Ujian
4. Pilih paket soal di Master Ujian
5. Buat Jadwal Ujian
6. Pilih Master Ujian
7. Pilih kelas
8. Set jam mulai, jam selesai, durasi
9. Submit
```

Alur tersebut benar secara teknis, tetapi untuk panitia/admin terasa panjang karena harus memahami konsep:

```text
Paket Soal -> Master Ujian -> Jadwal Ujian
```

Padahal secara operasional, admin biasanya ingin langsung:

```text
Pilih mapel/paket, pilih kelas/rombel, set jam, token, submit.
```

---

## 2. Tujuan Revisi

Tujuan revisi:

1. Membuat alur pembuatan jadwal lebih cepat.
2. Menyembunyikan kompleksitas Master Ujian dari flow utama.
3. Mendukung pembuatan banyak jadwal dalam satu modal.
4. Mendukung banyak jurusan dan banyak rombel dalam satu batch.
5. Mendukung mapel/paket berbeda dalam batch yang sama.
6. Mendukung satu token bersama untuk semua jadwal hasil batch.
7. Tetap menjaga struktur backend agar aman dan tidak terlalu banyak rombak.
8. Mengurangi risiko delete data ujian secara tidak sengaja.

---

## 3. Konsep Final yang Diusulkan

Nama fitur:

```text
Batch Jadwal Multi-Grup
```

Definisi:

```text
Satu batch dapat berisi banyak grup jadwal.
Setiap grup dapat memiliki jurusan, rombel, mapel/paket, jam, durasi, dan opsi ujian sendiri.
Semua jadwal dalam satu batch memakai token yang sama.
```

Contoh:

```text
Batch: Ujian Kelas X Hari Senin Sesi 1
Token: ABC123

Grup 1:
- Tingkat: X
- Jurusan: DKV
- Rombel: 1, 2
- Paket/Mapel: Desain Grafis
- Jam: 08:00 - 10:00
- Durasi: 90 menit

Grup 2:
- Tingkat: X
- Jurusan: TAV
- Rombel: 1, 2, 3
- Paket/Mapel: Dasar Elektronika
- Jam: 08:00 - 10:00
- Durasi: 90 menit
```

Hasil jadwal:

```text
X DKV 1 -> Desain Grafis -> token ABC123
X DKV 2 -> Desain Grafis -> token ABC123
X TAV 1 -> Dasar Elektronika -> token ABC123
X TAV 2 -> Dasar Elektronika -> token ABC123
X TAV 3 -> Dasar Elektronika -> token ABC123
```

---

## 4. Prinsip Desain Backend

Rekomendasi backend:

```text
1 kelas = 1 jadwal_ujian
```

Walaupun dibuat dengan batch, hasil akhirnya tetap banyak row `jadwal_ujians`.

Alasan:

1. Cocok dengan flow repo saat ini.
2. Risiko perubahan lebih kecil.
3. Monitoring per kelas lebih jelas.
4. Edit/hapus/arsip per kelas lebih mudah.
5. Token bisa dibuat sama untuk batch tanpa mengubah struktur besar.
6. Tidak perlu langsung mengubah pola query dashboard siswa.

Alternatif yang tidak direkomendasikan untuk tahap awal:

```text
1 jadwal = banyak kelas
```

Walaupun tabel `jadwal_ujian_kelas` mendukung multi-kelas, flow controller dan UI saat ini masih lebih cocok dengan model satu jadwal satu kelas.

---

## 5. Alur Baru untuk Admin

Alur baru:

```text
1. Paket Soal tetap dibuat dan difinalisasi sampai ready.
2. Admin klik tombol Buat Jadwal Baru.
3. Modal Batch Jadwal terbuka.
4. Admin isi header batch.
5. Admin tambah satu atau lebih grup jadwal.
6. Sistem menampilkan preview hasil expand ke kelas nyata.
7. Admin klik Submit Batch.
8. Sistem otomatis membuat/reuse Master Ujian.
9. Sistem membuat jadwal per kelas.
10. Semua jadwal hasil batch memakai token batch yang sama.
```

Master Ujian tetap ada di database, tetapi tidak perlu ditonjolkan di UI utama.

---

## 6. UI yang Diusulkan

### 6.1 Tombol utama

Di halaman Jadwal Ujian:

```text
+ Buat Jadwal Baru
```

Saat diklik, modal baru terbuka:

```text
Buat Jadwal Batch
```

---

## 7. Struktur Modal Batch

Modal dibagi menjadi 3 bagian:

```text
1. Header Batch
2. Form Tambah Grup
3. Tabel Preview Grup/Jadwal
```

---

## 8. Header Batch

Field header batch:

```text
Nama Batch
Tingkat
Token Batch
Gunakan Token
Waktu Default Mulai
Waktu Default Selesai
Durasi Default
Acak Soal Default
Acak Opsi Default
Visibilitas Hasil Default
Tanggal Rilis Hasil Default jika scheduled
```

Contoh:

```text
Nama Batch              : Ujian Kelas X Hari Senin Sesi 1
Tingkat                 : X
Gunakan Token           : ON
Token Batch             : ABC123
Waktu Default Mulai     : 08:00
Waktu Default Selesai   : 10:00
Durasi Default          : 90 menit
Acak Soal Default       : ON
Acak Opsi Default       : ON
Visibilitas Hasil       : Manual
```

Catatan:

- Token batch berlaku untuk semua jadwal yang dibuat dalam batch.
- Token batch default auto-generate.
- Admin dapat klik Generate Ulang.
- Token bisa dibuat manual jika dibutuhkan.

---

## 9. Form Tambah Grup

Field tambah grup:

```text
Jurusan
Rombel multi-select
Paket Soal / Mapel
Gunakan pengaturan khusus
Mulai
Selesai
Durasi
Acak Soal
Acak Opsi
Tampilkan Nilai
Visibilitas Hasil
Tanggal Rilis Hasil
```

Jika `Gunakan pengaturan khusus` tidak aktif:

```text
Mulai, selesai, durasi, acak soal, acak opsi, dan hasil mengikuti default batch.
```

Jika `Gunakan pengaturan khusus` aktif:

```text
Grup boleh memakai jam, durasi, acak soal, acak opsi, dan pengaturan hasil sendiri.
```

---

## 10. Arti Override

Override adalah pengaturan khusus pada satu grup yang mengganti default batch.

Contoh default batch:

```text
Mulai      : 08:00
Selesai    : 10:00
Durasi     : 90 menit
Acak Soal  : ON
Acak Opsi  : ON
Hasil      : Manual
```

Grup tanpa override:

```text
DKV rombel 1, 2
Paket: Desain Grafis
Jam: ikut default 08:00 - 10:00
Durasi: ikut default 90 menit
```

Grup dengan override:

```text
TAV rombel 1, 2, 3
Paket: Dasar Elektronika
Jam: 10:00 - 12:00
Durasi: 90 menit
```

Token tetap sama karena token berada di level batch.

---

## 11. Tabel Grup di Modal

Tabel grup yang sudah ditambahkan:

```text
| No | Jurusan | Rombel | Paket/Mapel       | Mulai | Selesai | Durasi | Acak Soal | Acak Opsi | Hasil  | Override | Aksi |
|----|---------|--------|-------------------|-------|---------|--------|-----------|-----------|--------|----------|------|
| 1  | DKV     | 1,2    | Desain Grafis     | AUTO  | AUTO    | AUTO   | AUTO      | AUTO      | AUTO   | Tidak    | Hapus|
| 2  | TAV     | 1,2,3  | Dasar Elektronika | 10:00 | 12:00   | 90     | ON        | ON        | Manual | Ya       | Hapus|
```

Keterangan:

```text
AUTO = mengikuti default batch
```

---

## 12. Preview Sebelum Submit

Sebelum submit, sistem harus menampilkan hasil expand jadwal.

Contoh preview:

```text
Akan dibuat 5 jadwal:

1. X DKV 1 - Desain Grafis - 08:00-10:00 - token ABC123
2. X DKV 2 - Desain Grafis - 08:00-10:00 - token ABC123
3. X TAV 1 - Dasar Elektronika - 10:00-12:00 - token ABC123
4. X TAV 2 - Dasar Elektronika - 10:00-12:00 - token ABC123
5. X TAV 3 - Dasar Elektronika - 10:00-12:00 - token ABC123
```

Preview wajib agar admin tidak salah submit banyak jadwal.

---

## 13. Token Batch

Rekomendasi token:

```text
1 batch = 1 token bersama
```

Alasan:

1. Lebih mudah diumumkan ke siswa.
2. Panitia cukup menyebut satu token untuk satu sesi batch.
3. Lebih sederhana untuk pengawas.
4. Tetap aman karena backend tetap membatasi berdasarkan kelas, jadwal, waktu, status hadir, user, dan device fingerprint.

Contoh:

```text
Token Batch: ABC123
```

Semua jadwal hasil batch memakai token yang sama:

```text
X DKV 1 -> ABC123
X DKV 2 -> ABC123
X TAV 1 -> ABC123
X TAV 2 -> ABC123
X TAV 3 -> ABC123
```

---

## 14. Keamanan Token Batch

Token sama semua masih aman selama validasi backend tetap dilakukan:

```text
1. User harus login.
2. User harus siswa.
3. Kelas siswa harus sesuai jadwal.
4. Jadwal harus aktif sesuai waktu.
5. Token harus cocok.
6. Status kehadiran harus hadir.
7. Device fingerprint harus valid sesuai aturan.
8. Session user harus sesuai.
```

Jadi siswa dari kelas lain tidak bisa masuk hanya karena tahu token, karena mapping kelas tetap dicek.

---

## 15. Auto Create / Reuse Master Ujian

Master Ujian tetap dipakai di database, tetapi dibuat otomatis.

Saat submit batch, untuk setiap grup:

```text
1. Sistem membaca paket_soal_id.
2. Sistem membaca judul ujian/mapel dari paket atau input batch.
3. Sistem membaca acak_soal, acak_opsi, tampilkan_nilai, hasil_visibilitas.
4. Sistem mencari master ujian yang sudah sama.
5. Jika ada, pakai master lama.
6. Jika tidak ada, buat master ujian baru.
7. Jadwal dibuat memakai master tersebut.
```

Kombinasi yang menentukan master bisa berupa:

```text
paket_soal_id
judul
acak_soal
acak_opsi
tampilkan_nilai_akhir
hasil_visibilitas
tanggal_rilis_hasil
```

Catatan:

- Jika judul master ingin seragam, judul bisa dibuat otomatis dari nama paket/mapel dan nama batch.
- Jika paket sama tetapi pengaturan acak berbeda, master harus berbeda.

---

## 16. Contoh Data Submit Batch

Contoh input konseptual:

```text
nama_batch: Ujian X Sesi 1
tingkat_id: X
gunakan_token: true
token: ABC123
default_mulai: 2026-06-10 08:00
default_selesai: 2026-06-10 10:00
default_durasi: 90
default_acak_soal: true
default_acak_opsi: true
default_hasil_visibilitas: manual

groups:
  - jurusan: DKV
    rombel: [1, 2]
    paket_soal_id: 11
    override: false

  - jurusan: TAV
    rombel: [1, 2, 3]
    paket_soal_id: 15
    override: true
    mulai: 2026-06-10 10:00
    selesai: 2026-06-10 12:00
    durasi: 90
    acak_soal: true
    acak_opsi: true
    hasil_visibilitas: manual
```

Hasil expand:

```text
X DKV 1 -> paket 11 -> 08:00-10:00
X DKV 2 -> paket 11 -> 08:00-10:00
X TAV 1 -> paket 15 -> 10:00-12:00
X TAV 2 -> paket 15 -> 10:00-12:00
X TAV 3 -> paket 15 -> 10:00-12:00
```

---

## 17. Endpoint Backend yang Diusulkan

Endpoint baru:

```text
POST /kelola/data/jadwal-ujian/batch
```

Endpoint tambahan untuk preview validasi:

```text
POST /kelola/data/jadwal-ujian/batch/preview
```

Rekomendasi:

- Preview endpoint mengembalikan hasil expand dan daftar error/warning.
- Submit endpoint melakukan validasi ulang, lalu transaksi insert.
- Jangan percaya hasil preview di frontend saja.

---

## 18. Validasi Wajib

Validasi header batch:

```text
1. nama_batch wajib.
2. tingkat wajib.
3. token wajib jika gunakan_token ON.
4. token hanya A-Z dan 0-9.
5. waktu default mulai wajib.
6. waktu default selesai wajib.
7. default selesai harus setelah default mulai.
8. default durasi minimal 1 menit.
9. default durasi tidak boleh melebihi rentang default waktu.
10. hasil scheduled wajib punya tanggal rilis.
```

Validasi grup:

```text
1. jurusan wajib.
2. minimal satu rombel wajib.
3. paket soal wajib.
4. paket soal harus status ready.
5. semua rombel harus ada di tingkat + jurusan tersebut.
6. jika override ON, waktu mulai dan selesai grup wajib valid.
7. jika override ON, durasi grup tidak boleh melebihi rentang waktu grup.
8. jika hasil scheduled, tanggal rilis hasil wajib.
```

Validasi bentrok:

```text
1. Satu kelas tidak boleh punya dua jadwal aktif yang bentrok waktu.
2. Satu kelas tidak boleh duplicate dalam batch yang sama pada waktu yang sama.
3. Jika kelas sudah punya sesi ujian di jadwal lain, jangan timpa.
```

---

## 19. Bentrok Jadwal

Bentrok jadwal terjadi jika kelas yang sama punya jadwal lain dengan waktu overlap.

Contoh bentrok:

```text
Jadwal lama: X DKV 1, 08:00-10:00
Jadwal baru: X DKV 1, 09:00-11:00
```

Validasi overlap:

```text
jadwal_lama.mulai < jadwal_baru.selesai
DAN
jadwal_lama.selesai > jadwal_baru.mulai
```

Jika bentrok, sistem harus memberi pesan jelas:

```text
X DKV 1 sudah memiliki jadwal MTK 08:00-10:00.
```

---

## 20. Durasi vs Waktu Selesai

Aturan:

```text
Durasi tidak boleh lebih panjang dari rentang waktu mulai-selesai.
```

Contoh valid:

```text
Mulai: 08:00
Selesai: 10:00
Durasi: 90 menit
```

Contoh tidak valid:

```text
Mulai: 08:00
Selesai: 10:00
Durasi: 150 menit
```

Catatan operasional:

Sisa waktu siswa sebaiknya tetap mengikuti aturan:

```text
min(waktu_login + durasi_menit, waktu_selesai jadwal)
```

Jadi siswa yang terlambat login tidak melewati batas akhir jadwal.

---

## 21. Mode Hasil Ujian

Visibilitas hasil yang didukung:

```text
instant   = nilai langsung tampil setelah selesai
manual    = nilai tidak tampil sampai admin membuka
scheduled = nilai tampil pada tanggal rilis
```

Pada batch, default hasil di header bisa dipakai semua grup.

Jika satu grup butuh aturan berbeda, gunakan override.

---

## 22. Perubahan UI yang Dibutuhkan

Perubahan pada halaman jadwal:

1. Ubah tombol `Buat Jadwal Baru` agar membuka modal batch.
2. Tab `Master Ujian` bisa tetap ada untuk mode advanced, tetapi tidak harus jadi flow utama.
3. Tambahkan modal `Buat Jadwal Batch`.
4. Tambahkan form header batch.
5. Tambahkan form tambah grup.
6. Tambahkan tabel grup.
7. Tambahkan preview expand.
8. Tambahkan submit batch.
9. Tambahkan indikator token batch.
10. Tambahkan aksi archive sebagai aksi utama.

---

## 23. Perubahan Backend yang Dibutuhkan

Backend baru:

```text
ScheduleManagementController::previewBatch()
ScheduleManagementController::storeBatch()
```

Service yang disarankan:

```text
App\Services\ScheduleBatchService
```

Tugas service:

```text
1. Validasi header batch.
2. Validasi grup.
3. Expand jurusan + rombel menjadi kelas_aktif_id.
4. Cek bentrok jadwal.
5. Auto create/reuse master ujian.
6. Insert jadwal per kelas.
7. Insert mapping jadwal_ujian_kelas.
8. Return ringkasan jadwal yang dibuat.
```

---

## 24. Struktur Database Opsional

Tahap awal bisa tanpa tabel baru.

Namun agar batch mudah dilacak, disarankan tambah tabel:

```text
jadwal_batches
```

Field:

```text
id
nama_batch
tingkat_id
token
gunakan_token
created_by
created_at
updated_at
```

Lalu di `jadwal_ujians` tambah:

```text
batch_id nullable
```

Manfaat:

1. Jadwal hasil batch bisa dikelompokkan.
2. Bisa tampilkan nama batch di UI.
3. Bisa regenerate token batch bersama.
4. Bisa archive satu batch.
5. Bisa audit lebih rapi.

Jika ingin implementasi cepat, batch_id bisa ditunda.

---

## 25. Mode Implementasi Bertahap

### Tahap 1 - Tanpa tabel baru

- Tambah endpoint batch create.
- Token sama untuk semua jadwal hasil batch.
- Jadwal tetap dibuat per kelas.
- Master otomatis dibuat/reuse.
- UI batch modal dasar.

Kelebihan:

```text
cepat, risiko kecil, tidak banyak migrasi database
```

Kekurangan:

```text
jadwal tidak punya group batch permanen
```

### Tahap 2 - Dengan jadwal_batches

- Tambah tabel `jadwal_batches`.
- Tambah `batch_id` di `jadwal_ujians`.
- UI bisa filter berdasarkan batch.
- Archive/delete bisa per batch.
- Token batch bisa regenerate bersama.

Kelebihan:

```text
rapi untuk operasional sekolah
```

Kekurangan:

```text
butuh migrasi database dan logic tambahan
```

Rekomendasi:

```text
Tahap 1 dulu untuk cepat jalan.
Tahap 2 setelah flow batch stabil.
```

---

## 26. Revisi Delete dan Archive

Temuan pada flow lama:

```text
Delete jadwal bersifat destruktif karena menghapus sesi, jawaban, event, audit, dan mapping jadwal.
```

Rekomendasi:

1. Aksi utama di UI adalah `Arsipkan`, bukan `Hapus`.
2. Delete permanen hanya untuk SuperAdmin.
3. Delete permanen hanya jika jadwal belum punya sesi, atau perlu konfirmasi ekstra.
4. Mass delete diganti menjadi mass archive.
5. Jika tetap ada delete, beri label jelas `Hapus Permanen`.

UI rekomendasi:

```text
Arsipkan
Edit
Generate Token
Hapus Permanen (SuperAdmin only)
```

---

## 27. Revisi Mass Delete Bug

Frontend saat ini memanggil endpoint:

```text
POST /kelola/data/jadwal-ujian/mass-delete
```

Sedangkan route backend yang tersedia menggunakan:

```text
DELETE /kelola/data/jadwal-ujian
```

Rekomendasi:

1. Samakan frontend dan backend.
2. Lebih baik ubah menjadi `POST /kelola/data/jadwal-ujian/bulk-archive` untuk aksi aman.
3. Untuk delete permanen, gunakan endpoint khusus SuperAdmin.

---

## 28. Contoh Flow UI Final

```text
Admin klik Buat Jadwal Baru

Modal terbuka:
Header:
- Nama Batch: Ujian X Sesi 1
- Tingkat: X
- Token: ABC123
- Mulai Default: 08:00
- Selesai Default: 10:00
- Durasi Default: 90
- Acak Soal Default: ON
- Acak Opsi Default: ON
- Hasil Default: Manual

Tambah Grup 1:
- Jurusan: DKV
- Rombel: 1,2
- Paket: Desain Grafis
- Override: OFF
- Klik Tambah

Tambah Grup 2:
- Jurusan: TAV
- Rombel: 1,2,3
- Paket: Dasar Elektronika
- Override: OFF
- Klik Tambah

Preview:
- X DKV 1 Desain Grafis 08:00-10:00 ABC123
- X DKV 2 Desain Grafis 08:00-10:00 ABC123
- X TAV 1 Dasar Elektronika 08:00-10:00 ABC123
- X TAV 2 Dasar Elektronika 08:00-10:00 ABC123
- X TAV 3 Dasar Elektronika 08:00-10:00 ABC123

Submit Batch
```

---

## 29. Contoh Flow dengan Override

```text
Header Batch:
- Tingkat: X
- Token: ABC123
- Default Jam: 08:00-10:00
- Default Durasi: 90

Grup 1:
- Jurusan: DKV
- Rombel: 1,2
- Paket: Desain Grafis
- Override: OFF
- Hasil: 08:00-10:00

Grup 2:
- Jurusan: TAV
- Rombel: 1,2,3
- Paket: Dasar Elektronika
- Override: ON
- Jam: 10:00-12:00
- Durasi: 90
- Hasil: 10:00-12:00
```

Token tetap:

```text
ABC123
```

---

## 30. Dampak ke Dashboard Siswa

Dashboard siswa tidak perlu berubah besar.

Siswa tetap melihat jadwal berdasarkan:

```text
kelas siswa
jadwal hari ini
waktu mulai dan selesai
status sesi
```

Karena hasil batch tetap membuat jadwal per kelas, query dashboard siswa tetap cocok.

---

## 31. Dampak ke Start Ujian

Start ujian tetap sama:

```text
POST /ujian/{jadwal_hash}/mulai
```

Validasi tetap:

```text
kelas siswa harus match jadwal
token harus match jadwal
waktu harus aktif
status hadir harus valid
device fingerprint dicek
```

Karena token batch disalin ke masing-masing `jadwal_ujians`, flow start tidak perlu banyak berubah.

---

## 32. Dampak ke Monitoring

Karena jadwal tetap per kelas, monitoring bisa tetap jelas:

```text
X DKV 1 - aktif
X DKV 2 - aktif
X TAV 1 - aktif
X TAV 2 - aktif
X TAV 3 - aktif
```

Jika nanti ada `batch_id`, monitoring bisa ditambah filter:

```text
Batch: Ujian X Sesi 1
```

---

## 33. Prioritas Implementasi

Prioritas tertinggi:

```text
1. Modal batch multi-grup.
2. Endpoint batch preview.
3. Endpoint batch submit.
4. Auto create/reuse master ujian.
5. Token batch sama semua.
6. Validasi bentrok jadwal.
7. Ubah delete default menjadi archive.
```

Prioritas menengah:

```text
1. Tabel jadwal_batches.
2. batch_id di jadwal_ujians.
3. Filter jadwal berdasarkan batch.
4. Regenerate token satu batch.
5. Archive satu batch.
```

Prioritas rendah:

```text
1. Import jadwal batch dari Excel.
2. Template jadwal mingguan.
3. Copy batch dari hari sebelumnya.
```

---

## 34. Kesimpulan

Revisi yang paling cocok untuk kebutuhan operasional:

```text
Batch Jadwal Multi-Grup dengan satu token bersama.
```

Model akhir:

```text
1 batch
  -> 1 token
  -> banyak grup
  -> tiap grup bisa beda jurusan, rombel, paket/mapel, jam, dan opsi
  -> hasil akhir tetap jadwal per kelas
```

Master Ujian tetap ada, tetapi dibuat/reuse otomatis agar admin tidak perlu membuat manual.

Flow yang diinginkan:

```text
Buat Jadwal Baru
  -> pilih tingkat
  -> token batch
  -> tambah grup DKV rombel 1,2 mapel A
  -> tambah grup TAV rombel 1,2,3 mapel B
  -> preview
  -> submit
```

Dengan desain ini, panitia dapat membuat banyak jadwal dalam satu kali jalan tanpa kehilangan kontrol dan keamanan backend.
