# IMPLANTMINIIO - Rencana Implementasi MinIO untuk Media CBT

Dokumen ini adalah mode plan untuk offload file media, terutama gambar soal, dari storage lokal Laravel ke MinIO/S3 compatible storage.

Tanggal: 2026-06-08
Repo: `rfmoscowsind/CBT-SMKN1BLORA`

> Catatan: dokumen ini belum mengubah kode aplikasi. Ini adalah panduan deploy, arsitektur, dan checklist pemeriksaan ulang setelah MinIO dipasang.

---

## 1. Tujuan

Implementasi MinIO bertujuan untuk:

- memindahkan gambar soal dari storage lokal web server ke object storage,
- mengurangi beban web server saat siswa membuka gambar soal,
- membuat media tetap konsisten saat nanti web server lebih dari satu node,
- memudahkan cache media via Cloudflare,
- memudahkan backup dan migrasi media.

Target media awal:

```text
gambar soal
```

Target media lanjutan:

```text
gambar opsi jika nanti ada
file import/export
template Excel
PDF hasil ujian
arsip laporan
```

---

## 2. Kondisi Repo Saat Ini

### 2.1 Upload gambar soal

Upload gambar soal masuk melalui `QuestionBankManagementController`.

Saat tambah soal baru:

```text
bank_soals.gambar_url = ImageService::webp(file gambar)
```

Saat update soal:

```text
jika ada file gambar baru -> ImageService::webp(file gambar)
jika tidak ada gambar baru -> pakai gambar_url lama
```

### 2.2 Penyimpanan gambar saat ini

`ImageService` saat ini:

```text
1. validasi gambar
2. cek resolusi maksimal
3. convert ke webp
4. simpan ke storage/app/public/soal-images
5. return URL /storage/soal-images/{uuid}.webp
```

Maka alur sekarang:

```text
Upload gambar soal
  -> convert webp lokal
  -> simpan di storage/app/public/soal-images
  -> DB menyimpan /storage/soal-images/xxx.webp
  -> browser siswa load dari web server Laravel/Nginx
```

### 2.3 Config S3 sudah tersedia

`config/filesystems.php` sudah punya disk `s3` dengan env:

```env
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=
AWS_BUCKET=
AWS_URL=
AWS_ENDPOINT=
AWS_USE_PATH_STYLE_ENDPOINT=
```

Default filesystem juga bisa dikontrol dengan:

```env
FILESYSTEM_DISK=
```

### 2.4 Dependency S3 perlu dicek saat implementasi

Di `composer.json`, belum terlihat dependency:

```text
league/flysystem-aws-s3-v3
```

Laravel biasanya membutuhkan adapter ini agar disk `s3` bisa dipakai.

---

## 3. Arsitektur yang Disarankan

### 3.1 Alur sederhana

```text
Guru/Admin upload gambar soal
        |
Laravel App
        |
Convert ke WebP
        |
Upload ke MinIO bucket
        |
Simpan URL/path ke bank_soals.gambar_url
        |
Siswa membuka soal
        |
Browser load gambar dari media domain / Cloudflare / MinIO proxy
```

### 3.2 Komponen

```text
Web/App CBT      : Laravel + PHP-FPM
DB               : PostgreSQL via PgBouncer
Cache/Queue      : Redis
Object Storage   : MinIO
CDN/Proxy Media  : Cloudflare + Nginx reverse proxy
```

### 3.3 Topologi media public

```text
Browser siswa
   |
Cloudflare
   |
media.domain
   |
Nginx reverse proxy
   |
MinIO
```

Contoh domain:

```text
media.madnnet.my.id
media.smkn1blora.sch.id
```

---

## 4. Opsi Akses Media

## Opsi A - Public media via URL CDN

URL gambar yang disimpan di DB:

```text
https://media.domain/soal-images/{uuid}.webp
```

Kelebihan:

- cepat,
- sederhana,
- mudah dicache Cloudflare,
- tidak membebani web app,
- cocok untuk gambar soal yang memang tampil ke siswa.

Kekurangan:

- siapa pun yang punya URL bisa membuka gambar,
- URL harus random dan tidak mudah ditebak.

Rekomendasi awal:

```text
Gunakan Opsi A dulu.
```

Alasannya: paling cocok untuk performa CBT 1500 siswa.

---

## Opsi B - Private bucket + signed URL

URL gambar dibuat sementara oleh aplikasi.

Kelebihan:

- akses lebih terbatas,
- URL bisa expired,
- cocok untuk file sensitif.

Kekurangan:

