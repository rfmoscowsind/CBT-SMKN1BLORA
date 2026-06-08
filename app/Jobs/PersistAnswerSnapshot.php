<?php

namespace App\Jobs;

use App\Services\ExamService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PersistAnswerSnapshot implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 30;

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
}
