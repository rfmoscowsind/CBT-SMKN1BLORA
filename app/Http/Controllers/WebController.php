<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesDeviceFingerprints;
use App\Models\User;
use App\Services\ExamService;
use App\Services\IdCodec;
use App\Services\ImageService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class WebController extends Controller
{
    use HandlesDeviceFingerprints;

    public function __construct(private ExamService $exams, private IdCodec $ids)
    {
    }

    public function loginForm()
    {
        if (Auth::check()) {
            return redirect('/dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('username', $data['username'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return back()->withErrors(['username' => 'Username atau password salah.'])->withInput($request->only('username'));
        }

        Auth::login($user);

        try {
            $this->enforceDeviceFingerprint($request);
            $this->rememberUserDevice($user, $request);
        } catch (\Throwable $throwable) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            throw $throwable;
        }

        $request->session()->regenerate();
        $this->acknowledgeDeviceReset($user);
        DB::table('users')->where('id', $user->id)->update(['last_login_at' => now(), 'updated_at' => now()]);
        $this->audit('web_login', ['username' => $user->username], $request);

        return redirect('/dashboard?auth_refresh='.now()->timestamp);
    }

    public function logout(Request $request)
    {
        $this->audit('web_logout', ['user_id' => Auth::id()], $request);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    public function dashboard(Request $request)
    {
        $user = Auth::user();
        abort_unless($user, 401);

        if ($user->role === 'Siswa') {
            $this->rejectStaleDeviceResetSession($user);
            $this->enforceDeviceFingerprint($request);

            if ($request->expectsJson()) {
                return response()->json([
                    'user' => $this->studentProfile($user),
                    'schedules' => $this->studentSchedules($user),
                ]);
            }

            return redirect('/vue/dashboard/siswa');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'role' => $user->role,
                ],
            ]);
        }

        return match ($user->role) {
            'SuperAdmin' => redirect('/vue/dashboard/superadmin'),
            'Admin' => redirect('/vue/dashboard/admin'),
            'Guru' => redirect('/vue/dashboard/guru'),
            'Pengawas' => redirect('/vue/dashboard/pengawas'),
            default => view('dashboard.index', $this->dashboardViewData($user)),
        };
    }

    public function start(Request $request, string $jadwal)
    {
        $user = Auth::user();
        abort_unless($user && $user->role === 'Siswa', 403);
        $this->rejectStaleDeviceResetSession($user);
        $this->enforceDeviceFingerprint($request);

        $scheduleId = $this->ids->decode($jadwal);
        $session = $this->exams->start($user, $scheduleId, $request->input('token'), $request->ip(), $request->userAgent());
        $this->rememberUserDevice($user, $request);
        $this->rememberSessionDevice((int) $session->id, $request);
        $sessionHash = $this->ids->encode((int) $session->id);

        if (($session->status ?? null) === 'selesai') {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'session_hash' => $sessionHash,
                    'data' => ['session_hash' => $sessionHash, 'status' => 'selesai'],
                ]);
            }

            return redirect("/ujian/sesi/$sessionHash/hasil");
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'session_hash' => $sessionHash,
                'data' => ['session_hash' => $sessionHash],
            ]);
        }

        return redirect("/ujian/sesi/$sessionHash?nomor=1");
    }

    public function show(Request $request, string $id)
    {
        $session = $this->ownedSession($id, $request);

        if (($session->status ?? null) === 'selesai') {
            if ($request->expectsJson()) {
                return response()->json(['redirect' => true, 'status' => 'selesai']);
            }

            return redirect("/ujian/sesi/$id/hasil");
        }

        $number = max(1, (int) $request->query('nomor', 1));
        $payload = $this->examPayload($session, $number);

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return view('exams.final', $this->examViewData($session, $id, $number));
    }

    public function soal(Request $request, string $id)
    {
        $session = $this->ownedSession($id, $request);
        abort_if(($session->status ?? null) !== 'aktif', 410, 'Sesi ujian selesai.');
        $number = max(1, (int) $request->query('nomor', 1));

        return response()->json($this->singleQuestionPayload($session, $number));
    }

    public function save(Request $request, string $id)
    {
        $session = $this->ownedSession($id, $request);
        $questionId = $this->ids->decode((string) $request->input('soal_hash'));
        $optionHash = $request->input('opsi_hash');
        $optionId = $optionHash ? $this->ids->decode((string) $optionHash) : null;
        $essay = $request->input('essay');

        $this->exams->save($session, $questionId, $optionId, $essay, $request->input('client_updated_at'));

        if ($request->has('ragu')) {
            DB::table('sesi_ujian_soals')
                ->where(['sesi_ujian_id' => $session->id, 'bank_soal_id' => $questionId])
                ->update(['ditandai' => $request->boolean('ragu')]);
        }

        if ($request->expectsJson()) {
            $item = DB::table('sesi_ujian_soals')
                ->where(['sesi_ujian_id' => $session->id, 'bank_soal_id' => $questionId])
                ->first(['nomor_soal', 'ditandai']);

            return response()->json([
                'success' => true,
                'sisa_detik' => $this->exams->remaining($session),
                'nomor' => (int) ($item?->nomor_soal ?? 0),
                'terjawab' => $this->hasAnswerValue($optionId, $essay),
                'ragu' => (bool) ($item?->ditandai ?? $request->boolean('ragu')),
                'server_updated_at' => now()->toISOString(),
            ]);
        }

        $next = max(1, (int) $request->input('next_number', 1));

        return redirect("/ujian/sesi/$id?nomor=$next");
    }

    public function sync(Request $request, string $id)
    {
        $session = $this->ownedSession($id, $request);
        $answers = [];
        $flagUpdates = [];

        foreach ($request->input('answers', []) as $answer) {
            if (! is_array($answer)) {
                continue;
            }

            $questionHash = $answer['soal_hash'] ?? null;
            if (! $questionHash) {
                continue;
            }
            $questionId = $this->ids->decode((string) $questionHash);
            $optionHash = $answer['opsi_hash'] ?? null;
            $optionId = $optionHash ? $this->ids->decode((string) $optionHash) : null;

            $answers[] = [
                'bank_soal_id' => $questionId,
                'opsi_jawaban_id' => $optionId,
                'jawaban_essay' => $answer['essay'] ?? null,
                'client_updated_at' => $answer['client_updated_at'] ?? null,
            ];

            if (array_key_exists('ragu', $answer)) {
                $flagUpdates[$questionId] = (bool) $answer['ragu'];
            }
        }

        $result = $this->exams->saveMany($session, $answers);
        $this->updateQuestionFlags((int) $session->id, $flagUpdates);

        $synced = (int) ($result['saved'] ?? count($answers));

        return response()->json([
            'success' => true,
            'synced' => $synced,
            'sisa_detik' => $this->exams->remaining($session),
            'server_updated_at' => now()->toISOString(),
            'data' => ['synced' => $synced],
        ]);
    }

    public function flag(Request $request, string $id)
    {
        $session = $this->ownedSession($id, $request);
        $questionId = $this->ids->decode((string) $request->input('soal_hash'));
        DB::table('sesi_ujian_soals')
            ->where(['sesi_ujian_id' => $session->id, 'bank_soal_id' => $questionId])
            ->update(['ditandai' => $request->boolean('ditandai')]);

        return response()->json(['success' => true]);
    }

    public function ping(Request $request, string $id)
    {
        $session = $this->ownedSession($id, $request);

        // FIX #7 (Issue issuegpt.md): Pindahkan heartbeat ke Redis untuk mengurangi
        // write load DB. Dengan 1500 siswa ping setiap 10 detik = ~150 write/detik ke DB.
        // Solusi: status "online" disimpan di Redis (TTL 45 detik).
        // DB last_seen_at hanya diupdate tiap 60 detik (rate-limit via Redis key).
        $redisKey     = "cbt:session:online:{$session->id}";
        $dbThrottleKey = "cbt:ping:db:{$session->id}";

        try {
            // Tandai online di Redis (45 detik TTL)
            Redis::setex($redisKey, 45, now()->timestamp);

            // Update DB last_seen_at hanya jika belum ada key throttle (max 1x per 60 detik)
            if (! Redis::exists($dbThrottleKey)) {
                DB::table('sesi_ujians')
                    ->where('id', $session->id)
                    ->update(['last_seen_at' => now(), 'updated_at' => now()]);
                Redis::setex($dbThrottleKey, 60, 1);
            }
        } catch (\Throwable $exception) {
            Log::warning('Redis heartbeat unavailable; falling back to DB heartbeat', [
                'session_id' => $session->id,
                'error' => $exception->getMessage(),
            ]);

            DB::table('sesi_ujians')
                ->where('id', $session->id)
                ->update(['last_seen_at' => now(), 'updated_at' => now()]);
        }

        return response()->json(['success' => true, 'sisa_detik' => $this->exams->remaining($session)]);
    }

    public function event(Request $request, string $id)
    {
        $session = $this->ownedSession($id, $request);
        $this->exams->event((int) $session->id, (string) $request->input('event_type', 'event'), [
            'event_data' => $request->input('event_data', []),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json(['success' => true]);
    }

    public function submit(Request $request, string $id)
    {
        $session = $this->ownedSession($id, $request);
        $session = $this->exams->submit($session);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => ['session_hash' => $id, 'status' => $session->status]]);
        }

        return redirect("/ujian/sesi/$id/hasil");
    }

    public function result(Request $request, string $id)
    {
        $session = $this->ownedSession($id, $request, false);
        $row = DB::table('sesi_ujians as s')
            ->join('jadwal_ujians as j', 'j.id', '=', 's.jadwal_ujian_id')
            ->join('master_ujians as m', 'm.id', '=', 'j.master_ujian_id')
            ->join('paket_soals as p', 'p.id', '=', 'm.paket_soal_id')
            ->join('mata_pelajarans as mp', 'mp.id', '=', 'p.mata_pelajaran_id')
            ->where('s.id', $session->id)
            ->first([
                's.*', 
                'm.judul', 
                'mp.nama_mapel as mapel',
                'm.tampilkan_nilai_akhir', 
                'm.hasil_visibilitas', 
                'm.tanggal_rilis_hasil'
            ]);
        abort_unless($row, 404);
        $canShow = (bool) $row->tampilkan_nilai_akhir
            && (
                $row->hasil_visibilitas === 'instant'
                || ($row->hasil_visibilitas === 'scheduled' && $row->tanggal_rilis_hasil && now()->gte(Carbon::parse($row->tanggal_rilis_hasil)))
            );

        // Calculate statistics
        $stats = DB::table('sesi_ujian_soals as ss')
            ->leftJoin('jawaban_siswas as js', function($join) {
                $join->on('js.bank_soal_id', '=', 'ss.bank_soal_id')
                     ->on('js.sesi_ujian_id', '=', 'ss.sesi_ujian_id');
            })
            ->where('ss.sesi_ujian_id', $session->id)
            ->select('js.skor', 'js.opsi_jawaban_id', 'js.jawaban_essay')
            ->get();

        $benar = 0;
        $salah = 0;
        $kosong = 0;

        foreach ($stats as $item) {
            if (is_null($item->opsi_jawaban_id) && is_null($item->jawaban_essay)) {
                $kosong++;
            } else {
                if (($item->skor ?? 0) > 0) {
                    $benar++;
                } else {
                    $salah++;
                }
            }
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true, 
                'data' => [
                    'session' => $row, 
                    'can_show' => $canShow,
                    'stats' => [
                        'benar' => $benar,
                        'salah' => $salah,
                        'kosong' => $kosong,
                    ]
                ]
            ]);
        }

        return view('exams.final-result', ['s' => $row, 'canShow' => $canShow]);
    }

    private function ownedSession(string $hash, Request $request, bool $activeCheck = true): object
    {
        $this->rejectStaleDeviceResetSession(Auth::user());
        $sessionId = $this->ids->decode($hash);
        $session = DB::table('sesi_ujians')->where(['id' => $sessionId, 'user_id' => Auth::id()])->first();
        abort_unless($session, 403);
        $this->enforceDeviceFingerprint($request, $session);
        if ($activeCheck && $this->exams->remaining($session) <= 0 && ($session->status ?? null) === 'aktif') {
            $session = $this->exams->submit($session);
        }

        return $session;
    }

    private function examPayload(object $session, int $number): array
    {
        $items = $this->sessionItems((int) $session->id);

        return [
            'status' => $session->status,
            'identitas' => $this->examIdentity($session),
            'siswa' => $this->studentProfile(Auth::user()),
            'navigasi' => $this->navigation($session, $items),
            'total_soal' => $items->count(),
            'sisa_detik' => $this->exams->remaining($session),
            'soal' => [],
        ];
    }

    private function singleQuestionPayload(object $session, int $number): array
    {
        $item = $this->sessionItems((int) $session->id)->firstWhere('nomor_soal', $number);
        abort_unless($item, 404);
        $question = $this->questionRow((int) $item->bank_soal_id);
        abort_unless($question, 404);
        $answer = DB::table('jawaban_siswas')->where(['sesi_ujian_id' => $session->id, 'bank_soal_id' => $question->id])->first();
        $pending = $this->pendingAnswer((int) $session->id, (int) $question->id);
        $options = $this->questionOptions($question, $item);
        $pendingOptionId = $pending['opsi_jawaban_id'] ?? null;
        $pendingEssay = $pending['jawaban_essay'] ?? null;

        return [
            'nomor' => (int) $item->nomor_soal,
            'hash_id' => $this->ids->encode((int) $question->id),
            'hashid' => $this->ids->encode((int) $question->id),
            'tipe' => $question->tipe_soal,
            'pertanyaan' => $question->pertanyaan,
            'gambar_url' => ImageService::displayUrl($question->gambar_url),
            'opsi' => $options,
            'jawaban_siswa' => $pendingOptionId
                ? $this->ids->encode((int) $pendingOptionId)
                : ($pending !== null ? $pendingEssay : ($answer?->opsi_jawaban_id ? $this->ids->encode((int) $answer->opsi_jawaban_id) : ($answer?->jawaban_essay))),
            'ragu' => (bool) $item->ditandai,
            'sisa_detik' => $this->exams->remaining($session),
        ];
    }

    private function questionOptions(object $question, object $item)
    {
        if ($question->tipe_soal !== 'PG') {
            return [];
        }

        $order = json_decode($item->opsi_order ?: '[]', true);
        $options = $this->questionOptionRows((int) $question->id);
        $position = array_flip(array_map('intval', is_array($order) ? $order : []));

        $options = ! empty($position)
            ? $options
                ->whereIn('id', array_keys($position))
                ->sortBy(fn ($option) => $position[(int) $option->id] ?? PHP_INT_MAX)
                ->values()
            : $options->sortBy('kode')->values();

        return $options->map(fn ($option) => [
            'hash_id' => $this->ids->encode((int) $option->id),
            'hashid' => $this->ids->encode((int) $option->id),
            'kode' => $option->kode,
            'teks' => $option->teks_opsi,
        ])->values();
    }

    private function rememberControllerValue(string $key, int $seconds, callable $resolver): mixed
    {
        try {
            return Cache::remember($key, $seconds, $resolver);
        } catch (\Throwable $exception) {
            Log::warning('Cache unavailable for web controller hot path', [
                'key' => $key,
                'error' => $exception->getMessage(),
            ]);

            return $resolver();
        }
    }

    private function questionRow(int $questionId): ?object
    {
        $row = $this->rememberControllerValue("bank_soal:{$questionId}:render:v1", 7200, function () use ($questionId) {
            $question = DB::table('bank_soals')->where('id', $questionId)->first();

            return $question ? (array) $question : null;
        });

        return $row ? (object) $row : null;
    }

    private function questionOptionRows(int $questionId)
    {
        $rows = $this->rememberControllerValue("opsi_soal:{$questionId}:render:v1", 7200, function () use ($questionId) {
            return DB::table('opsi_jawabans')
                ->where('bank_soal_id', $questionId)
                ->orderBy('kode')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();
        });

        return collect($rows)->map(fn ($row) => (object) $row);
    }

    private function sessionItems(int $sessionId)
    {
        return DB::table('sesi_ujian_soals')
            ->where('sesi_ujian_id', $sessionId)
            ->orderBy('nomor_soal')
            ->get();
    }

    private function questionList(object $session, $items)
    {
        return $items->map(function ($item) use ($session) {
            $payload = $this->singleQuestionPayload($session, (int) $item->nomor_soal);

            return [
                'nomor' => $payload['nomor'],
                'hashid' => $payload['hashid'],
                'hash_id' => $payload['hash_id'],
                'tipe' => $payload['tipe'],
                'pertanyaan' => $payload['pertanyaan'],
                'gambar_url' => $payload['gambar_url'],
                'opsi' => $payload['opsi'],
                'jawaban_siswa' => $payload['jawaban_siswa'],
                'ragu' => $payload['ragu'],
            ];
        })->values();
    }

    private function navigation(object $session, $items)
    {
        $answered = DB::table('jawaban_siswas')
            ->where('sesi_ujian_id', $session->id)
            ->get(['bank_soal_id', 'opsi_jawaban_id', 'jawaban_essay'])
            ->filter(fn ($answer) => $this->hasAnswerValue($answer->opsi_jawaban_id, $answer->jawaban_essay))
            ->keyBy('bank_soal_id');
        $pending = $this->pendingAnswers((int) $session->id);

        return $items->map(fn ($item) => [
            'nomor' => (int) $item->nomor_soal,
            'terjawab' => $answered->has($item->bank_soal_id)
                || $this->hasAnswerValue($pending[$item->bank_soal_id]['opsi_jawaban_id'] ?? null, $pending[$item->bank_soal_id]['jawaban_essay'] ?? null),
            'ragu' => (bool) $item->ditandai,
        ])->values();
    }

    private function pendingAnswer(int $sessionId, int $questionId): ?array
    {
        try {
            $raw = Redis::hget("queue_jawaban:$sessionId", (string) $questionId);
        } catch (\Throwable $exception) {
            Log::warning('Unable to read pending answer from Redis', [
                'session_id' => $sessionId,
                'question_id' => $questionId,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        if (! $raw) {
            return null;
        }

        $payload = json_decode($raw, true);
        return is_array($payload) ? $payload : null;
    }

    private function pendingAnswers(int $sessionId): array
    {
        try {
            $rows = Redis::hgetall("queue_jawaban:$sessionId");
        } catch (\Throwable $exception) {
            Log::warning('Unable to read pending answers from Redis', [
                'session_id' => $sessionId,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }

        if (empty($rows)) {
            return [];
        }

        $answers = [];
        foreach ($rows as $questionId => $raw) {
            $payload = json_decode($raw, true);
            if (is_array($payload)) {
                $answers[(int) $questionId] = $payload;
            }
        }

        return $answers;
    }

    private function hasAnswerValue($optionId, $essay): bool
    {
        return $optionId !== null || trim((string) $essay) !== '';
    }

    private function updateQuestionFlags(int $sessionId, array $flags): void
    {
        if (empty($flags)) {
            return;
        }

        $flags = array_combine(
            array_map('intval', array_keys($flags)),
            array_map('boolval', array_values($flags))
        );

        $case = '';
        $bindings = [];
        foreach ($flags as $questionId => $flag) {
            $case .= $flag ? ' WHEN ? THEN true' : ' WHEN ? THEN false';
            $bindings[] = $questionId;
        }

        $ids = array_keys($flags);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        DB::update(
            "UPDATE sesi_ujian_soals
             SET ditandai = CASE bank_soal_id{$case} ELSE ditandai END
             WHERE sesi_ujian_id = ? AND bank_soal_id IN ($placeholders)",
            [...$bindings, $sessionId, ...$ids]
        );
    }

    private function examViewData(object $session, string $hash, int $number): array
    {
        $items = $this->sessionItems((int) $session->id);
        $item = $items->firstWhere('nomor_soal', $number) ?: $items->first();
        abort_unless($item, 404);
        $question = $this->questionRow((int) $item->bank_soal_id);
        abort_unless($question, 404);
        $options = $this->questionOptions($question, $item);
        $answers = DB::table('jawaban_siswas')->where('sesi_ujian_id', $session->id)->get()->keyBy('bank_soal_id');

        return [
            'sessionHash' => $hash,
            'items' => $items,
            'item' => $item,
            'question' => $question,
            'options' => $options->map(fn ($option) => (object) [
                'id' => $this->ids->decode($option['hash_id']),
                'kode' => $option['kode'],
                'teks_opsi' => $option['teks'],
            ]),
            'answers' => $answers,
            'saved' => $answers->get($question->id),
            'number' => (int) $item->nomor_soal,
            'remaining' => $this->exams->remaining($session),
            'encode' => fn (int $value) => $this->ids->encode($value),
        ];
    }

    private function examIdentity(object $session): array
    {
        $row = DB::table('jadwal_ujians as j')
            ->join('master_ujians as m', 'm.id', '=', 'j.master_ujian_id')
            ->join('paket_soals as p', 'p.id', '=', 'm.paket_soal_id')
            ->join('mata_pelajarans as mp', 'mp.id', '=', 'p.mata_pelajaran_id')
            ->where('j.id', $session->jadwal_ujian_id)
            ->first(['m.judul', 'mp.nama_mapel', 'j.durasi_menit']);

        return [
            'judul' => $row?->judul,
            'mapel' => $row?->nama_mapel,
            'durasi_menit' => $row?->durasi_menit,
        ];
    }

    private function studentProfile(User $user): array
    {
        return $this->rememberControllerValue(
            "student-profile:{$user->id}:v1",
            900,
            fn () => $this->buildStudentProfile($user)
        );
    }

    private function buildStudentProfile(User $user): array
    {
        $row = DB::table('users as u')
            ->leftJoin('kelas_aktifs as k', 'k.id', '=', 'u.kelas_aktif_id')
            ->leftJoin('jurusans as j', 'j.id', '=', 'k.jurusan_id')
            ->where('u.id', $user->id)
            ->first(['u.name', 'u.username', 'k.nama_kelas', 'j.nama_jurusan']);

        return [
            'nama' => $row?->name ?: $user->name,
            'nisn' => $row?->username ?: $user->username,
            'kelas' => $row?->nama_kelas ?: '-',
            'jurusan' => $row?->nama_jurusan ?: '-',
        ];
    }

    private function studentSchedules(User $user): array
    {
        $date = now('Asia/Jakarta')->toDateString();

        return $this->rememberControllerValue(
            "student-schedules:{$user->id}:{$date}:v1",
            60,
            fn () => $this->buildStudentSchedules($user)
        );
    }

    private function buildStudentSchedules(User $user): array
    {
        $rows = DB::table('jadwal_ujians as j')
            ->join('master_ujians as m', 'm.id', '=', 'j.master_ujian_id')
            ->join('paket_soals as p', 'p.id', '=', 'm.paket_soal_id')
            ->join('mata_pelajarans as mp', 'mp.id', '=', 'p.mata_pelajaran_id')
            ->join('jadwal_ujian_kelas as jk', 'jk.jadwal_ujian_id', '=', 'j.id')
            ->leftJoin('sesi_ujians as s', fn ($join) => $join->on('s.jadwal_ujian_id', '=', 'j.id')->where('s.user_id', '=', $user->id))
            ->where('jk.kelas_aktif_id', $user->kelas_aktif_id)
            ->whereNull('j.diarsipkan_at')
            ->where('j.waktu_mulai', '<=', now('Asia/Jakarta')->endOfDay()->utc())
            ->where('j.waktu_selesai', '>=', now('Asia/Jakarta')->startOfDay()->utc())
            ->orderBy('j.waktu_mulai')
            ->get([
                'j.id',
                'm.judul',
                'mp.nama_mapel',
                'j.waktu_mulai',
                'j.waktu_selesai',
                'j.durasi_menit',
                'j.gunakan_token',
                'm.paket_soal_id',
                's.status as session_status',
            ]);

        if ($rows->isEmpty()) {
            return [];
        }

        // PERF-2 FIX: Batch-count soal per paket in a single query (was 2 sub-queries per row)
        $paketIds = $rows->pluck('paket_soal_id')->unique()->all();

        $jumlahPerPaket = DB::table('bank_soals')
            ->whereIn('paket_soal_id', $paketIds)
            ->selectRaw('paket_soal_id, count(*) as jumlah')
            ->groupBy('paket_soal_id')
            ->pluck('jumlah', 'paket_soal_id');

        $tipeAggregate = DB::connection()->getDriverName() === 'sqlite'
            ? "group_concat(DISTINCT tipe_soal)"
            : "string_agg(DISTINCT tipe_soal, ', ')";

        $tipePerPaket = DB::table('bank_soals')
            ->whereIn('paket_soal_id', $paketIds)
            ->selectRaw("paket_soal_id, {$tipeAggregate} as tipe")
            ->groupBy('paket_soal_id')
            ->pluck('tipe', 'paket_soal_id');

        return $rows->map(function ($row) use ($jumlahPerPaket, $tipePerPaket) {
            return [
                'id'           => $row->id,
                'hash'         => $this->ids->encode((int) $row->id),
                'mapel_nama'   => $row->nama_mapel,
                'judul_ujian'  => $row->judul,
                'waktu_mulai'  => Carbon::parse($row->waktu_mulai)->timezone('Asia/Jakarta')->format('H:i'),
                'waktu_selesai'=> Carbon::parse($row->waktu_selesai)->timezone('Asia/Jakarta')->format('H:i'),
                'durasi_menit' => $row->durasi_menit,
                'jumlah_soal'  => (int) ($jumlahPerPaket[$row->paket_soal_id] ?? 0),
                'tipe_soal'    => $tipePerPaket[$row->paket_soal_id] ?? '-',
                'gunakan_token'=> (bool) $row->gunakan_token,
                'status'       => $this->scheduleStatus($row),
            ];
        })->values()->all();
    }

    private function scheduleStatus(object $row): string
    {
        if (in_array($row->session_status, ['selesai', 'terkunci'], true)) {
            return $row->session_status;
        }
        if (now()->lt(Carbon::parse($row->waktu_mulai))) {
            return 'belum_mulai';
        }
        if (now()->gt(Carbon::parse($row->waktu_selesai))) {
            return 'terlewat';
        }

        return 'aktif';
    }

    private function dashboardViewData(User $user): array
    {
        $permissions = $user->getAllPermissions()->pluck('name');
        $schedules = DB::table('jadwal_ujians as j')
            ->join('master_ujians as m', 'm.id', '=', 'j.master_ujian_id')
            ->whereNull('j.diarsipkan_at')
            ->orderByDesc('j.waktu_mulai')
            ->limit(20)
            ->get(['j.*', 'm.judul']);
        $activeSessions = DB::table('sesi_ujians as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->whereIn('s.status', ['aktif', 'terkunci'])
            ->orderByDesc('s.id')
            ->limit(20)
            ->get(['s.*', 'u.name', 'u.username'])
            ->map(function ($session) {
                $session->online = $session->last_seen_at && now()->diffInSeconds($session->last_seen_at) <= 30;

                return $session;
            });

        return [
            'permissions' => $permissions,
            'schedules' => $schedules,
            'activeSessions' => $activeSessions,
            'studentCount' => DB::table('users')->where('role', 'Siswa')->count(),
            'activeSessionCount' => DB::table('sesi_ujians')->where('status', 'aktif')->count(),
            'offlineSessionCount' => $activeSessions->where('online', false)->count(),
            'questionCount' => DB::table('bank_soals')->count(),
            'readyPackageCount' => DB::table('paket_soals')->where('status', 'ready')->count(),
            'pendingEssayCount' => DB::table('jawaban_siswas')->where('scoring_status', 'pending_manual')->count(),
        ];
    }

    private function audit(string $action, array $payload, Request $request): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => Auth::id(),
            'action' => $action,
            'payload' => json_encode($payload),
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