- lebih kompleks,
- caching Cloudflare tidak seefektif public URL,
- app harus generate signed URL,
- latency lebih tinggi.

Rekomendasi:

```text
Pakai nanti kalau benar-benar butuh proteksi ekstra.
```

---

## 5. Struktur Bucket/Prefix

Nama bucket awal:

```text
cbt-media
```

Struktur prefix:

```text
cbt-media/
  soal-images/
    {uuid}.webp
  imports/
  exports/
  reports/
  temp/
```

Jika ingin lebih rapi per paket:

```text
cbt-media/
  soal-images/
    paket-{paket_id}/
      {uuid}.webp
```

Rekomendasi awal:

```text
soal-images/{uuid}.webp
```

Agar simpel dan tidak tergantung perubahan paket.

---

## 6. ENV Laravel yang Direncanakan

Contoh `.env` untuk MinIO:

```env
FILESYSTEM_DISK=s3

AWS_ACCESS_KEY_ID=cbt_app
AWS_SECRET_ACCESS_KEY=isi_secret_kuat
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=cbt-media
AWS_ENDPOINT=http://IP-MINIO:9000
AWS_URL=https://media.domain
AWS_USE_PATH_STYLE_ENDPOINT=true
```

Catatan:

- `AWS_ENDPOINT` digunakan Laravel untuk upload ke MinIO.
- `AWS_URL` digunakan untuk membentuk URL publik media.
- Untuk MinIO, `AWS_USE_PATH_STYLE_ENDPOINT=true` biasanya wajib.

---

## 7. Rencana Deploy MinIO

### 7.1 Mode awal single node

Cukup untuk tahap awal CBT:

```text
1 server MinIO
1 disk/data path dedicated
backup rutin ke disk lain / server lain
```

Contoh service:

```text
MinIO API     : port 9000
MinIO Console : port 9001
```

### 7.2 User dan policy

Buat user khusus aplikasi:

```text
user: cbt_app
akses: read/write hanya ke bucket cbt-media
```

Jangan gunakan root access key MinIO di `.env` Laravel.

### 7.3 Bucket policy

Untuk Opsi A public media:

```text
read public untuk prefix soal-images/*
write hanya user cbt_app
```

Untuk file sensitif:

```text
imports/* private
exports/* private
reports/* private atau signed URL
```

---

## 8. Reverse Proxy Media

Rekomendasi pakai Nginx sebagai proxy di depan MinIO.

Alur:

```text
https://media.domain/soal-images/xxx.webp
  -> Nginx
  -> MinIO bucket cbt-media/soal-images/xxx.webp
```

Tujuan:

- TLS lebih mudah,
- bisa diberi header cache,
- bisa dilindungi Cloudflare,
- endpoint MinIO asli tidak langsung diekspos bebas.

Header cache untuk gambar soal:

```text
Cache-Control: public, max-age=31536000, immutable
```

Karena nama file UUID, cache panjang aman.

---

## 9. Cloudflare Cache Rule

Cache yang disarankan:

```text
media.domain/soal-images/*
```

Mode:

```text
Cache Everything
Edge Cache TTL panjang
Browser Cache TTL panjang
```

Jangan cache endpoint dynamic CBT:

```text
/login
/dashboard
/ujian/*
/api/*
```

---

## 10. Perubahan Kode yang Direncanakan Nanti

Belum dilakukan dalam dokumen ini.

Area utama yang akan berubah:

```text
app/Services/ImageService.php
```

Perubahan konsep:

Dari:

```text
convert webp -> simpan lokal -> return /storage/soal-images/uuid.webp
```

Menjadi:

```text
convert webp -> upload ke disk s3/minio -> return URL media atau path media
```

Controller idealnya tidak perlu banyak berubah karena controller hanya menyimpan string hasil `ImageService::webp()` ke `bank_soals.gambar_url`.

---

## 11. Dependency yang Perlu Ditambahkan Saat Implementasi

Kemungkinan perlu:

```bash
composer require league/flysystem-aws-s3-v3
```

Setelah itu:

```bash
php artisan optimize:clear
php artisan config:cache
sudo systemctl reload php8.3-fpm
```

Catatan: jangan jalankan perubahan ini di production saat ujian sedang berjalan.

---

## 12. Rencana Migrasi Gambar Lama

Karena gambar lama saat ini disimpan lokal dengan format URL:

```text
/storage/soal-images/{file}.webp
```

Rencana migrasi:

