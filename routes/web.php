<?php
use App\Http\Controllers\WebController;
use App\Http\Controllers\ManageController;
use App\Http\Controllers\StudentManagementController;
use App\Http\Controllers\SchoolMasterController;
use App\Http\Controllers\ScheduleManagementController;
use App\Http\Controllers\ExamResultManagementController;
use App\Http\Controllers\ExamResultArchiveController;
use App\Http\Controllers\ExamResultDownloadController;
use App\Http\Controllers\QuestionBankManagementController;
use App\Http\Controllers\StaffManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/healthz', fn () => response()->json([
    'ok' => true,
    'time' => now()->toISOString(),
]));

Route::get('/readyz', function () {
    $checks = ['db' => false, 'redis' => false];

    try {
        \Illuminate\Support\Facades\DB::select('select 1');
        $checks['db'] = true;
    } catch (\Throwable $e) {
        $checks['db_error'] = 'unavailable';
    }

    try {
        \Illuminate\Support\Facades\Redis::ping();
        $checks['redis'] = true;
    } catch (\Throwable $e) {
        $checks['redis_error'] = 'unavailable';
    }

    $ok = $checks['db'] && $checks['redis'];

    return response()->json([
        'ok' => $ok,
        'checks' => $checks,
        'time' => now()->toISOString(),
    ], $ok ? 200 : 503);
});

Route::redirect('/', '/login');
Route::get('/login', [WebController::class, 'loginForm'])->name('login');
Route::post('/login', [WebController::class, 'login'])->middleware('throttle:10,1')->name('login.submit');
Route::any('/logout', [WebController::class, 'logout'])->name('logout');
Route::get('/media/soal-images/{file}', function (string $file) {
    abort_unless(preg_match('/^[A-Za-z0-9._-]+$/', $file) === 1, 404);

    $path = 'soal-images/'.$file;
    $disk = config('filesystems.default') === 's3' ? 's3' : 'public';
    $storage = \Illuminate\Support\Facades\Storage::disk($disk);

    abort_unless($storage->exists($path), 404);

    return response($storage->get($path), 200, [
        'Content-Type' => $storage->mimeType($path) ?: 'image/webp',
        'Cache-Control' => 'public, max-age=31536000, immutable',
    ]);
})->where('file', '[A-Za-z0-9._-]+');

