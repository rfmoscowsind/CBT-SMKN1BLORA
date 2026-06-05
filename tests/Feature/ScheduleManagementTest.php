<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class ScheduleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_schedule_page_reads_all_ready_question_packages_and_creates_schedule(): void
    {
        $this->post('/login', [
            'username' => 'superadmin',
            'password' => 'superadmin123',
        ]);

        $packageId = DB::table('paket_soals')->insertGetId([
            'mata_pelajaran_id' => 1,
            'pembuat_user_id' => 3,
            'judul' => 'Paket Kedua',
            'status' => 'ready',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('bank_soals')->insert([
            'paket_soal_id' => $packageId,
            'urutan' => 1,
            'tipe_soal' => 'ISIAN',
            'pertanyaan' => 'Soal paket kedua',
            'bobot_nilai' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/kelola/data/jadwal-ujian')
            ->assertOk()
            ->assertJsonCount(2, 'data.packages')
            ->assertJsonFragment([
                'judul' => 'Latihan Matematika',
                'jumlah_soal' => 4,
            ])
            ->assertJsonFragment([
                'judul' => 'Paket Kedua',
                'jumlah_soal' => 1,
            ]);

        $masterId = $this->postJson('/kelola/data/master-ujian', [
            'judul' => 'Ujian Paket Kedua',
            'paket_soal_id' => $packageId,
            'acak_soal' => false,
            'acak_opsi' => false,
            'tampilkan_nilai_akhir' => false,
            'hasil_visibilitas' => 'manual',
        ])->assertCreated()->json('data.id');

        $scheduleId = $this->postJson('/kelola/data/jadwal-ujian', [
            'master_ujian_id' => $masterId,
            'kelas_aktif_id' => 1,
            'waktu_mulai' => now()->addHour()->toDateTimeString(),
            'waktu_selesai' => now()->addHours(2)->toDateTimeString(),
            'durasi_menit' => 60,
        ])->assertCreated()->assertJsonStructure(['data' => ['id', 'token']])->json('data.id');

        $this->assertDatabaseHas('jadwal_ujian_kelas', [
            'jadwal_ujian_id' => $scheduleId,
            'kelas_aktif_id' => 1,
        ]);
    }

    public function test_schedule_form_saves_wib_as_utc_and_displays_wib(): void
    {
        Date::setTestNow('2026-06-02 02:21:00');
        $this->post('/login', [
            'username' => 'superadmin',
            'password' => 'superadmin123',
        ]);

        $scheduleId = $this->postJson('/kelola/data/jadwal-ujian', [
            'master_ujian_id' => 1,
            'kelas_aktif_id' => 1,
            'waktu_mulai' => '2026-06-02T09:20',
            'waktu_selesai' => '2026-06-02T13:20',
            'durasi_menit' => 60,
        ])->assertCreated()->json('data.id');

        $this->assertDatabaseHas('jadwal_ujians', [
            'id' => $scheduleId,
            'waktu_mulai' => '2026-06-02 02:20:00',
            'waktu_selesai' => '2026-06-02 06:20:00',
        ]);

        $this->getJson('/kelola/data/jadwal-ujian')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $scheduleId,
                'waktu_mulai' => '2026-06-02 09:20:00',
                'waktu_selesai' => '2026-06-02 13:20:00',
            ]);
    }

    public function test_admin_can_edit_schedule_without_regenerating_token(): void
    {
        $this->post('/login', [
            'username' => 'admin',
            'password' => 'admin123',
        ]);
        $token = DB::table('jadwal_ujians')->where('id', 1)->value('token');

        $this->putJson('/kelola/data/jadwal-ujian/1', [
            'master_ujian_id' => 1,
            'kelas_aktif_id' => 1,
            'waktu_mulai' => '2026-06-03T08:00',
            'waktu_selesai' => '2026-06-03T10:30',
            'durasi_menit' => 90,
        ])->assertOk();

        $this->assertDatabaseHas('jadwal_ujians', [
            'id' => 1,
            'waktu_mulai' => '2026-06-03 01:00:00',
            'waktu_selesai' => '2026-06-03 03:30:00',
            'durasi_menit' => 90,
            'token' => $token,
        ]);
    }

    public function test_schedule_edit_cannot_change_target_after_student_session_exists(): void
    {
        DB::table('sesi_ujians')->insert([
            'user_id' => 5,
            'jadwal_ujian_id' => 1,
            'waktu_login' => now(),
            'status' => 'aktif',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $classId = DB::table('kelas_aktifs')->insertGetId([
            'tingkat' => 11,
            'jurusan_id' => 1,
            'rombel_id' => 1,
            'nama_kelas' => '11 DKV 1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->post('/login', [
            'username' => 'admin',
            'password' => 'admin123',
        ]);

        $this->putJson('/kelola/data/jadwal-ujian/1', [
            'master_ujian_id' => 1,
            'kelas_aktif_id' => $classId,
            'waktu_mulai' => '2026-06-03T08:00',
            'waktu_selesai' => '2026-06-03T10:30',
            'durasi_menit' => 90,
        ])->assertStatus(422);
    }

    public function test_admin_can_edit_master_exam_result_visibility_after_session_exists(): void
    {
        DB::table('sesi_ujians')->insert([
            'user_id' => 5, 'jadwal_ujian_id' => 1, 'waktu_login' => now(), 'status' => 'aktif',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->post('/login', ['username' => 'admin', 'password' => 'admin123']);

        $this->putJson('/kelola/data/master-ujian/1', [
            'judul' => 'Latihan Matematika Revisi',
            'paket_soal_id' => 1,
            'acak_soal' => false,
            'acak_opsi' => false,
            'tampilkan_nilai_akhir' => true,
            'hasil_visibilitas' => 'scheduled',
            'tanggal_rilis_hasil' => '2026-06-10T08:00',
        ])->assertOk();

        $this->assertDatabaseHas('master_ujians', [
            'id' => 1,
            'judul' => 'Latihan Matematika Revisi',
            'hasil_visibilitas' => 'scheduled',
        ]);
    }

    public function test_master_exam_edit_cannot_change_package_after_session_exists(): void
    {
        DB::table('sesi_ujians')->insert([
            'user_id' => 5, 'jadwal_ujian_id' => 1, 'waktu_login' => now(), 'status' => 'aktif',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $packageId = DB::table('paket_soals')->insertGetId([
            'mata_pelajaran_id' => 1, 'pembuat_user_id' => 3, 'judul' => 'Paket Pengganti', 'status' => 'ready',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->post('/login', ['username' => 'admin', 'password' => 'admin123']);

        $this->putJson('/kelola/data/master-ujian/1', [
            'judul' => 'Tidak Berubah',
            'paket_soal_id' => $packageId,
            'acak_soal' => false,
            'acak_opsi' => false,
            'tampilkan_nilai_akhir' => true,
            'hasil_visibilitas' => 'instant',
        ])->assertStatus(422);
    }
}
