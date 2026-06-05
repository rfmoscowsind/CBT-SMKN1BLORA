<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentPasswordManagementTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_admin_can_create_student_and_reset_login_password(): void
    {
        $login = $this->post('/login', [
            'username' => 'superadmin',
            'password' => 'superadmin123',
        ]);
        $login->assertRedirect();
        $this->assertStringContainsString('/dashboard?auth_refresh=', $login->headers->get('Location'));

        $response = $this->postJson('/kelola/data/siswa', [
            'nisn' => '00990011',
            'nama' => 'Siswa Password',
            'kelas_aktif_id' => 1,
            'password' => 'awal123',
            'status' => 'aktif',
        ])->assertCreated();

        $student = User::findOrFail($response->json('data.id'));
        $this->assertTrue(Hash::check('awal123', $student->password));
        $this->assertSame('00990011@siswa.local', $student->email);

        // Test custom email creation
        $responseWithEmail = $this->postJson('/kelola/data/siswa', [
            'nisn' => '00990022',
            'nama' => 'Siswa Email Custom',
            'email' => 'custom@domain.com',
            'kelas_aktif_id' => 1,
            'password' => 'awal123',
            'status' => 'aktif',
        ])->assertCreated();
        $studentWithEmail = User::findOrFail($responseWithEmail->json('data.id'));
        $this->assertSame('custom@domain.com', $studentWithEmail->email);

        $this->putJson("/kelola/data/siswa/{$student->id}", [
            'nisn' => '00990011',
            'nama' => 'Siswa Password Diperbarui',
            'email' => 'updated@domain.com',
            'kelas_aktif_id' => 1,
            'password' => '',
            'status' => 'aktif',
        ])->assertOk();

        $this->assertSame('Siswa Password Diperbarui', $student->fresh()->name);
        $this->assertSame('updated@domain.com', $student->fresh()->email);
        $this->assertTrue(Hash::check('awal123', $student->fresh()->password));

        // Test unique email validation during update
        $this->putJson("/kelola/data/siswa/{$student->id}", [
            'nisn' => '00990011',
            'nama' => 'Siswa Password Diperbarui',
            'email' => 'custom@domain.com',
            'kelas_aktif_id' => 1,
            'password' => '',
            'status' => 'aktif',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->putJson("/kelola/data/siswa/{$student->id}", [
            'nisn' => '00990011',
            'nama' => 'Siswa Password Diperbarui',
            'kelas_aktif_id' => 1,
            'password' => '123',
            'status' => 'aktif',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->patchJson("/kelola/data/siswa/{$student->id}/password", [
            'password' => 'baru456',
            'password_confirmation' => 'baru456',
        ])->assertOk();

        $this->assertTrue(Hash::check('baru456', $student->fresh()->password));
        $this->assertFalse(Hash::check('awal123', $student->fresh()->password));
    }

    public function test_student_cannot_access_student_management_data(): void
    {
        $this->actingAs(User::where('username', 'siswa')->firstOrFail())
            ->getJson('/kelola/data/siswa')
            ->assertForbidden();
    }
}
