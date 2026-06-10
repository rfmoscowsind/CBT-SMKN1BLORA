<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ExamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamResultManagementTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_admin_can_filter_exam_results_by_level_major_and_group(): void
    {
        app(ExamService::class)->start(User::where('username', 'siswa')->first(), 1, 'LATIHAN', '127.0.0.1', 'phpunit');
        $this->post('/login', ['username' => 'admin', 'password' => 'admin123']);

        $this->getJson('/kelola/data/hasil-ujian/options')
            ->assertOk()
            ->assertJsonFragment(['nama_kelas' => '10 DKV 1']);

        $this->getJson('/kelola/data/hasil-ujian?tingkat=10&jurusan_id=1&rombel_id=1')
            ->assertOk()
            ->assertJsonPath('data.kelas.nama_kelas', '10 DKV 1')
            ->assertJsonPath('data.schedules.0.judul', 'Latihan Matematika')
            ->assertJsonPath('data.schedules.0.statistik.total_target', 1)
            ->assertJsonPath('data.schedules.0.hasil.0.username', 'siswa');
    }

    public function test_result_filter_requires_complete_class_selection(): void
    {
        $this->post('/login', ['username' => 'superadmin', 'password' => 'superadmin123']);

        $this->getJson('/kelola/data/hasil-ujian?tingkat=10')
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['jurusan_id', 'rombel_id']]);
    }

    public function test_student_cannot_open_exam_result_data(): void
    {
        $this->post('/login', ['username' => 'siswa', 'password' => 'siswa123']);

        $this->getJson('/kelola/data/hasil-ujian/options')->assertForbidden();
    }
}