Route::middleware('auth')->group(function () {
    $managementPage = function () {
        abort_if(Auth::user()?->role === 'Siswa', 403);

        return view('app');
    };

    // ── Vue SPA Pages (GET only) ──────────────────────────────────
    // Dashboard redirect based on User Role
    Route::get('/dashboard', [WebController::class, 'dashboard'])->name('dashboard');

    Route::get('/kelola', $managementPage);
    Route::get('/kelola/data/siswa', [StudentManagementController::class, 'index']);
    Route::post('/kelola/data/siswa', [StudentManagementController::class, 'store']);
    Route::put('/kelola/data/siswa/{student}', [StudentManagementController::class, 'update']);
    Route::patch('/kelola/data/siswa/{student}/password', [StudentManagementController::class, 'password']);
    Route::delete('/kelola/data/siswa/{student}', [StudentManagementController::class, 'destroy']);
    Route::get('/api/management/staff', [StaffManagementController::class, 'index']);
    Route::post('/api/management/staff', [StaffManagementController::class, 'store']);
    Route::put('/api/management/staff/{staf}', [StaffManagementController::class, 'update']);
    Route::patch('/api/management/staff/{staf}/password', [StaffManagementController::class, 'password']);
    Route::delete('/api/management/staff/{staf}', [StaffManagementController::class, 'destroy']);


    Route::get('/kelola/data/staf', [StaffManagementController::class, 'index']);
    Route::post('/kelola/data/staf', [StaffManagementController::class, 'store']);
    Route::put('/kelola/data/staf/{staf}', [StaffManagementController::class, 'update']);
    Route::patch('/kelola/data/staf/{staf}/password', [StaffManagementController::class, 'password']);
    Route::delete('/kelola/data/staf/{staf}', [StaffManagementController::class, 'destroy']);
    Route::get('/kelola/data/master-sekolah', [SchoolMasterController::class, 'index']);
    Route::post('/kelola/data/tingkat', [SchoolMasterController::class, 'storeTingkat']);
    Route::delete('/kelola/data/tingkat/{id}', [SchoolMasterController::class, 'destroyTingkat']);
    Route::post('/kelola/data/jurusan', [SchoolMasterController::class, 'storeJurusan']);
    Route::put('/kelola/data/jurusan/{id}', [SchoolMasterController::class, 'updateJurusan']);
    Route::delete('/kelola/data/jurusan/{id}', [SchoolMasterController::class, 'destroyJurusan']);
    Route::post('/kelola/data/kelas/generate', [SchoolMasterController::class, 'generateClass']);
    Route::post('/kelola/data/kelas/{id}/import-siswa', [SchoolMasterController::class, 'importStudents']);
    Route::get('/kelola/data/jadwal-ujian', [ScheduleManagementController::class, 'index']);
    Route::post('/kelola/data/master-ujian', [ScheduleManagementController::class, 'storeMaster']);
    Route::put('/kelola/data/master-ujian/{id}', [ScheduleManagementController::class, 'updateMaster']);
    Route::post('/kelola/data/jadwal-ujian', [ScheduleManagementController::class, 'storeSchedule']);
    Route::put('/kelola/data/jadwal-ujian/{id}', [ScheduleManagementController::class, 'updateSchedule']);
    Route::post('/kelola/data/jadwal-ujian/{id}/archive', [ScheduleManagementController::class, 'archive']);
    Route::post('/kelola/data/jadwal-ujian/{id}/token', [ScheduleManagementController::class, 'regenerateToken']);
    Route::delete('/kelola/data/jadwal-ujian', [ScheduleManagementController::class, 'massDestroy']);
    Route::delete('/kelola/data/jadwal-ujian/{id}', [ScheduleManagementController::class, 'destroy']);
    Route::get('/kelola/data/hasil-ujian/options', [ExamResultManagementController::class, 'options']);
    Route::get('/kelola/data/hasil-ujian', [ExamResultManagementController::class, 'index']);
    Route::get('/kelola/data/arsip-hasil/options', [ExamResultArchiveController::class, 'options']);
    Route::get('/kelola/data/arsip-hasil', [ExamResultArchiveController::class, 'index']);
    Route::get('/kelola/data/download-hasil/options', [ExamResultDownloadController::class, 'options']);
    Route::get('/kelola/data/download-hasil/preview', [ExamResultDownloadController::class, 'preview']);
    Route::get('/kelola/data/download-hasil/download', [ExamResultDownloadController::class, 'download']);
    Route::get('/kelola/data/paket-soal', [QuestionBankManagementController::class, 'index']);
    Route::post('/kelola/data/paket-soal', [QuestionBankManagementController::class, 'storePackage']);
    Route::get('/kelola/data/paket-soal/{id}', [QuestionBankManagementController::class, 'show']);
    Route::put('/kelola/data/paket-soal/{id}', [QuestionBankManagementController::class, 'updatePackage']);
    Route::delete('/kelola/data/paket-soal/{id}', [QuestionBankManagementController::class, 'destroyPackage']);
    Route::post('/kelola/data/paket-soal/{id}/ready', [QuestionBankManagementController::class, 'ready']);
    Route::post('/kelola/data/paket-soal/{id}/import', [QuestionBankManagementController::class, 'import']);
    Route::post('/kelola/data/paket-soal/{packageId}/soal', [QuestionBankManagementController::class, 'storeQuestion']);
    Route::post('/kelola/data/paket-soal/{packageId}/soal/{id}', [QuestionBankManagementController::class, 'updateQuestion']);
    Route::delete('/kelola/data/paket-soal/{packageId}/soal/{id}', [QuestionBankManagementController::class, 'destroyQuestion']);

    // ── Kelola POST/PUT/DELETE API routes (keep working) ──────────
    foreach (['mapel', 'jurusan', 'rombel', 'kelas'] as $type) {
        Route::post("/kelola/$type", [ManageController::class, $type]);
    }
    Route::put('/kelola/master/{type}/{id}', [ManageController::class, 'updateMaster']);
    Route::delete('/kelola/master/{type}/{id}', [ManageController::class, 'deleteMaster']);
    Route::post('/kelola/siswa', [ManageController::class, 'student']);
    Route::post('/kelola/staf', [ManageController::class, 'staffUser']);
    Route::post('/kelola/user/{id}/kehadiran', [ManageController::class, 'attendance']);
    Route::get('/kelola/template-siswa', [ManageController::class, 'studentTemplate']);
    Route::post('/kelola/import-siswa', [ManageController::class, 'bulk']);
    Route::post('/kelola/paket', [ManageController::class, 'storePackage']);
    Route::get('/kelola/paket/{id}/preview', [ManageController::class, 'previewPackage']);
    Route::get('/kelola/template-soal', [ManageController::class, 'questionTemplate']);
    Route::post('/kelola/paket/{id}/import-soal', [ManageController::class, 'importQuestions']);
    Route::post('/kelola/soal', [ManageController::class, 'question']);
    Route::put('/kelola/soal/{id}', [ManageController::class, 'updateQuestion']);
    Route::delete('/kelola/soal/{id}', [ManageController::class, 'deleteQuestion']);
    Route::post('/kelola/paket/{id}/ready', [ManageController::class, 'ready']);
    Route::post('/kelola/master-ujian', [ManageController::class, 'masterExam']);
    Route::post('/kelola/jadwal', [ManageController::class, 'schedule']);
    Route::post('/kelola/jadwal/{id}/token', [ManageController::class, 'regenerate']);
    Route::post('/kelola/sesi/{id}/reset', [ManageController::class, 'resetSession']);
    Route::post('/kelola/sesi/{id}/unlock-resume', [ManageController::class, 'unlockSessionForResume']);
    Route::get('/kelola/laporan/{id}/{format}', [ManageController::class, 'report']);
    Route::post('/kelola/grade/{id}', [ManageController::class, 'grade']);
    Route::put('/kelola/user/{id}', [ManageController::class, 'updateUser']);
    Route::delete('/kelola/user/{id}', [ManageController::class, 'deleteUser']);
    Route::put('/kelola/role/{id}/permissions', [ManageController::class, 'syncRolePermissions']);
    Route::get('/kelola/data/device-fingerprints', [ManageController::class, 'deviceFingerprints']);
    Route::post('/kelola/data/device-lock/toggle', [ManageController::class, 'toggleDeviceLock']);
    Route::post('/kelola/data/device-fingerprints/{id}/unlock', [ManageController::class, 'unlockDeviceFingerprint']);
    Route::post('/kelola/data/device-fingerprints/{id}/lock', [ManageController::class, 'lockDeviceFingerprint']);
    
    Route::get('/kelola/{section}', $managementPage)->where('section', '.*');

    // ── Ujian routes ──────────────────────────────────────────────
    Route::post('/ujian/{jadwal}/mulai', [WebController::class, 'start'])->name('exams.start');
    Route::get('/ujian/sesi/{id}', [WebController::class, 'show']);
    Route::get('/ujian/sesi/{id}/soal',   [WebController::class, 'soal'])->middleware('throttle:300,1'); // 1 soal per request
    Route::post('/ujian/sesi/{id}/simpan', [WebController::class, 'save'])->middleware('throttle:120,1');
    Route::post('/ujian/sesi/{id}/selesai', [WebController::class, 'submit']);
    Route::post('/ujian/sesi/{id}/sync', [WebController::class, 'sync'])->middleware('throttle:120,1');
    Route::post('/ujian/sesi/{id}/flag', [WebController::class, 'flag']);
    Route::post('/ujian/sesi/{id}/ping', [WebController::class, 'ping'])->middleware('throttle:120,1');
    Route::post('/ujian/sesi/{id}/event', [WebController::class, 'event']);
    Route::get('/ujian/sesi/{id}/hasil', [WebController::class, 'result']);

    // ── Monitoring API ──────────────────────────────────────────────
    Route::get('/monitoring/radar', function() {
        abort_unless(Auth::user()?->role === 'SuperAdmin', 403);
        return response()->json(json_decode(\Illuminate\Support\Facades\Redis::get('cbt:radar:live'), true) ?: []);
    });
    Route::get('/monitoring/stats', function() {
        abort_unless(Auth::user()?->can('monitor-exams') || in_array(Auth::user()?->role, ['SuperAdmin', 'Admin', 'Pengawas'], true), 403);
        $totalSiswa = DB::table('users')->where('role', 'Siswa')->count();
        $ujianBerlangsung = DB::table('jadwal_ujians')
            ->where('waktu_mulai', '<=', now())
            ->where('waktu_selesai', '>=', now())
            ->whereNull('diarsipkan_at')
            ->count();
        $pesertaAktif = DB::table('sesi_ujians')->where('status', 'aktif')->count();
        $siswaSelesai = DB::table('sesi_ujians')->where('status', 'selesai')->count();
        $totalPaket = DB::table('paket_soals')->count();

        $serverOverview = function (): array {
            $load = sys_getloadavg() ?: [0, 0, 0];
            $memory = ['used_mb' => null, 'total_mb' => null, 'percent' => null];
            if (is_readable('/proc/meminfo')) {
                $info = file('/proc/meminfo') ?: [];
                $values = [];
                foreach ($info as $line) {
                    if (preg_match('/^([A-Za-z_()]+):\s+(\d+)/', $line, $match)) {
                        $values[$match[1]] = (int) $match[2];
                    }
                }
                $total = $values['MemTotal'] ?? null;
                $available = $values['MemAvailable'] ?? null;
                if ($total && $available !== null) {
                    $used = $total - $available;
                    $memory = [
                        'used_mb' => round($used / 1024),
                        'total_mb' => round($total / 1024),
                        'percent' => round(($used / $total) * 100, 1),
                    ];
                }
            }

            $diskTotal = @disk_total_space('/');
            $diskFree = @disk_free_space('/');
            $disk = ['used_gb' => null, 'total_gb' => null, 'percent' => null];
            if ($diskTotal && $diskFree !== false) {
                $diskUsed = $diskTotal - $diskFree;
                $disk = [
                    'used_gb' => round($diskUsed / 1024 / 1024 / 1024, 1),
                    'total_gb' => round($diskTotal / 1024 / 1024 / 1024, 1),
                    'percent' => round(($diskUsed / $diskTotal) * 100, 1),
                ];
            }

            return [
                'name' => 'Server Utama',
                'host' => gethostname() ?: request()->server('SERVER_ADDR'),
                'status' => 'online',
                'load' => [
                    'one' => round((float) $load[0], 2),
                    'five' => round((float) $load[1], 2),
                    'fifteen' => round((float) $load[2], 2),
                ],
                'memory' => $memory,
                'disk' => $disk,
            ];
        };

        $databaseOverview = function (string $connection, string $label): array {
            $config = config("database.connections.$connection", []);
            $started = microtime(true);
            try {
                $row = DB::connection($connection)->selectOne("
                    select
                        now() as db_time,
                        pg_is_in_recovery() as is_standby,
                        pg_size_pretty(pg_database_size(current_database())) as db_size,
                        (
                            select count(*)
                            from pg_stat_activity
                            where datname = current_database()
                        ) as connections
                ");
                $latency = round((microtime(true) - $started) * 1000);

                return [
                    'name' => $label,
                    'host' => $config['host'] ?? '-',
                    'port' => $config['port'] ?? '-',
                    'database' => $config['database'] ?? '-',
                    'status' => 'online',
                    'latency_ms' => $latency,
                    'role' => ($row->is_standby ?? false) ? 'standby' : 'primary',
                    'size' => (string) ($row->db_size ?? '-'),
                    'connections' => (int) ($row->connections ?? 0),
                    'db_time' => (string) ($row->db_time ?? ''),
                    'message' => 'Connected',
                ];
            } catch (\Throwable $e) {
                return [
                    'name' => $label,
                    'host' => $config['host'] ?? '-',
                    'port' => $config['port'] ?? '-',
                    'database' => $config['database'] ?? '-',
                    'status' => 'offline',
                    'latency_ms' => null,
                    'role' => '-',
                    'size' => '-',
                    'connections' => null,
                    'db_time' => null,
                    'message' => $e->getMessage(),
                ];
            }
        };

        $redisOverview = function (string $connection, string $label): array {
            $config = config("database.redis.$connection", []);
            try {
                $info = \Illuminate\Support\Facades\Redis::connection($connection)->info();

                $memory = $info['Memory'] ?? $info;
                $stats = $info['Stats'] ?? $info;
                $clients = $info['Clients'] ?? $info;
                $server = $info['Server'] ?? $info;
                $keyspace = $info['Keyspace'] ?? [];
                $keyspaceRows = [];
                foreach ($keyspace as $db => $value) {
                    if (is_array($value)) {
                        $keyspaceRows[] = [
                            'db' => $db,
                            'keys' => (int) ($value['keys'] ?? 0),
                            'expires' => (int) ($value['expires'] ?? 0),
                        ];
                        continue;
                    }
                    if (is_string($value) && preg_match('/keys=(\d+),expires=(\d+)/', $value, $match)) {
                        $keyspaceRows[] = [
                            'db' => $db,
                            'keys' => (int) $match[1],
                            'expires' => (int) $match[2],
                        ];
                    }
                }

                $hits = (int) ($stats['keyspace_hits'] ?? 0);
                $misses = (int) ($stats['keyspace_misses'] ?? 0);
                $totalLookups = $hits + $misses;

                return [
                    'name' => $label,
                    'host' => $config['host'] ?? '-',
                    'port' => $config['port'] ?? '-',
                    'status' => 'online',
                    'memory' => [
                        'used' => $memory['used_memory_human'] ?? '-',
                        'peak' => $memory['used_memory_peak_human'] ?? '-',
                        'max' => $memory['maxmemory_human'] ?? 'unlimited',
                        'fragmentation' => isset($memory['mem_fragmentation_ratio']) ? round((float) $memory['mem_fragmentation_ratio'], 2) : null,
                    ],
                    'qps' => (int) ($stats['instantaneous_ops_per_sec'] ?? 0),
                    'clients' => (int) ($clients['connected_clients'] ?? 0),
                    'hit_rate' => $totalLookups > 0 ? round(($hits / $totalLookups) * 100, 1) : null,
                    'uptime_days' => (int) ($server['uptime_in_days'] ?? 0),
                    'keyspace' => $keyspaceRows,
                    'message' => 'Connected',
                ];
            } catch (\Throwable $e) {
                return [
                    'name' => $label,
                    'host' => $config['host'] ?? '-',
                    'port' => $config['port'] ?? '-',
                    'status' => 'offline',
                    'memory' => ['used' => '-', 'peak' => '-', 'max' => '-', 'fragmentation' => null],
                    'qps' => null,
                    'clients' => null,
                    'hit_rate' => null,
                    'uptime_days' => null,
                    'keyspace' => [],
                    'message' => $e->getMessage(),
                ];
            }
        };

        $pgbouncerRows = function (string $sql): array {
            $config = config('database.connections.'.config('database.default'), []);
            $command = [
                'timeout',
                '2',
                'psql',
                '-h',
                (string) ($config['host'] ?? '127.0.0.1'),
                '-p',
                (string) ($config['port'] ?? '6432'),
                '-U',
                (string) ($config['username'] ?? 'laravel'),
                '-d',
                env('PGBOUNCER_DATABASE', 'pgbouncer'),
                '-X',
                '-A',
                '-F',
                "\t",
                '-P',
                'footer=off',
                '-c',
                $sql,
            ];

            $pipes = [];
            $process = proc_open($command, [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ], $pipes, base_path(), [
                'PGPASSWORD' => (string) ($config['password'] ?? ''),
            ]);

            if (! is_resource($process)) {
                throw new RuntimeException('Gagal menjalankan psql.');
            }

            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);

            if ($exitCode !== 0) {
                throw new RuntimeException(trim($stderr) ?: "psql exit code $exitCode");
            }

            $lines = array_values(array_filter(array_map('trim', explode("\n", trim($stdout)))));
            if (count($lines) < 2) {
                return [];
            }

            $headers = explode("\t", array_shift($lines));

            return array_map(function (string $line) use ($headers) {
                $values = explode("\t", $line);

                return array_combine($headers, array_pad($values, count($headers), null)) ?: [];
            }, $lines);
        };

        $pgbouncerOverview = function () use ($pgbouncerRows): array {
            $config = config('database.connections.'.config('database.default'), []);
            try {
                $version = $pgbouncerRows('SHOW VERSION')[0]['version'] ?? '-';
                $pools = $pgbouncerRows('SHOW POOLS');
                $stats = collect($pgbouncerRows('SHOW STATS'))->firstWhere('database', $config['database'] ?? 'cbt_system') ?? [];
                $pool = collect($pools)->firstWhere('database', $config['database'] ?? 'cbt_system') ?? ($pools[0] ?? []);

                return [
                    'name' => 'PgBouncer',
                    'host' => $config['host'] ?? '-',
                    'port' => $config['port'] ?? '-',
                    'status' => 'online',
                    'version' => $version,
                    'database' => $pool['database'] ?? ($config['database'] ?? '-'),
                    'pool_mode' => $pool['pool_mode'] ?? '-',
                    'clients_active' => (int) ($pool['cl_active'] ?? 0),
                    'clients_waiting' => (int) ($pool['cl_waiting'] ?? 0),
                    'servers_active' => (int) ($pool['sv_active'] ?? 0),
                    'servers_idle' => (int) ($pool['sv_idle'] ?? 0),
                    'max_wait' => (int) ($pool['maxwait'] ?? 0),
                    'avg_query_ms' => isset($stats['avg_query_time']) ? round(((int) $stats['avg_query_time']) / 1000, 2) : null,
                    'avg_wait_ms' => isset($stats['avg_wait_time']) ? round(((int) $stats['avg_wait_time']) / 1000, 2) : null,
                    'total_queries' => (int) ($stats['total_query_count'] ?? 0),
                    'total_xacts' => (int) ($stats['total_xact_count'] ?? 0),
                    'message' => 'Connected',
                ];
            } catch (\Throwable $e) {
                return [
                    'name' => 'PgBouncer',
                    'host' => $config['host'] ?? '-',
                    'port' => $config['port'] ?? '-',
                    'status' => 'offline',
                    'version' => '-',
                    'database' => $config['database'] ?? '-',
                    'pool_mode' => '-',
                    'clients_active' => null,
                    'clients_waiting' => null,
                    'servers_active' => null,
                    'servers_idle' => null,
                    'max_wait' => null,
                    'avg_query_ms' => null,
                    'avg_wait_ms' => null,
                    'total_queries' => null,
                    'total_xacts' => null,
                    'message' => $e->getMessage(),
                ];
            }
        };

        $cachedPgbouncerOverview = function () use ($pgbouncerOverview): array {
            try {
                return \Illuminate\Support\Facades\Cache::remember('monitoring:pgbouncer:overview', 10, $pgbouncerOverview);
            } catch (\Throwable $e) {
                return $pgbouncerOverview();
            }
        };

        return response()->json([
            'total_siswa' => $totalSiswa,
            'ujian_berlangsung' => $ujianBerlangsung,
            'peserta_aktif' => $pesertaAktif,
            'siswa_selesai' => $siswaSelesai,
            'total_paket' => $totalPaket,
            'updated_at' => now('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'overview' => [
                'main_server' => $serverOverview(),
                'primary_db' => $databaseOverview('pgsql_primary_direct', 'DB Utama'),
                'secondary_db' => $databaseOverview('pgsql_standby', 'Server 2 DB'),
                'redis_primary' => $redisOverview('default', 'Redis Primary'),
                'pgbouncer' => $cachedPgbouncerOverview(),
            ],
        ]);
    });
    Route::get('/monitoring/sessions', function() {
        abort_unless(Auth::user()?->can('monitor-exams') || in_array(Auth::user()?->role, ['SuperAdmin', 'Admin', 'Pengawas'], true), 403);
        $sessions = DB::table('sesi_ujians as s')
            ->join('users as u','u.id','=','s.user_id')
            ->join('jadwal_ujians as j','j.id','=','s.jadwal_ujian_id')
            ->join('master_ujians as m','m.id','=','j.master_ujian_id')
            ->select('s.*','u.name','u.username','j.durasi_menit','m.judul as mapel')
            ->orderByDesc('s.id')
            ->get();

        if ($sessions->isEmpty()) {
            return response()->json([]);
        }

        $sessionIds = $sessions->pluck('id')->all();

        $answeredCounts = DB::table('jawaban_siswas')
            ->whereIn('sesi_ujian_id', $sessionIds)
            ->selectRaw('sesi_ujian_id, count(*) as cnt')
            ->groupBy('sesi_ujian_id')
            ->pluck('cnt', 'sesi_ujian_id')
            ->all();

        $totalCounts = DB::table('sesi_ujian_soals')
            ->whereIn('sesi_ujian_id', $sessionIds)
            ->selectRaw('sesi_ujian_id, count(*) as cnt')
            ->groupBy('sesi_ujian_id')
            ->pluck('cnt', 'sesi_ujian_id')
            ->all();

        $className = DB::table('kelas_aktifs')->get()->keyBy('id');

        $userIds = $sessions->pluck('user_id')->unique()->all();
        $userKelasMap = DB::table('users')->whereIn('id',$userIds)->pluck('kelas_aktif_id','id')->all();

        // PERF-3 FIX: Capture now() once outside the map loop instead of calling it per iteration.
        // Also fix now()->parse() — that is not a real Carbon method; use Carbon::parse() instead.
        $now = now();

        $result = $sessions->map(function($x) use ($answeredCounts, $totalCounts, $className, $userKelasMap, $now){
            $classId = $userKelasMap[$x->user_id] ?? null;
            $x->kelas = $classId ? ($className->get($classId)?->nama_kelas ?? '-') : '-';
            $x->terjawab = $answeredCounts[$x->id] ?? 0;
            $x->total_soal = $totalCounts[$x->id] ?? 0;
            $x->online = $x->last_seen_at && $now->diffInSeconds($x->last_seen_at) <= 30;

            $deadline = \Carbon\Carbon::parse($x->waktu_login)->addMinutes($x->durasi_menit);
            $remSeconds = max(0, $now->diffInSeconds($deadline, false));
            $min = floor($remSeconds / 60);
            $sec = $remSeconds % 60;
            $x->sisa_waktu = sprintf('%02d:%02d', $min, $sec);

            $x->device = json_decode($x->device_info, true)['user_agent'] ?? '-';
            return $x;
        });

        return response()->json($result);
    });
    // Guru: jadwal ujian yang paket soalnya dibuat oleh guru ini
    Route::get('/kelola/guru/jadwal-terkait', function() {
        $guruId = Auth::id();
        $rows = DB::table('jadwal_ujians as j')
            ->join('master_ujians as m', 'm.id', '=', 'j.master_ujian_id')
            ->join('paket_soals as p', 'p.id', '=', 'm.paket_soal_id')
            ->join('mata_pelajarans as mp', 'mp.id', '=', 'p.mata_pelajaran_id')
            ->where('p.pembuat_user_id', $guruId)
            ->orderByDesc('j.waktu_mulai')
            ->limit(20)
            ->get([
                'j.id', 'm.judul', 'mp.nama_mapel',
                'j.waktu_mulai', 'j.waktu_selesai',
                'j.waktu_mulai as waktu_mulai_raw',
                'j.waktu_selesai as waktu_selesai_raw',
            ])
            ->map(function ($r) {
                $r->waktu_mulai   = \Carbon\Carbon::parse($r->waktu_mulai)->timezone('Asia/Jakarta')->format('d M Y H:i');
                $r->waktu_selesai = \Carbon\Carbon::parse($r->waktu_selesai)->timezone('Asia/Jakarta')->format('H:i');
                return $r;
            });
        return response()->json(['success' => true, 'data' => $rows]);
    });

    Route::get('/auth/user', function() {
        // SECURITY: Only return safe fields
        $u = Auth::user();
        return response()->json(['id'=>$u->id,'name'=>$u->name,'role'=>$u->role,'username'=>$u->username]);
    });

    // ── Vue SPA Catch-all ─────────────────────────────────────────
    Route::get('/vue/management/{any}', $managementPage)->where('any', '.*');
    Route::get('/vue/{any}', fn() => view('app'))->where('any', '.*');
});
