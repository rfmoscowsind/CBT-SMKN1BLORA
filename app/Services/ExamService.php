<?php

namespace App\Services;

use App\Jobs\PersistAnswerSnapshot;
use App\Jobs\PersistSessionAnswersSnapshot;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class ExamService
{
    /**
     * Ambil semua jadwal ujian yang relevan untuk user (siswa/guru/panitia).
     */
    public function schedulesFor(User $user)
    {
        $q = DB::table('jadwal_ujians as j')
            ->join('master_ujians as m', 'm.id', '=', 'j.master_ujian_id')
            ->join('paket_soals as p', 'p.id', '=', 'm.paket_soal_id')
            ->join('mata_pelajarans as mp', 'mp.id', '=', 'p.mata_pelajaran_id')
            ->whereNull('j.diarsipkan_at')
            ->select('j.*', 'm.judul', 'm.tampilkan_nilai_akhir', 'm.hasil_visibilitas', 'p.id as paket_soal_id', 'mp.nama_mapel');

        if ($user->role === 'Siswa') {
            $dayStart = now('Asia/Jakarta')->startOfDay()->utc();
            $dayEnd   = now('Asia/Jakarta')->endOfDay()->utc();
            $q->join('jadwal_ujian_kelas as jk', 'jk.jadwal_ujian_id', '=', 'j.id')
              ->where('jk.kelas_aktif_id', $user->kelas_aktif_id)
              ->where('j.waktu_mulai', '<=', $dayEnd)
              ->where('j.waktu_selesai', '>=', $dayStart);
        }

        return $q->orderByDesc('j.waktu_mulai')->get();
    }

    /**
     * Mulai ujian untuk seorang siswa.
     *
     * FIX #1 (issuegpt.md): Seluruh pembuatan sesi di-wrap dalam DB::transaction()
     * + lockForUpdate() untuk mencegah race condition saat siswa double-click,
     * browser retry, atau request paralel masuk bersamaan.
     * Unique index (user_id, jadwal_ujian_id) tetap sebagai safety net DB level.
     *
     * FIX (Issue #7): Batch-query semua opsi sekaligus, lalu batch-insert ke sesi_ujian_soals.
     * Sebelum: 50 soal = 50 SELECT + 50 INSERT = 100 query.
     * Sesudah: 1 SELECT opsi + 1 batch INSERT = 2 query tambahan.
     */
    public function start(User $user, int $jadwalId, ?string $token, string $ip, ?string $ua): object
    {
        $j = DB::table('jadwal_ujians')->where('id', $jadwalId)->first();
        abort_unless($j, 404);
        abort_if(
            now()->lt($j->waktu_mulai) || now()->gt($j->waktu_selesai),
            410,
            'Waktu ujian tidak aktif.'
        );
        abort_if($user->status_kehadiran !== 'hadir', 403, 'Status kehadiran tidak memenuhi syarat.');

        if ($j->gunakan_token) {
            abort_if(
                Str::upper(trim((string) $token)) !== Str::upper(trim((string) $j->token)),
                403,
                'Token ujian tidak sesuai.'
            );
        }

        abort_unless(
            DB::table('jadwal_ujian_kelas')
                ->where(['jadwal_ujian_id' => $j->id, 'kelas_aktif_id' => $user->kelas_aktif_id])
                ->exists(),
            403,
            'Kelas tidak terdaftar.'
        );

        // FIX #1: Wrap dalam transaction + lockForUpdate untuk mencegah race condition.
        try {
            return DB::transaction(function () use ($user, $j, $ip, $ua) {
            // Cek ulang di dalam transaksi dengan row-level lock
            $existing = DB::table('sesi_ujians')
                ->where('user_id', $user->id)
                ->where('jadwal_ujian_id', $j->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if (! in_array($existing->status, ['reset', 'invalidated'], true)) {
                    $this->event($existing->id, 'login_return', ['ip' => $ip, 'user_agent' => $ua]);
                    return $existing;
                }

                $sessionId = (int) $existing->id;
                Redis::del("queue_jawaban:$sessionId");
                $this->forgetHotCache("session_soals:$sessionId");
                DB::table('jawaban_siswas')->where('sesi_ujian_id', $sessionId)->delete();
                DB::table('sesi_ujian_soals')->where('sesi_ujian_id', $sessionId)->delete();
                DB::table('sesi_ujians')->where('id', $sessionId)->update([
                    'waktu_login'  => now(),
                    'waktu_submit' => null,
                    'status'       => 'aktif',
                    'ip_address'   => $ip,
                    'device_info'  => json_encode(['user_agent' => $ua]),
                    'nilai_akhir'  => null,
                    'last_seen_at' => now(),
                    'updated_at'   => now(),
                ]);
            } else {
                // Buat sesi baru (aman dari duplikat karena sudah di dalam lock)
                $sessionId = DB::table('sesi_ujians')->insertGetId([
                    'user_id'         => $user->id,
                    'jadwal_ujian_id' => $j->id,
                    'waktu_login'     => now(),
                    'status'          => 'aktif',
                    'ip_address'      => $ip,
                    'device_info'     => json_encode(['user_agent' => $ua]),
                    'last_seen_at'    => now(),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }

            // Ambil ID soal dari paket
            $soalIds = DB::table('jadwal_ujians as j')
                ->join('master_ujians as m', 'm.id', '=', 'j.master_ujian_id')
                ->join('bank_soals as b', 'b.paket_soal_id', '=', 'm.paket_soal_id')
                ->where('j.id', $j->id)
                ->orderBy('b.urutan')
                ->pluck('b.id')
                ->all();

            $master = DB::table('jadwal_ujians as j')
                ->join('master_ujians as m', 'm.id', '=', 'j.master_ujian_id')
                ->where('j.id', $j->id)
                ->first();

            if ($master->acak_soal) {
                shuffle($soalIds);
            }

            // Batch-fetch semua opsi sekaligus — 1 query, bukan N queries
            $allOptions = DB::table('opsi_jawabans')
                ->whereIn('bank_soal_id', $soalIds)
                ->get(['id', 'bank_soal_id'])
                ->groupBy('bank_soal_id');

            // Siapkan semua row lalu batch-insert — 1 INSERT, bukan N INSERT
            $rows = [];
            foreach ($soalIds as $i => $soalId) {
                $opsi = $allOptions->get($soalId, collect())->pluck('id')->toArray();
                if ($master->acak_opsi) {
                    shuffle($opsi);
                }
                $rows[] = [
                    'sesi_ujian_id' => $sessionId,
                    'bank_soal_id'  => $soalId,
                    'nomor_soal'    => $i + 1,
                    'opsi_order'    => json_encode($opsi),
                ];
            }

            if (!empty($rows)) {
                DB::table('sesi_ujian_soals')->insert($rows);
            }

            $this->forgetHotCache("session_soals:$sessionId");

            $this->event($sessionId, $existing ? 'login_after_reset' : 'login', ['ip' => $ip, 'user_agent' => $ua]);

            return DB::table('sesi_ujians')->find($sessionId);
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23505') {
                throw $exception;
            }

            $existing = DB::table('sesi_ujians')
                ->where(['user_id' => $user->id, 'jadwal_ujian_id' => $j->id])
                ->first();
            abort_unless($existing, 409, 'Sesi ujian sedang dibuat. Coba ulangi.');

            $this->event($existing->id, 'login_return_after_duplicate', ['ip' => $ip, 'user_agent' => $ua]);

            return $existing;
        }
    }

    /**
     * Catat event sesi ujian ke tabel session_events.
     */
    public function event(int $sessionId, string $type, array $data = []): void
    {
        DB::table('session_events')->insert([
            'sesi_ujian_id' => $sessionId,
            'event_type'    => $type,
            'event_data'    => json_encode($data),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    /**
     * Hitung sisa waktu ujian dalam detik.
     *
     * Query langsung agar tidak ada state container yang stale di worker Octane.
     */
    public function remaining(object $s): int
    {
        $j = DB::table('jadwal_ujians')->find($s->jadwal_ujian_id);
        abort_unless($j, 404, 'Jadwal ujian tidak ditemukan.');

        $deadline = min(
            now()->parse($j->waktu_selesai),
            now()->parse($s->waktu_login)->addMinutes($j->durasi_menit)
        );

        return max(0, now()->diffInSeconds($deadline, false));
    }

    /**
     * Simpan jawaban siswa ke Redis queue, lalu dispatch job persistensi.
     */
    public function save(object $s, int $soalId, ?int $opsiId, ?string $essay, ?string $clientAt = null): array
    {
        abort_if($s->status !== 'aktif' || $this->remaining($s) <= 0, 410, 'Sesi ujian selesai.');

        $q = $this->questionMetadata($soalId);
        $sessionSoalIds = $this->sessionQuestionIds($s);

        $existsInSession = $q && in_array($soalId, $sessionSoalIds, true);
        abort_unless($existsInSession, 403);

        if ($opsiId !== null) {
            $validOptionIds = $this->optionIdsForQuestion($soalId);

            abort_unless(
                in_array($opsiId, $validOptionIds, true),
                422,
                'Opsi jawaban tidak sesuai soal.'
            );
        }

        $received = now()->toISOString();
        $payload  = [
            'sesi_ujian_id'     => $s->id,
            'user_id'           => $s->user_id,
            'bank_soal_id'      => $q->id,
            'opsi_jawaban_id'   => $opsiId,
            'jawaban_essay'     => $essay,
            'tipe_soal'         => $q->tipe_soal,
            'client_updated_at' => $clientAt,
            'server_updated_at' => $received,
        ];

        $this->bufferAnswer($s, $q, $payload);

        return ['server_updated_at' => $received];
    }

    /**
     * Simpan banyak jawaban dari sync/reconnect dengan satu Redis pipeline dan satu flush job.
     */
    public function saveMany(object $s, array $answers): array
    {
        abort_if($s->status !== 'aktif' || $this->remaining($s) <= 0, 410, 'Sesi ujian selesai.');

        $answers = array_values(array_filter($answers, 'is_array'));
        $received = now()->toISOString();
        $sessionSoalIds = $this->sessionQuestionIds($s);
        $questionIds = array_values(array_unique(array_map(
            fn (array $answer) => (int) ($answer['bank_soal_id'] ?? 0),
            $answers
        )));
        $questionIds = array_values(array_filter($questionIds));

        if (empty($questionIds)) {
            return ['server_updated_at' => $received, 'saved' => 0];
        }

        $questions = DB::table('bank_soals')
            ->whereIn('id', $questionIds)
            ->get(['id', 'tipe_soal', 'bobot_nilai'])
            ->keyBy('id');

        $optionIds = DB::table('opsi_jawabans')
            ->whereIn('bank_soal_id', $questionIds)
            ->get(['id', 'bank_soal_id'])
            ->groupBy('bank_soal_id')
            ->map(fn ($rows) => $rows->pluck('id')->map(fn ($id) => (int) $id)->all())
            ->all();

        $payloads = [];
        $prepared = [];
        $processed = 0;

        foreach ($answers as $answer) {
            $soalId = (int) ($answer['bank_soal_id'] ?? 0);
            if ($soalId <= 0) {
                continue;
            }

            $q = $questions->get($soalId);
            abort_unless($q && in_array($soalId, $sessionSoalIds, true), 403);

            $opsiId = $answer['opsi_jawaban_id'] ?? null;
            $opsiId = $opsiId !== null ? (int) $opsiId : null;
            if ($opsiId !== null) {
                abort_unless(
                    in_array($opsiId, $optionIds[$soalId] ?? [], true),
                    422,
                    'Opsi jawaban tidak sesuai soal.'
                );
            }

            $payload = [
                'sesi_ujian_id'     => $s->id,
                'user_id'           => $s->user_id,
                'bank_soal_id'      => (int) $q->id,
                'opsi_jawaban_id'   => $opsiId,
                'jawaban_essay'     => $answer['jawaban_essay'] ?? null,
                'tipe_soal'         => $q->tipe_soal,
                'client_updated_at' => $answer['client_updated_at'] ?? null,
                'server_updated_at' => $received,
            ];

            $field = (string) $q->id;
            $payloads[$field] = json_encode($payload);
            $prepared[$field] = [$q, $payload];
            $processed++;
        }

        if (empty($payloads)) {
            return ['server_updated_at' => $received, 'saved' => 0];
        }

        $key = "queue_jawaban:{$s->id}";

        try {
            Redis::pipeline(function ($pipe) use ($key, $payloads): void {
                foreach ($payloads as $field => $payload) {
                    $pipe->hset($key, $field, $payload);
                }
                $pipe->expire($key, 86400);
            });

            PersistSessionAnswersSnapshot::dispatch((int) $s->id)->onQueue('answers');
        } catch (\Throwable $exception) {
            Log::error('Redis answer buffer unavailable during batch save; persisting directly', [
                'session_id' => $s->id,
                'error' => $exception->getMessage(),
            ]);

            $correctOptions = $this->correctOptionIdsForQuestions($questionIds);

            foreach ($prepared as [$q, $payload]) {
                $this->persistPreparedAnswer(
                    (int) $s->id,
                    (int) $q->id,
                    $payload,
                    $q,
                    $correctOptions[(int) $q->id] ?? null
                );
            }
        }

        return ['server_updated_at' => $received, 'saved' => $processed];
    }

    private function rememberHotValue(string $key, int $seconds, callable $resolver): mixed
    {
        try {
            return Cache::remember($key, $seconds, $resolver);
        } catch (\Throwable $exception) {
            Log::warning('Cache unavailable for exam hot path', [
                'key' => $key,
                'error' => $exception->getMessage(),
            ]);

            return $resolver();
        }
    }

    private function forgetHotCache(string $key): void
    {
        try {
            Cache::forget($key);
        } catch (\Throwable $exception) {
            Log::warning('Cache forget failed for exam hot path', [
                'key' => $key,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function questionMetadata(int $soalId): ?object
    {
        $qData = $this->rememberHotValue("bank_soal:{$soalId}:v2", 7200, function () use ($soalId) {
            $row = DB::table('bank_soals')->find($soalId);

            return $row ? [
                'id' => (int) $row->id,
                'tipe_soal' => $row->tipe_soal,
                'bobot_nilai' => $row->bobot_nilai,
            ] : null;
        });

        return $qData ? (object) $qData : null;
    }

    private function sessionQuestionIds(object $s): array
    {
        return array_map('intval', $this->rememberHotValue("session_soals:{$s->id}", 7200, function () use ($s) {
            return DB::table('sesi_ujian_soals')
                ->where('sesi_ujian_id', $s->id)
                ->pluck('bank_soal_id')
                ->all();
        }));
    }

    private function optionIdsForQuestion(int $soalId): array
    {
        return array_map('intval', $this->rememberHotValue("opsi_soal:{$soalId}", 7200, function () use ($soalId) {
            return DB::table('opsi_jawabans')
                ->where('bank_soal_id', $soalId)
                ->pluck('id')
                ->all();
        }));
    }

    private function bufferAnswer(object $s, object $q, array $payload): void
    {
        try {
            Redis::hset("queue_jawaban:{$s->id}", (string) $q->id, json_encode($payload));
            Redis::expire("queue_jawaban:{$s->id}", 86400);
            PersistAnswerSnapshot::dispatch((int) $s->id, (int) $q->id)->onQueue('answers');
        } catch (\Throwable $exception) {
            Log::error('Redis answer buffer unavailable; persisting directly', [
                'session_id' => $s->id,
                'question_id' => $q->id,
                'error' => $exception->getMessage(),
            ]);

            $this->persistPreparedAnswer(
                (int) $s->id,
                (int) $q->id,
                $payload,
                $q
            );
        }
    }

    private function correctOptionIdsForQuestions(array $questionIds): array
    {
        if (empty($questionIds)) {
            return [];
        }

        return DB::table('opsi_jawabans')
            ->whereIn('bank_soal_id', $questionIds)
            ->where('is_benar', true)
            ->pluck('id', 'bank_soal_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function answerScore(object $question, ?int $optionId, ?int $knownCorrectOptionId = null): array
    {
        $score = 0;
        $status = 'pending_manual';

        if ($question->tipe_soal === 'PG') {
            if ($knownCorrectOptionId !== null) {
                $ok = $optionId !== null && (int) $optionId === $knownCorrectOptionId;
            } else {
                $ok = $optionId !== null && DB::table('opsi_jawabans')
                    ->where(['id' => $optionId, 'bank_soal_id' => $question->id, 'is_benar' => true])
                    ->exists();
            }

            $score = $ok ? $question->bobot_nilai : 0;
            $status = 'auto_scored';
        }

        return [$score, $status];
    }

    private function persistPreparedAnswer(int $sessionId, int $soalId, array $answer, object $question, ?int $knownCorrectOptionId = null): void
    {
        [$score, $status] = $this->answerScore(
            $question,
            isset($answer['opsi_jawaban_id']) ? (int) $answer['opsi_jawaban_id'] : null,
            $knownCorrectOptionId
        );

        if ($this->persistAnswerIfNewer($sessionId, $soalId, $answer, $question, $score, $status)) {
            $this->recordAnswerAuditSafely($sessionId, $soalId, $answer);
        }
    }

    /**
     * Hapus entry Redis jika masih sama dengan yang di-pass (atomic via Lua).
     */
    private function forgetIfCurrent(string $key, string $field, string $raw): void
    {
        Redis::eval(
            "if redis.call('hget',KEYS[1],ARGV[1])==ARGV[2] then return redis.call('hdel',KEYS[1],ARGV[1]) end return 0",
            1,
            $key,
            $field,
            $raw
        );
    }

    public function flushOne(int $sessionId, int $soalId): void
    {
        if (! $this->isFlushableSession($sessionId)) {
            Redis::del("queue_jawaban:$sessionId");
            return;
        }

        $raw = Redis::hget("queue_jawaban:$sessionId", (string) $soalId);
        if (!$raw) {
            return;
        }

        $a = json_decode($raw, true);
        $q = DB::table('bank_soals')->find($soalId);
        if (!$q) {
            $this->forgetIfCurrent("queue_jawaban:$sessionId", (string) $soalId, $raw);
            return;
        }

        $this->persistPreparedAnswer($sessionId, $soalId, $a, $q);

        $this->forgetIfCurrent("queue_jawaban:$sessionId", (string) $soalId, $raw);
    }

    public function flushAll(int $sessionId): void
    {
        if (! $this->isFlushableSession($sessionId)) {
            Redis::del("queue_jawaban:$sessionId");
            return;
        }

        $queue = Redis::hgetall("queue_jawaban:$sessionId");
        if (empty($queue)) {
            return;
        }

        $soalIds = array_map('intval', array_keys($queue));

        // Fetch all bank_soals for these IDs (1 query)
        $questions = DB::table('bank_soals')->whereIn('id', $soalIds)->get()->keyBy('id');

        // Fetch all correct options for PG questions (1 query)
        $correctOptions = DB::table('opsi_jawabans')
            ->whereIn('bank_soal_id', $soalIds)
            ->where('is_benar', true)
            ->pluck('id', 'bank_soal_id')
            ->all();

        $rowsToPersist = [];
        $forgetFields = [];

        foreach ($queue as $soalIdStr => $raw) {
            $soalId = (int)$soalIdStr;
            $a = json_decode($raw, true);
            $q = $questions->get($soalId);

            if (!$q || ! is_array($a)) {
                $forgetFields[$soalIdStr] = $raw;
                continue;
            }

            $score  = 0;
            $status = 'pending_manual';
            if ($q->tipe_soal === 'PG') {
                $correctOptId = $correctOptions[$q->id] ?? null;
                $answerOptId = isset($a['opsi_jawaban_id']) ? (int) $a['opsi_jawaban_id'] : null;
                $ok = $correctOptId && $answerOptId !== null && $answerOptId === (int)$correctOptId;
                $score  = $ok ? $q->bobot_nilai : 0;
                $status = 'auto_scored';
            }

            $rowsToPersist[] = [$soalId, $a, $q, $score, $status];

            $forgetFields[$soalIdStr] = $raw;
        }

        DB::transaction(function () use ($rowsToPersist, $sessionId) {
            $persistedIds = array_flip($this->persistAnswersIfNewerBulk($sessionId, $rowsToPersist));

            foreach ($rowsToPersist as [$soalId, $a]) {
                if (isset($persistedIds[$soalId])) {
                    $this->recordAnswerAuditSafely($sessionId, $soalId, $a);
                }
            }
        });

        foreach ($forgetFields as $field => $raw) {
            $this->forgetIfCurrent("queue_jawaban:$sessionId", $field, $raw);
        }
    }

    private function isFlushableSession(int $sessionId): bool
    {
        $status = DB::table('sesi_ujians')->where('id', $sessionId)->value('status');

        return in_array($status, ['aktif', 'selesai', 'terkunci'], true);
    }

    private function persistAnswersIfNewerBulk(int $sessionId, array $rowsToPersist): array
    {
        if (empty($rowsToPersist)) {
            return [];
        }

        if (DB::getDriverName() !== 'pgsql') {
            $persisted = [];
            foreach ($rowsToPersist as [$soalId, $answer, $question, $score, $status]) {
                if ($this->persistAnswerIfNewer($sessionId, $soalId, $answer, $question, $score, $status)) {
                    $persisted[] = (int) $soalId;
                }
            }

            return $persisted;
        }

        $now = now();
        $placeholders = [];
        $bindings = [];

        foreach ($rowsToPersist as [$soalId, $answer, $question, $score, $status]) {
            $placeholders[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            array_push(
                $bindings,
                $sessionId,
                $soalId,
                $answer['opsi_jawaban_id'] ?? null,
                $answer['jawaban_essay'] ?? null,
                $question->tipe_soal,
                $score,
                $status,
                $answer['client_updated_at'] ?? null,
                $answer['server_updated_at'] ?? null,
                $now,
                $now
            );
        }

        $rows = DB::select(
            "
            INSERT INTO jawaban_siswas (
                sesi_ujian_id, bank_soal_id, opsi_jawaban_id, jawaban_essay,
                tipe_soal, skor, scoring_status, client_updated_at,
                server_updated_at, created_at, updated_at
            )
            VALUES ".implode(', ', $placeholders)."
            ON CONFLICT (sesi_ujian_id, bank_soal_id)
            DO UPDATE SET
                opsi_jawaban_id = EXCLUDED.opsi_jawaban_id,
                jawaban_essay = EXCLUDED.jawaban_essay,
                tipe_soal = EXCLUDED.tipe_soal,
                skor = EXCLUDED.skor,
                scoring_status = EXCLUDED.scoring_status,
                client_updated_at = EXCLUDED.client_updated_at,
                server_updated_at = EXCLUDED.server_updated_at,
                updated_at = EXCLUDED.updated_at
            WHERE jawaban_siswas.server_updated_at IS NULL
               OR EXCLUDED.server_updated_at >= jawaban_siswas.server_updated_at
            RETURNING bank_soal_id
            ",
            $bindings
        );

        return array_map(fn ($row) => (int) $row->bank_soal_id, $rows);
    }

    private function persistAnswerIfNewer(int $sessionId, int $soalId, array $answer, object $question, float|int $score, string $status): bool
    {
        $now = now();

        if (DB::getDriverName() === 'pgsql') {
            $rows = DB::select(
                "
                INSERT INTO jawaban_siswas (
                    sesi_ujian_id, bank_soal_id, opsi_jawaban_id, jawaban_essay,
                    tipe_soal, skor, scoring_status, client_updated_at,
                    server_updated_at, created_at, updated_at
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON CONFLICT (sesi_ujian_id, bank_soal_id)
                DO UPDATE SET
                    opsi_jawaban_id = EXCLUDED.opsi_jawaban_id,
                    jawaban_essay = EXCLUDED.jawaban_essay,
                    tipe_soal = EXCLUDED.tipe_soal,
                    skor = EXCLUDED.skor,
                    scoring_status = EXCLUDED.scoring_status,
                    client_updated_at = EXCLUDED.client_updated_at,
                    server_updated_at = EXCLUDED.server_updated_at,
                    updated_at = EXCLUDED.updated_at
                WHERE jawaban_siswas.server_updated_at IS NULL
                   OR EXCLUDED.server_updated_at >= jawaban_siswas.server_updated_at
                RETURNING id
                ",
                [
                    $sessionId,
                    $soalId,
                    $answer['opsi_jawaban_id'] ?? null,
                    $answer['jawaban_essay'] ?? null,
                    $question->tipe_soal,
                    $score,
                    $status,
                    $answer['client_updated_at'] ?? null,
                    $answer['server_updated_at'] ?? null,
                    $now,
                    $now,
                ]
            );

            return ! empty($rows);
        }

        $current = DB::table('jawaban_siswas')
            ->where(['sesi_ujian_id' => $sessionId, 'bank_soal_id' => $soalId])
            ->first();

        if (
            $current && $current->server_updated_at
            && ($answer['server_updated_at'] ?? null)
            && now()->parse($current->server_updated_at)->gt(now()->parse($answer['server_updated_at']))
        ) {
            return false;
        }

        DB::table('jawaban_siswas')->updateOrInsert(
            ['sesi_ujian_id' => $sessionId, 'bank_soal_id' => $soalId],
            [
                'opsi_jawaban_id'   => $answer['opsi_jawaban_id'] ?? null,
                'jawaban_essay'     => $answer['jawaban_essay'] ?? null,
                'tipe_soal'         => $question->tipe_soal,
                'skor'              => $score,
                'scoring_status'    => $status,
                'client_updated_at' => $answer['client_updated_at'] ?? null,
                'server_updated_at' => $answer['server_updated_at'] ?? null,
                'created_at'        => $now,
                'updated_at'        => $now,
            ]
        );

        return true;
    }

    private function recordAnswerAuditSafely(int $sessionId, int $soalId, array $answer): void
    {
        if (! config('app.audit_answer_saved', false)) {
            return;
        }

        try {
            $this->auditAnswerSaved($sessionId, $soalId, $answer);
        } catch (\Throwable $exception) {
            Log::warning('Answer audit log failed', [
                'session_id' => $sessionId,
                'question_id' => $soalId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function auditAnswerSaved(int $sessionId, int $soalId, array $answer): void
    {
        DB::table('audit_logs')->insert([
            'sesi_ujian_id' => $sessionId,
            'user_id'       => $answer['user_id'] ?? null,
            'action'        => 'answer_saved',
            'bank_soal_id'  => $soalId,
            'payload'       => json_encode([
                'opsi_id'           => $answer['opsi_jawaban_id'] ?? null,
                'client_updated_at' => $answer['client_updated_at'] ?? null,
                'server_updated_at' => $answer['server_updated_at'] ?? null,
            ]),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    /**
     * Submit (selesaikan) sesi ujian.
     *
     * FIX #4 (issuegpt.md): Wrap dalam DB::transaction() + lockForUpdate() untuk
     * mencegah double-submit saat: siswa klik selesai + auto-submit waktu habis terjadi
     * bersamaan, browser retry, atau request lain yang memicu ownedSession().
     */
    public function submit(object $s): object
    {
        return DB::transaction(function () use ($s) {
            $session = DB::table('sesi_ujians')
                ->where('id', $s->id)
                ->lockForUpdate()
                ->first();

            // Jika tidak ditemukan atau sudah selesai, tidak perlu proses ulang
            if (!$session || $session->status !== 'aktif') {
                return $session ?: $s;
            }

            try {
                $this->flushAll($session->id);
            } catch (\Throwable $exception) {
                Log::error('Unable to flush pending answers before submit', [
                    'session_id' => $session->id,
                    'error' => $exception->getMessage(),
                ]);

                abort(503, 'Jawaban pending belum bisa diproses. Coba kirim ulang beberapa saat lagi.');
            }

            $score = DB::table('jawaban_siswas')
                ->where('sesi_ujian_id', $session->id)
                ->sum('skor');

            // Tambah kondisi WHERE status='aktif' agar aman dari update ganda
            DB::table('sesi_ujians')
                ->where('id', $session->id)
                ->where('status', 'aktif')
                ->update([
                    'status'       => 'selesai',
                    'waktu_submit' => now(),
                    'nilai_akhir'  => $score,
                    'updated_at'   => now(),
                ]);

            $this->event($session->id, 'submit');

            return DB::table('sesi_ujians')->find($session->id);
        });
    }
}
