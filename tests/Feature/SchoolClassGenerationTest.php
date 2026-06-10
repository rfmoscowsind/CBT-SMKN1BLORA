<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SchoolClassGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_admin_can_generate_sequential_classes_and_import_students_without_resetting_password(): void
    {
        $this->post('/login', [
            'username' => 'superadmin',
            'password' => 'superadmin123',
        ])->assertRedirect();

        $tingkatId = $this->postJson('/kelola/data/tingkat', [
            'nama_tingkat' => 10,
        ])->assertCreated()->json('data.id');

        $jurusanId = $this->postJson('/kelola/data/jurusan', [
            'kode_jurusan' => 'te',
            'nama_jurusan' => 'Teknik Elektronika',
        ])->assertCreated()->json('data.id');

        $firstClassId = $this->postJson('/kelola/data/kelas/generate', [
            'tingkat_id' => $tingkatId,
            'jurusan_id' => $jurusanId,
        ])->assertCreated()->assertJsonPath('data.kelas.nama_kelas', '10 TE 1')->json('data.kelas.id');

        $secondClassId = $this->postJson('/kelola/data/kelas/generate', [
            'tingkat_id' => $tingkatId,
            'jurusan_id' => $jurusanId,
        ])->assertCreated()->assertJsonPath('data.kelas.nama_kelas', '10 TE 2')->json('data.kelas.id');

        $this->post("/kelola/data/kelas/{$secondClassId}/import-siswa", [
            'file' => UploadedFile::fake()->createWithContent(
                'siswa.csv',
                "nisn,nama,email,password\n99112233,Siswa Import,siswa.import@example.test,rahasia123\n"
            ),
        ])->assertOk()->assertJsonPath('data.imported', 1);

        $student = User::where('username', '99112233')->firstOrFail();
        $this->assertSame($secondClassId, $student->kelas_aktif_id);
        $this->assertTrue(Hash::check('rahasia123', $student->password));

        $this->post("/kelola/data/kelas/{$firstClassId}/import-siswa", [
            'file' => UploadedFile::fake()->createWithContent(
                'siswa.csv',
                "nisn,nama,email,password\n99112233,Siswa Import Baru,siswa.import@example.test,\n"
            ),
        ])->assertOk()->assertJsonPath('data.imported', 1);

        $student->refresh();
        $this->assertSame($firstClassId, $student->kelas_aktif_id);
        $this->assertSame('Siswa Import Baru', $student->name);
        $this->assertTrue(Hash::check('rahasia123', $student->password));
    }
}
