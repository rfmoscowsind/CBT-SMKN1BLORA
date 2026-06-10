<?php

namespace App\Jobs;

use App\Services\ExamService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PersistAnswerSnapshot implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 30;
    public int $tries = 5;
    public int $maxExceptions = 5;
    public array $backoff = [3, 10, 30, 60, 120];
    public int $timeout = 30;

    public function __construct(public int $sessionId, public int $questionId)
    {
    }

    public function uniqueId(): string
    {
        return $this->sessionId.':'.$this->questionId;
    }

    public function handle(ExamService $service): void
    {
        $service->flushOne($this->sessionId, $this->questionId);
    }

    public function failed(\Throwable $exception): void
    {
        Log::critical('PersistAnswerSnapshot failed', [
            'session_id' => $this->sessionId,
            'question_id' => $this->questionId,
            'error' => $exception->getMessage(),
        ]);

        try {
            DB::table('audit_logs')->insert([
                'sesi_ujian_id' => $this->sessionId,
                'action' => 'answer_persist_failed',
                'bank_soal_id' => $this->questionId,
                'payload' => json_encode(['error' => $exception->getMessage()]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $auditException) {
            Log::error('Unable to record failed answer persist audit', [
                'session_id' => $this->sessionId,
                'question_id' => $this->questionId,
                'error' => $auditException->getMessage(),
            ]);
        }
    }
}
