<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ExamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QuestionBankManagementTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_teacher_can_create_question_package_add_question_and_finalize_it(): void
    {
        $this->post('/login', ['username' => 'guru', 'password' => 'guru123']);

        $packageId = $this->postJson('/kelola/data/paket-soal', [
            'mata_pelajaran_id' => 1,
            'kode_paket' => 'PAS-MTK-10',
            'jumlah_pg' => 0,
            'has_isian' => false,
        ])->assertCreated()->json('data.id');

        $this->postJson("/kelola/data/paket-soal/$packageId/soal", [
            'tipe_soal' => 'PG',
            'pertanyaan' => 'Hasil dari 10 + 5 adalah?',
            'bobot_nilai' => 10,
            'opsi' => [
                ['kode' => 'A', 'teks_opsi' => '10', 'is_benar' => false],
                ['kode' => 'B', 'teks_opsi' => '15', 'is_benar' => true],
            ],
        ])->assertCreated();

        $this->postJson("/kelola/data/paket-soal/$packageId/ready")
            ->assertOk();

        $this->assertDatabaseHas('paket_soals', [
            'id' => $packageId,
            'status' => 'ready',
        ]);
        $this->post('/logout');
        $this->post('/login', ['username' => 'superadmin', 'password' => 'superadmin123']);
        $this->getJson('/kelola/data/jadwal-ujian')
            ->assertOk()
            ->assertJsonFragment([
                'judul' => 'PAS-MTK-10 - Matematika',
                'jumlah_soal' => 1,
            ]);
    }

    public function test_teacher_can_update_question_options_and_package_returns_to_draft(): void
    {
        $this->post('/login', ['username' => 'guru', 'password' => 'guru123']);
        $questionId = DB::table('bank_soals')->where('paket_soal_id', 1)->first()->id;

        $this->postJson("/kelola/data/paket-soal/1/soal/$questionId", [
            'tipe_soal' => 'PG',
            'pertanyaan' => 'Soal direvisi',
            'bobot_nilai' => 20,
            'opsi' => [
                ['kode' => 'A', 'teks_opsi' => 'Salah', 'is_benar' => false],
                ['kode' => 'B', 'teks_opsi' => 'Benar', 'is_benar' => true],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('bank_soals', ['id' => $questionId, 'pertanyaan' => 'Soal direvisi']);
        $this->assertDatabaseHas('paket_soals', ['id' => 1, 'status' => 'draft']);
        $this->assertDatabaseHas('opsi_jawabans', ['bank_soal_id' => $questionId, 'kode' => 'B', 'is_benar' => true]);
    }

    public function test_package_with_student_session_cannot_be_revised(): void
    {
        app(ExamService::class)->start(User::where('username', 'siswa')->first(), 1, 'LATIHAN', '127.0.0.1', 'phpunit');
        $this->post('/login', ['username' => 'guru', 'password' => 'guru123']);

        $this->putJson('/kelola/data/paket-soal/1', [
            'mata_pelajaran_id' => 1,
            'kode_paket' => 'LATIHAN',
            'jumlah_pg' => 4,
            'has_isian' => false,
        ])->assertStatus(422);
    }

    public function test_student_cannot_open_question_bank_data(): void
    {
        $this->post('/login', ['username' => 'siswa', 'password' => 'siswa123']);

        $this->getJson('/kelola/data/paket-soal')->assertForbidden();
    }
}
