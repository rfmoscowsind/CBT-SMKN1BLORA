<?php

namespace App\Services;

use App\Jobs\PersistAnswerSnapshot;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
        return DB::transaction(function () use ($user, $j, $ip, $ua) {
            // Cek ulang di dalam transaksi dengan row-level lock
            $existing = DB::table('sesi_ujians')
                ->where('user_id', $user->id)
                ->where('jadwal_ujian_id', $j->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $this->event($existing->id, 'login_return', ['ip' => $ip, 'user_agent' => $ua]);
                return $existing;
            }

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

            $this->event($sessionId, 'login', ['ip' => $ip, 'user_agent' => $ua]);

            return DB::table('sesi_ujians')->find($sessionId);
        });
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
     * FIX (Issue #6): Gunakan static cache per-request untuk menghindari query
     * duplikat DB::table('jadwal_ujians')->find() setiap kali remaining() dipanggil
     * (dipanggil di show(), save(), sync() — bisa 3x per request).
     */
    public function remaining(object $s): int
    {
        static $cache = [];

        $key = 'jadwal_' . $s->jadwal_ujian_id;
        if (!array_key_exists($key, $cache)) {
            $cache[$key] = DB::table('jadwal_ujians')->find($s->jadwal_ujian_id);
        }
        $j = $cache[$key];

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

        $qData = \Illuminate\Support\Facades\Cache::remember("bank_soal:{$soalId}:v2", 7200, function () use ($soalId) {
            $row = DB::table('bank_soals')->find($soalId);

            return $row ? [
                'id' => (int) $row->id,
                'tipe_soal' => $row->tipe_soal,
                'bobot_nilai' => $row->bobot_nilai,
            ] : null;
        });
        $q = $qData ? (object) $qData : null;

        $sessionSoalIds = \Illuminate\Support\Facades\Cache::remember("session_soals:{$s->id}", 7200, function () use ($s) {
            return DB::table('sesi_ujian_soals')
                ->where('sesi_ujian_id', $s->id)
                ->pluck('bank_soal_id')
                ->all();
        });

        $existsInSession = $q && in_array($soalId, $sessionSoalIds, true);
        abort_unless($existsInSession, 403);

        if ($opsiId !== null) {
            $validOptionIds = \Illuminate\Support\Facades\Cache::remember("opsi_soal:{$soalId}", 7200, function () use ($soalId) {
                return DB::table('opsi_jawabans')
                    ->where('bank_soal_id', $soalId)
                    ->pluck('id')
                    ->all();
            });

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

        Redis::hset("queue_jawaban:{$s->id}", (string) $q->id, json_encode($payload));
        Redis::expire("queue_jawaban:{$s->id}", 86400);
        PersistAnswerSnapshot::dispatch($s->id, $q->id)->onQueue('answers');

        return ['server_updated_at' => $received];
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

        $current = DB::table('jawaban_siswas')
            ->where(['sesi_ujian_id' => $sessionId, 'bank_soal_id' => $soalId])
            ->first();
        if (
            $current && $current->server_updated_at
            && now()->parse($current->server_updated_at)->gt(now()->parse($a['server_updated_at']))
        ) {
            $this->forgetIfCurrent("queue_jawaban:$sessionId", (string) $soalId, $raw);
            return;
        }

        $score  = 0;
        $status = 'pending_manual';
        if ($q->tipe_soal === 'PG') {
            $ok = DB::table('opsi_jawabans')
                ->where(['id' => $a['opsi_jawaban_id'], 'bank_soal_id' => $q->id, 'is_benar' => true])
                ->exists();
            $score  = $ok ? $q->bobot_nilai : 0;
            $status = 'auto_scored';
        }

        DB::table('jawaban_siswas')->updateOrInsert(
            ['sesi_ujian_id' => $sessionId, 'bank_soal_id' => $soalId],
            [
                'opsi_jawaban_id'   => $a['opsi_jawaban_id'],
                'jawaban_essay'     => $a['jawaban_essay'],
                'tipe_soal'         => $q->tipe_soal,
                'skor'              => $score,
                'scoring_status'    => $status,
                'client_updated_at' => $a['client_updated_at'],
                'server_updated_at' => $a['server_updated_at'],
                'created_at'        => now(),
                'updated_at'        => now(),
            ]
        );

        DB::table('audit_logs')->insert([
            'sesi_ujian_id' => $sessionId,
            'user_id'       => $a['user_id'],
            'action'        => 'answer_saved',
            'bank_soal_id'  => $soalId,
            'payload'       => json_encode([
                'opsi_id'           => $a['opsi_jawaban_id'],
                'client_updated_at' => $a['client_updated_at'],
                'server_updated_at' => $a['server_updated_at'],
            ]),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->forgetIfCurrent("queue_jawaban:$sessionId", (string) $soalId, $raw);
    }

    public function flushAll(int $sessionId): void
    {
        $queue = Redis::hgetall("queue_jawaban:$sessionId");
        if (empty($queue)) {
            return;
        }

        $soalIds = array_map('intval', array_keys($queue));

        // Fetch all bank_soals for these IDs (1 query)
        $questions = DB::table('bank_soals')->whereIn('id', $soalIds)->get()->keyBy('id');

        // Fetch existing jawaban_siswas for these IDs (1 query)
        $existing = DB::table('jawaban_siswas')
            ->where('sesi_ujian_id', $sessionId)
            ->whereIn('bank_soal_id', $soalIds)
            ->get()
            ->keyBy('bank_soal_id');

        // Fetch all correct options for PG questions (1 query)
        $correctOptions = DB::table('opsi_jawabans')
            ->whereIn('bank_soal_id', $soalIds)
            ->where('is_benar', true)
            ->pluck('id', 'bank_soal_id')
            ->all();

        $rowsToUpsert = [];
        $auditLogs = [];
        $forgetFields = [];

        foreach ($queue as $soalIdStr => $raw) {
            $soalId = (int)$soalIdStr;
            $a = json_decode($raw, true);
            $q = $questions->get($soalId);

            if (!$q) {
                $forgetFields[$soalIdStr] = $raw;
                continue;
            }

            $current = $existing->get($soalId);
            if (
                $current && $current->server_updated_at
                && now()->parse($current->server_updated_at)->gt(now()->parse($a['server_updated_at']))
            ) {
                $forgetFields[$soalIdStr] = $raw;
                continue;
            }

            $score  = 0;
            $status = 'pending_manual';
            if ($q->tipe_soal === 'PG') {
                $correctOptId = $correctOptions[$q->id] ?? null;
                $ok = $correctOptId && (int)$a['opsi_jawaban_id'] === (int)$correctOptId;
                $score  = $ok ? $q->bobot_nilai : 0;
                $status = 'auto_scored';
            }

            $rowsToUpsert[] = [
                'sesi_ujian_id'     => $sessionId,
                'bank_soal_id'      => $soalId,
                'opsi_jawaban_id'   => $a['opsi_jawaban_id'],
                'jawaban_essay'     => $a['jawaban_essay'],
                'tipe_soal'         => $q->tipe_soal,
                'skor'              => $score,
                'scoring_status'    => $status,
                'client_updated_at' => $a['client_updated_at'],
                'server_updated_at' => $a['server_updated_at'],
                'created_at'        => now(),
                'updated_at'        => now(),
            ];

            $auditLogs[] = [
                'sesi_ujian_id' => $sessionId,
                'user_id'       => $a['user_id'],
                'action'        => 'answer_saved',
                'bank_soal_id'  => $soalId,
                'payload'       => json_encode([
                    'opsi_id'           => $a['opsi_jawaban_id'],
                    'client_updated_at' => $a['client_updated_at'],
                    'server_updated_at' => $a['server_updated_at'],
                ]),
                'created_at'    => now(),
                'updated_at'    => now(),
            ];

            $forgetFields[$soalIdStr] = $raw;
        }

        DB::transaction(function () use ($rowsToUpsert, $auditLogs) {
            if (!empty($rowsToUpsert)) {
                DB::table('jawaban_siswas')->upsert(
                    $rowsToUpsert,
                    ['sesi_ujian_id', 'bank_soal_id'],
                    ['opsi_jawaban_id', 'jawaban_essay', 'tipe_soal', 'skor', 'scoring_status', 'client_updated_at', 'server_updated_at', 'updated_at']
                );
            }

            if (!empty($auditLogs)) {
                DB::table('audit_logs')->insert($auditLogs);
            }
        });

        foreach ($forgetFields as $field => $raw) {
            $this->forgetIfCurrent("queue_jawaban:$sessionId", $field, $raw);
        }
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

            $this->flushAll($session->id);

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
