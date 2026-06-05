<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ExamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeviceFingerprintResetTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_unlock_clears_student_device_lock_everywhere(): void
    {
        $student = User::where('username', 'siswa')->firstOrFail();
        $session = app(ExamService::class)->start($student, 1, 'LATIHAN', '127.0.0.1', 'phpunit');

        DB::table('users')->where('id', $student->id)->update([
            'device_fingerprint' => 'old-device',
            'device_fingerprint_raw' => json_encode(['legacyHash' => 'old-legacy']),
            'is_device_locked' => true,
        ]);
        DB::table('sesi_ujians')->where('id', $session->id)->update([
            'status' => 'terkunci',
            'device_fingerprint' => 'old-device',
            'device_fingerprint_raw' => json_encode(['legacyHash' => 'old-legacy']),
            'is_device_locked' => true,
        ]);

        $this->post('/login', ['username' => 'superadmin', 'password' => 'superadmin123']);
        $this->postJson("/kelola/data/device-fingerprints/{$student->id}/unlock")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'device_fingerprint' => null,
            'device_fingerprint_raw' => null,
            'is_device_locked' => false,
        ]);
        $this->assertDatabaseHas('sesi_ujians', [
            'id' => $session->id,
            'status' => 'aktif',
            'device_fingerprint' => null,
            'device_fingerprint_raw' => null,
            'is_device_locked' => false,
        ]);
        $this->assertNotEmpty(Cache::get("cbt:device-reset:{$student->id}"));
    }

    public function test_bad_login_fingerprint_does_not_lock_next_valid_dashboard_request(): void
    {
        $student = User::where('username', 'siswa')->firstOrFail();

        $this->post('/login', [
            'username' => 'siswa',
            'password' => 'siswa123',
            'device_fp' => 'dfp_0',
        ])->assertRedirectContains('/dashboard?auth_refresh=');

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'device_fingerprint' => null,
            'is_device_locked' => false,
        ]);

        $this->withHeader('X-Device-Fingerprint', 'dfp_abcdef12')
            ->getJson('/dashboard?device_raw[legacyHash]=dfp_abcdef12')
            ->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'device_fingerprint' => 'dfp_abcdef12',
            'is_device_locked' => false,
        ]);
    }
}
