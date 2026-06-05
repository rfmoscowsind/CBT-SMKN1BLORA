<?php

namespace Tests\Feature;

use App\Services\IdCodec;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentDashboardScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_student_dashboard_json_contains_assigned_class_schedule(): void
    {
        $this->post('/login', [
            'username' => 'siswa',
            'password' => 'siswa123',
        ])->assertRedirectContains('/dashboard?auth_refresh=');

        $this->get('/dashboard')
            ->assertRedirect('/vue/dashboard/siswa');

        $this->getJson('/dashboard')
            ->assertOk()
            ->assertJsonPath('schedules.0.hash', app(IdCodec::class)->encode(1))
            ->assertJsonPath('schedules.0.mapel_nama', 'Matematika')
            ->assertJsonPath('schedules.0.judul_ujian', 'Latihan Matematika')
            ->assertJsonPath('schedules.0.status', 'aktif')
            ->assertJsonStructure([
                'user' => ['nama', 'nisn', 'kelas', 'jurusan'],
                'schedules' => [[
                    'hash',
                    'mapel_nama',
                    'judul_ujian',
                    'waktu_mulai',
                    'waktu_selesai',
                    'durasi_menit',
                    'jumlah_soal',
                    'tipe_soal',
                    'gunakan_token',
                    'status',
                ]],
            ]);
    }

    public function test_student_dashboard_only_lists_today_and_token_is_trimmed_case_insensitively(): void
    {
        $this->post('/login', [
            'username' => 'siswa',
            'password' => 'siswa123',
        ]);

        $scheduleId = DB::table('jadwal_ujians')->insertGetId([
            'master_ujian_id' => 1,
            'waktu_mulai' => now()->addDays(2),
            'waktu_selesai' => now()->addDays(3),
            'durasi_menit' => 60,
            'gunakan_token' => true,
            'token' => 'NANTI',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('jadwal_ujian_kelas')->insert([
            'jadwal_ujian_id' => $scheduleId,
            'kelas_aktif_id' => 1,
        ]);

        $this->getJson('/dashboard')
            ->assertOk()
            ->assertJsonCount(1, 'schedules');

        $this->postJson('/ujian/'.app(IdCodec::class)->encode(1).'/mulai', [
            'token' => ' latihan ',
        ])->assertOk()->assertJsonPath('success', true);
    }

    public function test_student_can_load_exam_json_after_token_is_accepted(): void
    {
        $this->post('/login', [
            'username' => 'siswa',
            'password' => 'siswa123',
        ]);

        $sessionHash = $this->postJson('/ujian/'.app(IdCodec::class)->encode(1).'/mulai', [
            'token' => 'LATIHAN',
        ])->assertOk()->json('session_hash');

        $this->getJson("/ujian/sesi/{$sessionHash}")
            ->assertOk()
            ->assertJsonPath('status', 'aktif')
            ->assertJsonCount(4, 'soal')
            ->assertJsonPath('soal.0.opsi.0.teks', '3')
            ->assertJsonMissing(['is_benar' => true])
            ->assertJsonPath('siswa.kelas', '10 DKV 1');
    }
}
