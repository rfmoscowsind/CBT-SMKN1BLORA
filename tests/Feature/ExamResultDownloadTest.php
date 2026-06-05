<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ExamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExamResultDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_admin_can_preview_and_download_class_result_pdf(): void
    {
        $this->post('/login', ['username' => 'admin', 'password' => 'admin123']);

        $this->getJson('/kelola/data/download-hasil/options')
            ->assertOk()
            ->assertJsonFragment([
                'nama_kelas' => '10 DKV 1',
                'nama_mapel' => 'Matematika',
            ]);

        $this->get('/kelola/data/download-hasil/preview?jadwal_id=1&kelas_aktif_id=1')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->get('/kelola/data/download-hasil/download?jadwal_id=1&kelas_aktif_id=1')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename=hasil-mtk-10-dkv-1.pdf');

        $this->assertDatabaseHas('hasil_ujian_unduhans', [
            'jadwal_ujian_id' => 1,
            'kelas_aktif_id' => 1,
        ]);
    }

    public function test_schedule_can_only_be_archived_after_pdf_download(): void
    {
        $session = app(ExamService::class)->start(User::where('username', 'siswa')->first(), 1, 'LATIHAN', '127.0.0.1', 'phpunit');
        $this->post('/login', ['username' => 'admin', 'password' => 'admin123']);

        $this->deleteJson('/kelola/data/jadwal-ujian/1')
            ->assertStatus(422);

        $this->get('/kelola/data/download-hasil/download?jadwal_id=1&kelas_aktif_id=1')
            ->assertOk();

        $this->deleteJson('/kelola/data/jadwal-ujian/1')
            ->assertOk();

        $this->assertNotNull(DB::table('jadwal_ujians')->where('id', 1)->value('diarsipkan_at'));
        $this->assertDatabaseHas('sesi_ujians', ['id' => $session->id]);
        $this->assertCount(0, app(ExamService::class)->schedulesFor(User::where('username', 'siswa')->first()));
        $this->getJson('/kelola/data/jadwal-ujian')
            ->assertOk()
            ->assertJsonCount(0, 'data.schedules');
    }

    public function test_student_cannot_download_result_pdf(): void
    {
        $this->post('/login', ['username' => 'siswa', 'password' => 'siswa123']);

        $this->get('/kelola/data/download-hasil/download?jadwal_id=1&kelas_aktif_id=1')
            ->assertForbidden();
    }
}
