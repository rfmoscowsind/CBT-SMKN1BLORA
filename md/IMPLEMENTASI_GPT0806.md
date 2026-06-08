# Implementasi Rekomendasi GPT0806

Tanggal: 2026-06-08

Dokumen ini mencatat rekomendasi `gpt0806.md` yang sudah diimplementasikan ke kode.

## P0 Data Safety

- Sesi ujian dibuat lebih aman dari race condition.
  - `ExamService::start()` sudah memakai transaction, row lock, dan fallback duplicate-key PostgreSQL.
  - Sesi berstatus `reset` atau `invalidated` bisa dibangun ulang tanpa membuat row ganda.
- Submit ujian sudah lock-safe.
  - `ExamService::submit()` mengunci row sesi dengan `lockForUpdate()`.
- Persist jawaban Redis ke database sudah timestamp-safe.
  - `flushOne()` dan `flushAll()` memakai PostgreSQL `ON CONFLICT ... WHERE server_updated_at`.
  - Jawaban lama dari worker paralel tidak boleh menimpa jawaban baru.
- Queue persist jawaban sudah unique per sesi dan soal.
  - `PersistAnswerSnapshot` memakai `ShouldBeUniqueUntilProcessing`.
- Reset session tidak hard delete sesi.
  - Reset menandai `sesi_ujians.status = reset`.
  - Pending Redis `queue_jawaban:{sessionId}` dibersihkan.
  - Jawaban dan susunan soal sesi lama dibersihkan sebelum sesi bisa dibangun ulang.
- Migration safety index ditambahkan.
  - `2026_06_08_100000_add_gpt0806_safety_indexes.php`.
  - Termasuk unique index sesi, jawaban, soal sesi, urutan soal, dan index report/monitoring.

## P1 Stabilitas dan Performa

- API ujian disamakan dengan Web untuk pending answer Redis.
  - `ApiController::singleQuestionPayload()` membaca pending answer dari Redis.
  - `save()` dan `sync()` API mendukung flag `ragu`.
- Ping API dipindah ke Redis-first.
  - Online status memakai `cbt:session:online:{id}` TTL 45 detik.
  - Update DB `last_seen_at` dibatasi maksimal 1 kali per 60 detik.
- Report aggregate tidak lagi global.
  - `ReportService::rowsForClass()` memfilter jawaban berdasarkan `jadwal_ujian_id`.
- Arsip hasil memakai range waktu WIB ke UTC.
  - Menghindari `whereYear()` yang kurang ramah index dan rawan beda tahun UTC/WIB.
- Download PDF tidak ditandai sukses sebelum PDF dibuat.
  - PDF object dibuat dulu, baru `hasil_ujian_unduhans` dicatat.
- Manual grading dibuat flush-safe.
  - `ManageController::grade()` dan `OperationsApiController::grade()` hanya menerima sesi `selesai`.
  - Pending Redis diflush sebelum nilai manual dihitung.

## Hardening

- `ImageService` memvalidasi dimensi gambar.
  - Maksimal 6.000.000 pixel sebelum decode ke bitmap.
- `IdCodec` mendukung salt stabil khusus.
  - `ID_CODEC_SALT` ditambahkan ke config/env.
  - Fallback tetap ke `APP_KEY` agar kompatibel saat env belum diset.
- `.env.production.example` ditambahkan.
  - Redis dipakai untuk cache, session, dan queue.
- Hard delete user dengan riwayat ujian dicegah.
  - Siswa dengan riwayat ujian dinonaktifkan via `status_kehadiran = alpha`.
  - Staff/user legacy dengan riwayat ujian ditolak untuk dihapus.
- Paket `ready` divalidasi lebih ketat.
  - Pertanyaan kosong, placeholder, bobot nol, opsi kosong, dan kunci PG tidak valid akan ditolak.
- Import spreadsheet dibatasi.
  - Maksimal file 2 MB.
  - Maksimal 2000 baris data per import.

## Catatan Operasional

- Jalankan migration baru di server sebelum ujian massal.
- Jika unique index gagal dibuat karena data duplikat lama, bersihkan duplikasi data dulu dan ulangi migration.
- Set `ID_CODEC_SALT` di production sebelum rotasi `APP_KEY`. Jangan mengganti salt setelah link ujian aktif.
- Worker queue `answers` tetap wajib aktif.
