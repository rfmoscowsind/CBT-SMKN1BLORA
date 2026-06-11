<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class RefreshPgbouncerSnapshot extends Command
{
    protected $signature = 'monitoring:pgbouncer-snapshot {--ttl=120}';

    protected $description = 'Refresh PgBouncer monitoring snapshot into cache';

    private const CACHE_KEY = 'monitoring:pgbouncer:overview';

    public function handle(): int
    {
        $ttl = max(30, (int) $this->option('ttl'));
        $overview = $this->buildOverview();

        Cache::put(self::CACHE_KEY, $overview, $ttl);

        $this->line($overview['status'] === 'online'
            ? 'PGBOUNCER_SNAPSHOT_OK'
            : 'PGBOUNCER_SNAPSHOT_OFFLINE: '.$overview['message']);

        return self::SUCCESS;
    }

    private function buildOverview(): array
    {
        $config = config('database.connections.'.config('database.default'), []);

        try {
            $version = $this->rows('SHOW VERSION')[0]['version'] ?? '-';
            $pools = $this->rows('SHOW POOLS');
            $stats = collect($this->rows('SHOW STATS'))->firstWhere('database', $config['database'] ?? 'cbt_system') ?? [];
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
                'refreshed_at' => now('Asia/Jakarta')->format('Y-m-d H:i:s'),
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
                'refreshed_at' => now('Asia/Jakarta')->format('Y-m-d H:i:s'),
            ];
        }
    }

    private function rows(string $sql): array
    {
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
            throw new RuntimeException(trim((string) $stderr) ?: "psql exit code $exitCode");
        }

        $lines = array_values(array_filter(array_map('trim', explode("\n", trim((string) $stdout)))));
        if (count($lines) < 2) {
            return [];
        }

        $headers = explode("\t", array_shift($lines));

        return array_map(function (string $line) use ($headers) {
            $values = explode("\t", $line);

            return array_combine($headers, array_pad($values, count($headers), null)) ?: [];
        }, $lines);
    }
}
