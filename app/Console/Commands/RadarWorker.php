<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class RadarWorker extends Command
{
    protected $signature = 'cbt:radar-worker {--sleep=2} {--max-iterations=0} {--max-memory=256}';
    protected $description = 'Worker untuk mengumpulkan data sesi ujian aktif ke Redis (Radar Nilai Real-Time)';

    public function handle()
    {
        $this->info("Memulai CBT Radar Worker...");

        $sleep = max(1, (int) $this->option('sleep'));
        $maxIterations = max(0, (int) $this->option('max-iterations'));
        $maxMemory = max(32, (int) $this->option('max-memory'));
        $iteration = 0;
        $running = true;

        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, function () use (&$running): void {
                $running = false;
            });
            pcntl_signal(SIGINT, function () use (&$running): void {
                $running = false;
            });
        }

        while ($running) {
            try {
                // Radar hanya menampilkan siswa yang sudah memulai ujian.
                // Sesi dibuat oleh ExamService::start() setelah token valid/ujian dikonfirmasi.
                $sessions = DB::table('sesi_ujians as s')
                    ->join('users as u', 'u.id', '=', 's.user_id')
                    ->leftJoin('kelas_aktifs as k', 'k.id', '=', 'u.kelas_aktif_id')
                    ->join('jadwal_ujians as j', 'j.id', '=', 's.jadwal_ujian_id')
                    ->join('master_ujians as m', 'm.id', '=', 'j.master_ujian_id')
                    ->where('s.status', 'aktif')
                    ->select(
                        's.id as sesi_id',
                        'u.name as nama',
                        'k.nama_kelas as kelas',
                        's.last_seen_at',
                        's.waktu_login',
                        'm.judul as ujian'
                    )
                    ->orderByDesc('s.waktu_login')
                    ->get();

                $radarData = [];

                if ($sessions->isNotEmpty()) {
                    $sessionIds = $sessions->pluck('sesi_id')->all();

                    $jawabanStats = DB::table('jawaban_siswas')
                        ->whereIn('sesi_ujian_id', $sessionIds)
                        ->selectRaw('sesi_ujian_id, count(*) as progress, sum(skor) as score')
                        ->groupBy('sesi_ujian_id')
                        ->get()
                        ->keyBy('sesi_ujian_id');

                    $soalStats = DB::table('sesi_ujian_soals')
                        ->whereIn('sesi_ujian_id', $sessionIds)
                        ->selectRaw('sesi_ujian_id, count(*) as total_soal')
                        ->groupBy('sesi_ujian_id')
                        ->get()
                        ->keyBy('sesi_ujian_id');

                    foreach ($sessions as $s) {
                        $stats = $jawabanStats->get($s->sesi_id);
                        $soal = $soalStats->get($s->sesi_id);

                        $radarData[] = [
                            'id' => $s->sesi_id,
                            'nama' => $s->nama,
                            'kelas' => $s->kelas ?? '-',
                            'ujian' => $s->ujian,
                            'status' => $s->last_seen_at && now()->diffInSeconds($s->last_seen_at) <= 30 ? 'Online' : 'Offline',
                            'progress' => $stats ? (int) $stats->progress : 0,
                            'total_soal' => $soal ? (int) $soal->total_soal : 0,
                            'score' => $stats ? (float) $stats->score : 0.0,
                            'isUpdated' => false,
                        ];
                    }

                    // Sort active sessions by score desc.
                    usort($radarData, function($a, $b) {
                        return $b['score'] <=> $a['score'];
                    });
                }

                Redis::set('cbt:radar:live', json_encode($radarData));

            } catch (\Throwable $e) {
                $this->error("Error: " . $e->getMessage());
            }

            $iteration++;
            if ($maxIterations > 0 && $iteration >= $maxIterations) {
                break;
            }

            $memoryMb = memory_get_usage(true) / 1024 / 1024;
            if ($memoryMb >= $maxMemory) {
                $this->warn("Radar worker berhenti: memory {$memoryMb}MB melewati limit {$maxMemory}MB.");
                break;
            }

            sleep($sleep);
        }

        $this->info("CBT Radar Worker berhenti.");

        return self::SUCCESS;
    }
}
