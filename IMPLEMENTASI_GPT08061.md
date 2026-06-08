# Implementasi GPT08061 - Resume Session dan Toggle Device Lock

Tanggal implementasi: 2026-06-08

## Ringkasan

Implementasi ini memisahkan dua aksi admin yang sebelumnya rawan tertukar:

1. `Buka Sesi / Lanjutkan`
   - Non-destruktif.
   - Tidak menghapus jawaban siswa.
   - Tidak menghapus susunan soal.
   - Tidak mengubah `waktu_login`, sehingga sisa waktu tetap dihitung dari waktu mulai awal.
   - Cocok untuk siswa terkunci, pindah perangkat, browser error, atau sesi nyangkut.

2. `Reset Ulang dari Nol`
   - Destruktif.
   - Menghapus jawaban, antrean jawaban Redis, susunan soal sesi, dan nilai sementara.
   - Cocok hanya jika pengawas/admin memang ingin siswa mulai ujian ulang dari awal.

## Database

Migration baru:

`database/migrations/2026_06_08_110000_add_device_lock_settings_and_history.php`

Tabel baru:

- `app_settings`
  - Menyimpan toggle global `device_lock_enabled`.
  - Default membaca `DEVICE_LOCK_ENABLED`, fallback `true`.
  - Menyimpan `updated_by` dan `updated_at`.

- `device_fingerprint_histories`
  - Mencatat fingerprint historis per siswa, sesi, jadwal, IP, user agent, dan mode lock.
  - Tetap diisi saat Device Lock OFF untuk kebutuhan audit.
  - Index disiapkan untuk pencarian berdasarkan user, NIS, NISN, sesi, fingerprint, dan waktu.

## Backend

### Device Lock Toggle

Lokasi:

- `app/Http/Controllers/Concerns/HandlesDeviceFingerprints.php`
- `app/Http/Controllers/ManageController.php`
- `routes/web.php`

Endpoint:

```text
POST /kelola/data/device-lock/toggle
```

Hak akses:

- SuperAdmin saja.

Perilaku:

- ON: perubahan perangkat siswa diblokir seperti biasa.
- OFF: perubahan perangkat tidak diblokir, tetapi fingerprint historis tetap dicatat.
- Perubahan toggle dicatat ke `audit_logs` dengan action `device_lock_setting_changed`.

### Fingerprint History

Saat siswa mengirim fingerprint valid, sistem mencatat histori ke `device_fingerprint_histories`.

Dedup:

- Event serupa dari user, fingerprint, sesi, IP, user agent, action, dan mode lock yang sama tidak dicatat berulang dalam 60 detik.

Action yang digunakan:

- `checked`: Device Lock ON dan fingerprint diperiksa normal.
- `audit_only`: Device Lock OFF, fingerprint dicatat tanpa blocking.

### Buka Sesi / Lanjutkan

Endpoint web:

```text
POST /kelola/sesi/{id}/unlock-resume
```

Endpoint API pengawas:

```text
POST /api/v1/pengawas/sesi/{id}/unlock-resume
```

Hak akses:

- SuperAdmin
- Admin
- Pengawas
- Permission `monitor-exams`

Perilaku:

- Mengubah status sesi ke `aktif`.
- Membersihkan fingerprint dan flag lock user/sesi.
- Mengosongkan `waktu_submit`.
- Mengosongkan `last_seen_at`.
- Tidak menghapus `jawaban_siswas`.
- Tidak menghapus `sesi_ujian_soals`.
- Tidak menghapus `queue_jawaban:{sesi_id}`.
- Tidak mengubah `waktu_login`.
- Menghapus key online/ping Redis agar sesi refresh dari state terbaru.
- Mencatat event `session_resume_unlock` atau `api_session_resume_unlock`.

Sesi yang ditolak:

- `selesai`: tidak perlu dibuka.
- `reset`: sudah masuk jalur mulai ulang dari nol.

### Pending Answer Saat Terkunci

`app/Services/ExamService.php` sekarang menganggap status berikut tetap flushable:

```php
['aktif', 'selesai', 'terkunci']
```

Dampaknya:

- Pending answer Redis tidak dibuang hanya karena sesi sementara terkunci.
- Pending answer hanya hilang pada jalur reset destruktif.

## Frontend

Lokasi:

`resources/js/pages/Management/DeviceFingerprints.vue`

Perubahan:

- Menambah card toggle `Device Lock ON/OFF`.
- Menampilkan warning saat Device Lock OFF.
- Tombol `Lanjutkan` untuk sesi terkunci kini memanggil endpoint non-destruktif.
- Tombol `Buka Sesi` tersedia untuk sesi aktif yang perlu dibuka ulang tanpa hapus jawaban.
- Tombol `Reset Nol` / `Reset Ulang dari Nol` dibuat eksplisit dan bernada destruktif.
- Modal detail fingerprint juga menyediakan tombol `Buka Sesi / Lanjutkan`.
- Counter `Sesi Aktif` hanya menghitung status `aktif` dan `terkunci`, bukan semua sesi yang punya `sesi_id`.

## Catatan Operasional

- Gunakan `Buka Sesi / Lanjutkan` untuk siswa yang hanya terkunci perangkat atau perlu lanjut ujian.
- Gunakan `Reset Ulang dari Nol` hanya setelah pengawas/admin yakin jawaban siswa boleh dihapus.
- Saat Device Lock OFF, siswa bisa pindah perangkat tanpa blokir, tetapi histori fingerprint tetap dapat diaudit.
- Toggle Device Lock disimpan di database, bukan hanya `.env`, sehingga bisa diubah dari dashboard tanpa redeploy.
