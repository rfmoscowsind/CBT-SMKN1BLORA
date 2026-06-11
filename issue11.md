# Rencana Penulisan `issue11.md` Audit Performa Ujian CBT

## Ringkasan
- Buat file `issue11.md` berisi temuan audit bottleneck/risiko ujian tanpa mengubah kode aplikasi.
- Format tiap temuan: `Severity`, `Area`, `Gejala/Risiko`, `Penyebab`, `Dampak saat ujian`, `Solusi disarankan`, `Verifikasi`.
- Basis audit sudah dicek dari jalur ujian web/API, Redis, queue worker, Octane, nginx, monitoring, dan metadata index PostgreSQL production.

## Temuan Yang Akan Ditulis
- **Critical: Jawaban offline lokal belum disinkronkan otomatis**
  - Penyebab: `ExamInterface.vue` menyimpan jawaban ke `localStorage`, tetapi tidak memanggil endpoint `/ujian/sesi/{id}/sync` saat online kembali atau sebelum submit.
  - Dampak: siswa bisa merasa jawaban tersimpan, tetapi jawaban yang gagal POST ke server bisa hilang saat submit.
  - Solusi: tambah pending queue lokal, sync otomatis saat `online`, sebelum pindah soal/submit, dan blok submit jika pending belum terkirim.

- **High: Redis belum durable untuk session dan pending jawaban**
  - Penyebab: Redis dipakai untuk `SESSION_DRIVER`, cache, queue, dan `queue_jawaban:*`, tetapi `aof_enabled=0`.
  - Dampak: restart/crash Redis saat ujian bisa logout massal dan menghilangkan jawaban pending yang belum dipersist job.
  - Solusi: aktifkan AOF `appendonly yes` dengan policy aman, backup Redis, dan monitoring key `queue_jawaban:*`.

- **High: Queue jawaban hanya satu worker**
  - Penyebab: service `cbt-worker.service` hanya menjalankan satu `queue:work redis --queue=answers,default`.
  - Dampak: saat banyak siswa menjawab serentak, job persist jawaban bisa antre; submit bisa melambat karena harus flush pending.
  - Solusi: jalankan beberapa worker khusus `answers`, pisahkan `default`, set `--max-jobs/--max-time`, dan monitor queue depth.

- **High: Setiap jawaban membuat write audit**
  - Penyebab: `persistPreparedAnswer()` menyimpan/UPSERT jawaban lalu insert `audit_logs` untuk setiap jawaban.
  - Dampak: beban write DB berlipat pada puncak ujian.
  - Solusi: audit jawaban dibuat sampling/ringkas/batch, atau audit hanya event penting dan error.

- **Medium: Monitoring sessions polling query penuh**
  - Penyebab: `/monitoring/sessions` mengambil semua sesi tanpa filter/limit, lalu dashboard pengawas polling tiap 3 detik dan panitia tiap 5 detik.
  - Dampak: saat data sesi banyak, monitoring bisa membebani DB utama.
  - Solusi: filter hanya sesi aktif/terkunci/recent, cache 2-5 detik di Redis, atau pakai data `cbt:radar:live`.

- **Medium: Duplikasi index di tabel hot path**
  - Penyebab: index unik/komposit ganda ditemukan pada `sesi_ujians`, `sesi_ujian_soals`, `jawaban_siswas`, dan `bank_soals`.
  - Dampak: setiap insert/update jawaban/sesi membayar biaya maintenance index ekstra.
  - Solusi: inventaris index duplikat, hapus yang redundant dengan `DROP INDEX CONCURRENTLY` setelah validasi constraint.

- **Medium: Config/routes production belum cached**
  - Penyebab: `php artisan about` menunjukkan `Config NOT CACHED` dan `Routes NOT CACHED`.
  - Dampak: tidak sebesar PHP-FPM karena Octane warm, tetapi restart worker/queue dan recovery lebih lambat/kurang deterministik.
  - Solusi: setelah deploy, jalankan `php artisan config:cache` dan `route:cache` jika semua route closure sudah dipindah atau dinyatakan kompatibel.

- **Medium: Container cache berpotensi stale di Octane**
  - Penyebab: beberapa data ujian disimpan via `app()->instance(...)` dengan asumsi request-scoped.
  - Dampak: di long-running worker, data jadwal/soal bisa stale setelah reset/edit jadwal jika tidak terflush.
  - Solusi: ganti ke request object/local variable atau Redis/cache TTL eksplisit, hindari binding dinamis ke container Octane.

- **Low/Medium: Fallback Redis config tidak selaras**
  - Penyebab: `.env` sekarang `REDIS_CLIENT=phpredis`, tetapi fallback `config/database.php` masih `predis`.
  - Dampak: jika `.env` tidak terbaca/cache salah, driver Redis bisa berubah diam-diam.
  - Solusi: samakan fallback ke `phpredis` jika keputusan final memang memakai PhpRedis.

## Test Plan Setelah `issue11.md` Ditulis
- Pastikan hanya `issue11.md` yang dibuat/berubah.
- Cantumkan bukti audit ringkas: service Octane/queue aktif, Redis AOF off, queue worker tunggal, polling interval, endpoint `/sync` tidak dipakai frontend, dan daftar index duplikat.
- Tidak menjalankan migrasi, tidak mengubah nginx/systemd, tidak mengubah kode.

## Asumsi
- Target audit adalah kesiapan ujian skala besar sekitar 1500 siswa, mengikuti komentar migration/performance yang ada.
- File laporan memakai bahasa Indonesia.
- Prioritas laporan: risiko kehilangan jawaban dan bottleneck saat ujian lebih penting daripada kosmetik/dashboard.
