<?php

namespace App\Jobs;

use App\Services\ExamService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PersistSessionAnswersSnapshot implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 30;
    public int $tries = 5;
    public int $maxExceptions = 5;
    public array $backoff = [3, 10, 30, 60, 120];
    public int $timeout = 60;

    public function __construct(public int $sessionId)
    {
    }

    public function uniqueId(): string
    {
        return (string) $this->sessionId;
    }

    public function handle(ExamService $service): void
    {
        $service->flushAll($this->sessionId);
    }

    public function failed(\Throwable $exception): void
    {
        Log::critical('PersistSessionAnswersSnapshot failed', [
            'session_id' => $this->sessionId,
            'error' => $exception->getMessage(),
        ]);

        try {
            DB::table('audit_logs')->insert([
                'sesi_ujian_id' => $this->sessionId,
                'action' => 'answer_session_persist_failed',
                'payload' => json_encode(['error' => $exception->getMessage()]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $auditException) {
            Log::error('Unable to record failed session answer persist audit', [
                'session_id' => $this->sessionId,
                'error' => $auditException->getMessage(),
            ]);
        }
    }
}