```text
1. Ambil semua bank_soals yang gambar_url diawali /storage/soal-images/
2. Cari file lokal di storage/app/public/soal-images/{file}.webp
3. Upload ke MinIO prefix soal-images/{file}.webp atau soal-images/{uuid}.webp
4. Update bank_soals.gambar_url ke URL baru
5. Test preview paket soal
6. Test halaman ujian siswa
7. Backup file lokal lama
8. Setelah aman, baru boleh bersihkan storage lokal
```

Jangan hapus gambar lokal sebelum semua data berhasil dimigrasi dan diuji.

---

## 13. Risiko dan Mitigasi

### Risiko 1 - URL gambar lama rusak

Mitigasi:

```text
backup DB sebelum migrasi
backup storage/app/public/soal-images
migrasi bertahap
uji 1 paket dulu
```

### Risiko 2 - MinIO down saat ujian

Mitigasi:

```text
Cloudflare cache aktif
MinIO pakai storage stabil
monitor service MinIO
backup/restart plan jelas
```

### Risiko 3 - Bucket public terlalu luas

Mitigasi:

```text
public read hanya untuk soal-images/*
imports/reports tetap private
jangan simpan kunci jawaban di bucket public
```

### Risiko 4 - Gambar tidak ter-cache

Mitigasi:

```text
pakai filename UUID
set Cache-Control panjang
atur Cloudflare cache rule
```

### Risiko 5 - URL media berbeda antara lokal dan production

Mitigasi:

```text
gunakan AWS_URL dari .env
jangan hardcode domain di kode
```

---

## 14. Checklist Setelah MinIO Deploy

### Cek MinIO

```text
[ ] MinIO service running
[ ] Console bisa login
[ ] Bucket cbt-media ada
[ ] User cbt_app ada
[ ] Policy user terbatas ke bucket cbt-media
[ ] Prefix soal-images bisa read public jika pakai Opsi A
```

### Cek Laravel ENV

```text
[ ] FILESYSTEM_DISK=s3
[ ] AWS_ACCESS_KEY_ID benar
[ ] AWS_SECRET_ACCESS_KEY benar
[ ] AWS_BUCKET=cbt-media
[ ] AWS_ENDPOINT mengarah ke MinIO API
[ ] AWS_URL mengarah ke media domain
[ ] AWS_USE_PATH_STYLE_ENDPOINT=true
[ ] config cache sudah refresh
```

### Cek upload baru

```text
[ ] Upload gambar soal baru dari dashboard
[ ] File muncul di MinIO
[ ] bank_soals.gambar_url berubah ke URL media
[ ] Preview paket soal menampilkan gambar
[ ] Halaman ujian siswa menampilkan gambar
[ ] Gambar bisa di-cache Cloudflare
```

### Cek migrasi lama

```text
[ ] Gambar lama masih bisa dibuka sebelum migrasi
[ ] Migrasi 1 paket berhasil
[ ] Semua gambar paket tampil di preview
[ ] Semua gambar paket tampil di halaman ujian
[ ] Tidak ada 404 untuk /storage/soal-images lama atau URL baru
```

---

## 15. Checklist Load Test Media

Test dengan paket yang memiliki gambar.

Metric:

```text
TTFB gambar
cache hit Cloudflare
bandwidth origin media
CPU web CBT
CPU MinIO
latency halaman ujian
jumlah request gambar per siswa
```

Target:

```text
Gambar dari cache Cloudflare setelah hit pertama
Web CBT tidak serving gambar lokal lagi
Halaman ujian tetap < 500 ms untuk JSON soal
Gambar tidak menyebabkan 502/504
```

---

## 16. Rekomendasi Final

Untuk tahap awal:

```text
Gunakan MinIO untuk gambar soal saja.
Pakai public media URL via Cloudflare.
Simpan URL media di bank_soals.gambar_url.
Jangan ubah flow exam dulu.
Jangan pakai signed URL dulu kecuali diperlukan.
```

Tahap setelah stabil:

```text
Migrasikan file export/report ke MinIO.
Pisahkan prefix public dan private.
Buat command migrasi media lama.
Tambahkan health check MinIO di monitoring.
```

---

## 17. Catatan untuk Pemeriksaan Lanjutan

Setelah MinIO sudah dideploy, pemeriksaan yang perlu dilakukan:

```text
1. cek .env production
2. cek config/filesystems.php terbaca benar
3. test upload gambar soal baru
4. cek object muncul di MinIO
5. cek gambar_url di DB
6. cek Network DevTools halaman ujian
7. cek apakah gambar lewat media domain, bukan /storage lokal
8. cek cache Cloudflare
9. cek error log Laravel/Nginx/MinIO
```

Dokumen ini bisa dijadikan acuan sebelum implementasi kode final.
