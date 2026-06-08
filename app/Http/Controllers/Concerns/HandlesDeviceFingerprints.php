<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait HandlesDeviceFingerprints
{
    protected function deviceRaw(Request $request): array
    {
        $raw = $request->input('device_raw');

        if ($raw === null && $request->query->has('device_raw')) {
            $raw = $request->query('device_raw');
        }

        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    protected function clientFingerprint(Request $request): ?string
    {
        $value = $request->header('X-Device-Fingerprint')
            ?: $request->input('device_fp')
            ?: $request->query('device_fp');

        $value = is_string($value) ? trim($value) : '';

        return $this->isUsableFingerprint($value) ? $value : null;
    }

    protected function isUsableFingerprint(?string $value): bool
    {
        $value = is_string($value) ? trim($value) : '';

        if ($value === '' || $value === 'dfp_0') {
            return false;
        }

        return (bool) preg_match('/^dfp_[0-9a-f]{6,}$/i', $value);
    }

    protected function fingerprintMatches(?string $current, array $raw, ?string $known): bool
    {
        if (! $current || ! $known) {
            return false;
        }

        if (hash_equals($known, $current)) {
            return true;
        }

        $legacy = isset($raw['legacyHash']) && is_string($raw['legacyHash'])
            ? trim($raw['legacyHash'])
            : null;

        return $this->isUsableFingerprint($legacy) && hash_equals($known, $legacy);
    }

    protected function rememberUserDevice(User $user, Request $request): void
    {
        $fingerprint = $this->clientFingerprint($request);

        if (! $fingerprint) {
            return;
        }

        DB::table('users')->where('id', $user->id)->update([
            'device_fingerprint'     => $fingerprint,
            'device_fingerprint_raw' => json_encode($this->deviceRaw($request)),
            'is_device_locked'       => false,
            'updated_at'             => now(),
        ]);
    }

    protected function deviceLockEnabled(): bool
    {
        return Cache::remember('setting:device_lock_enabled', 30, function () {
            if (! Schema::hasTable('app_settings')) {
                return filter_var(env('DEVICE_LOCK_ENABLED', true), FILTER_VALIDATE_BOOLEAN);
            }

            $value = DB::table('app_settings')
                ->where('key', 'device_lock_enabled')
                ->value('value');

            if ($value === null) {
                return filter_var(env('DEVICE_LOCK_ENABLED', true), FILTER_VALIDATE_BOOLEAN);
            }

            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        });
    }

    protected function setDeviceLockEnabled(bool $enabled, ?int $actorId = null): bool
    {
        abort_unless(Schema::hasTable('app_settings'), 503, 'Tabel pengaturan belum tersedia. Jalankan migration terlebih dahulu.');

        $old = $this->deviceLockEnabled();

        DB::table('app_settings')->updateOrInsert(
            ['key' => 'device_lock_enabled'],
            [
                'value' => $enabled ? 'true' : 'false',
                'updated_by' => $actorId,
                'updated_at' => now(),
            ]
        );

        Cache::forget('setting:device_lock_enabled');

        DB::table('audit_logs')->insert([
            'user_id' => $actorId,
            'action' => 'device_lock_setting_changed',
            'payload' => json_encode(['old' => $old, 'new' => $enabled]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $enabled;
    }

    protected function recordDeviceFingerprintHistory(User $user, Request $request, string $fingerprint, ?object $session = null, string $action = 'checked'): void
    {
        if (! Schema::hasTable('device_fingerprint_histories')) {
            return;
        }

        $lockEnabled = $this->deviceLockEnabled();
        $dedup = sha1(implode('|', [
            $user->id,
            $fingerprint,
            $session->id ?? '',
            $request->ip(),
            $request->userAgent(),
            $action,
            $lockEnabled ? '1' : '0',
        ]));

        if (! Cache::add("device-history:$dedup", 1, now()->addSeconds(60))) {
            return;
        }

        DB::table('device_fingerprint_histories')->insert([
            'user_id' => $user->id,
            'nis' => $user->nis ?? null,
            'nisn' => $user->nisn ?? null,
            'username' => $user->username,
            'sesi_ujian_id' => $session->id ?? null,
            'jadwal_ujian_id' => $session->jadwal_ujian_id ?? null,
            'fingerprint' => $fingerprint,
            'fingerprint_raw' => json_encode($this->deviceRaw($request)),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'lock_enabled' => $lockEnabled,
            'action' => $action,
            'created_at' => now(),
        ]);
    }

    protected function rememberSessionDevice(int $sessionId, Request $request): void
    {
        $fingerprint = $this->clientFingerprint($request);

        if (! $fingerprint) {
            return;
        }

        DB::table('sesi_ujians')->where('id', $sessionId)->update([
            'device_fingerprint'     => $fingerprint,
            'device_fingerprint_raw' => json_encode($this->deviceRaw($request)),
            'is_device_locked'       => false,
            'updated_at'             => now(),
        ]);
    }

    protected function clearDeviceAccessForUser(int $userId, ?int $actorId = null, string $reason = 'device_unlock'): void
    {
        DB::transaction(function () use ($userId, $actorId, $reason): void {
            DB::table('users')->where('id', $userId)->update([
                'device_fingerprint'     => null,
                'device_fingerprint_raw' => null,
                'is_device_locked'       => false,
                'updated_at'             => now(),
            ]);

            $sessionIds = DB::table('sesi_ujians')->where('user_id', $userId)->pluck('id')->all();

            DB::table('sesi_ujians')->where('user_id', $userId)->update([
                'device_fingerprint'     => null,
                'device_fingerprint_raw' => null,
                'is_device_locked'       => false,
                'updated_at'             => now(),
            ]);

            DB::table('sesi_ujians')
                ->where('user_id', $userId)
                ->whereIn('status', ['aktif', 'terkunci'])
                ->update([
                    'status'     => 'aktif',
                    'updated_at' => now(),
                ]);

            if (! empty($sessionIds)) {
                $events = array_map(fn ($sessionId) => [
                    'sesi_ujian_id' => $sessionId,
                    'event_type'    => $reason,
                    'event_data'    => json_encode([
                        'actor_id'                  => $actorId,
                        'cleared_all_user_sessions' => true,
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $sessionIds);

                DB::table('session_events')->insert($events);
            }
        });

        $markerKey = "cbt:device-reset:{$userId}";
        Cache::put($markerKey, now()->timestamp, now()->addHours(12));

        Cache::forget("cbt:user-sessions:{$userId}");
    }

    protected function lockDeviceAccessForUser(int $userId, ?int $sessionId = null, string $reason = 'device_lock'): void
    {
        DB::table('users')->where('id', $userId)->update([
            'is_device_locked' => true,
            'updated_at'       => now(),
        ]);

        $query = DB::table('sesi_ujians')->where('user_id', $userId);
        if ($sessionId !== null) {
            $query->where('id', $sessionId);
        } else {
            $query->whereIn('status', ['aktif', 'terkunci']);
        }

        $query->update([
            'status'           => 'terkunci',
            'is_device_locked' => true,
            'updated_at'       => now(),
        ]);

        $eventSessionId = $sessionId ?: DB::table('sesi_ujians')
            ->where('user_id', $userId)
            ->where('status', 'terkunci')
            ->latest('id')
            ->value('id');

        if ($eventSessionId) {
            DB::table('session_events')->insert([
                'sesi_ujian_id' => $eventSessionId,
                'event_type'    => $reason,
                'event_data'    => json_encode(['user_id' => $userId]),
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }
    }

    protected function acknowledgeDeviceReset(User $user): void
    {
        $markerKey = "cbt:device-reset:{$user->id}";
        $marker    = (int) Cache::get($markerKey, 0);

        if ($marker > 0) {
            session(["cbt_device_reset_ack_{$user->id}" => $marker]);
            Cache::forget($markerKey);
        }
    }

    protected function rejectStaleDeviceResetSession(?User $user = null): void
    {
        $user = $user ?: Auth::user();

        if (! $user || $user->role !== 'Siswa') {
            return;
        }

        $markerKey = "cbt:device-reset:{$user->id}";
        $marker    = (int) Cache::get($markerKey, 0);

        if ($marker === 0) {
            return;
        }

        if (request()->hasSession()) {
            $ack = (int) request()->session()->get("cbt_device_reset_ack_{$user->id}", 0);
            if ($ack >= $marker) {
                return;
            }
        } else {
            try {
                $payload = \Tymon\JWTAuth\Facades\JWTAuth::parseToken()->getPayload();
                $iat = (int) $payload->get('iat');
                if ($iat >= $marker) {
                    return;
                }
            } catch (\Throwable $e) {
                // Continue to abort if token is stale or unparseable
            }
        }

        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
        }
        if (Auth::guard('api')->check()) {
            Auth::guard('api')->logout();
        }

        if (request()->hasSession()) {
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }

        abort(423, 'Akses gawai sudah direset admin. Silakan login ulang.');
    }

    protected function enforceDeviceFingerprint(Request $request, ?object $session = null): void
    {
        $user = Auth::user();

        if ($user) {
            $this->enforceDeviceFingerprintForUser($user, $request, $session);
        }
    }

    protected function enforceDeviceFingerprintForUser(User $user, Request $request, ?object $session = null): void
    {
        if ($user->role !== 'Siswa') {
            return;
        }

        $freshUser = User::query()->find($user->id);
        if (! $freshUser) {
            abort(401);
        }

        $lockEnabled = $this->deviceLockEnabled();
        $sessionLocked = $session && (
            (bool) ($session->is_device_locked ?? false)
            || ($session->status ?? null) === 'terkunci'
        );

        if ($lockEnabled && ((bool) $freshUser->is_device_locked || $sessionLocked)) {
            abort(423, 'Akun terkunci karena terdeteksi perangkat berbeda. Silakan hubungi admin.');
        }

        $fingerprint = $this->clientFingerprint($request);
        if (! $fingerprint) {
            return;
        }

        $raw   = $this->deviceRaw($request);
        $this->recordDeviceFingerprintHistory(
            $freshUser,
            $request,
            $fingerprint,
            $session,
            $lockEnabled ? 'checked' : 'audit_only'
        );

        if (! $lockEnabled) {
            if (! $freshUser->device_fingerprint) {
                $this->rememberUserDevice($freshUser, $request);
            }
            if ($session && isset($session->id) && ! ($session->device_fingerprint ?? null)) {
                $this->rememberSessionDevice((int) $session->id, $request);
            }

            return;
        }

        $known = $freshUser->device_fingerprint ?: ($session->device_fingerprint ?? null);
        $known = $this->isUsableFingerprint($known) ? $known : null;

        if (! $known) {
            $this->rememberUserDevice($freshUser, $request);
            if ($session && isset($session->id)) {
                $this->rememberSessionDevice((int) $session->id, $request);
            }

            return;
        }

        if (! $this->fingerprintMatches($fingerprint, $raw, $known)) {
            $this->lockDeviceAccessForUser($freshUser->id, $session->id ?? null, 'device_mismatch');
            abort(423, 'Akun terkunci karena terdeteksi perangkat berbeda. Silakan hubungi admin.');
        }

        if ($freshUser->device_fingerprint !== $fingerprint) {
            $this->rememberUserDevice($freshUser, $request);
        }
        if ($session && isset($session->id) && ($session->device_fingerprint ?? null) !== $fingerprint) {
            $this->rememberSessionDevice((int) $session->id, $request);
        }
    }
}
