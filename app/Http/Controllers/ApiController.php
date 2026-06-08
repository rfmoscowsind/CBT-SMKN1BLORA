<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesDeviceFingerprints;
use App\Models\User;
use App\Services\ExamService;
use App\Services\IdCodec;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Tymon\JWTAuth\Facades\JWTAuth;

class ApiController extends Controller
{
    use HandlesDeviceFingerprints;

    public function __construct(private ExamService $exams, private IdCodec $ids)
    {
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('username', $data['username'])->first();
        abort_unless($user && Hash::check($data['password'], $user->password), 401, 'Username atau password salah.');

        // BUG-3 FIX: Acknowledge device-reset BEFORE enforcing fingerprint (mirrors web login fix)
        $this->acknowledgeDeviceReset($user);
        $this->enforceDeviceFingerprintForUser($user, $request);
        $this->rememberUserDevice($user, $request);

        $token = JWTAuth::fromUser($user);
        DB::table('users')->where('id', $user->id)->update(['last_login_at' => now(), 'updated_at' => now()]);
        $this->audit('api_login', ['username' => $user->username], $request, $user->id);

        return $this->ok([
            'token'      => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl', 60) * 60,
            'user'       => $this->safeUser($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $userId = Auth::guard('api')->id();
        $this->audit('api_logout', ['user_id' => $userId], $request, $userId);
        Auth::guard('api')->logout();

        return $this->ok();
    }

    public function me(): JsonResponse
    {
        return $this->ok($this->safeUser(Auth::guard('api')->user()));
    }

    public function schedules(): JsonResponse
    {
        $user = Auth::guard('api')->user();
        abort_unless($user, 401);

        return $this->ok($this->studentSchedules($user));
    }

    public function start(Request $request, string $id): JsonResponse
    {
        $user = Auth::guard('api')->user();
        abort_unless($user && $user->role === 'Siswa', 403);
        $this->apiDeviceCheck($request);

        $scheduleId = $this->ids->decode($id);
        $session = $this->exams->start($user, $scheduleId, $request->input('token'), $request->ip(), $request->userAgent());
        $this->rememberUserDevice($user, $request);
        $this->rememberSessionDevice((int) $session->id, $request);
        $hash = $this->ids->encode((int) $session->id);

        return $this->ok(['session_hash' => $hash, 'status' => $session->status]);
    }

    public function question(Request $request, string $sid): JsonResponse
    {
        $session = $this->ownedApiSession($request, $sid);
        abort_if(($session->status ?? null) !== 'aktif', 410, 'Sesi ujian selesai.');

        return $this->ok(['soal' => $this->singleQuestionPayload($session, max(1, $request->integer('nomor', 1))), 'sisa_detik' => $this->exams->remaining($session)]);
    }

    public function save(Request $request, string $sid): JsonResponse
    {
        $session = $this->ownedApiSession($request, $sid);
        $questionId = $this->ids->decode((string) $request->input('soal_hash'));
        $optionHash = $request->input('opsi_hash');
        $optionId = $optionHash ? $this->ids->decode((string) $optionHash) : null;
        $this->exams->save($session, $questionId, $optionId, $request->input('essay'), $request->input('client_updated_at'));
        if ($request->has('ragu')) {
            DB::table('sesi_ujian_soals')
                ->where(['sesi_ujian_id' => $session->id, 'bank_soal_id' => $questionId])
                ->update(['ditandai' => $request->boolean('ragu')]);
        }

        return $this->ok(['sisa_detik' => $this->exams->remaining($session)]);
    }

    public function sync(Request $request, string $sid): JsonResponse
    {
        $session = $this->ownedApiSession($request, $sid);
        $synced = 0;
        foreach ($request->input('answers', []) as $answer) {
            $questionHash = $answer['soal_hash'] ?? null;
            if (! $questionHash) {
                continue;
            }
            $questionId = $this->ids->decode((string) $questionHash);
            $optionHash = $answer['opsi_hash'] ?? null;
            $optionId = $optionHash ? $this->ids->decode((string) $optionHash) : null;
            $this->exams->save($session, $questionId, $optionId, $answer['essay'] ?? null, $answer['client_updated_at'] ?? null);
            if (array_key_exists('ragu', $answer)) {
                DB::table('sesi_ujian_soals')
                    ->where(['sesi_ujian_id' => $session->id, 'bank_soal_id' => $questionId])
                    ->update(['ditandai' => (bool) $answer['ragu']]);
            }
            $synced++;
        }

        return $this->ok(['synced' => $synced, 'sisa_detik' => $this->exams->remaining($session)]);
    }

    public function ping(Request $request, string $sid): JsonResponse
    {
        $session = $this->ownedApiSession($request, $sid);
        Redis::setex("cbt:session:online:{$session->id}", 45, now()->timestamp);
        if (! Redis::exists("cbt:ping:db:{$session->id}")) {
            DB::table('sesi_ujians')->where('id', $session->id)->update(['last_seen_at' => now(), 'updated_at' => now()]);
            Redis::setex("cbt:ping:db:{$session->id}", 60, 1);
        }

        return $this->ok(['sisa_detik' => $this->exams->remaining($session)]);
    }

    public function submit(Request $request, string $sid): JsonResponse
    {
        $session = $this->ownedApiSession($request, $sid);
        $session = $this->exams->submit($session);

        return $this->ok(['session_hash' => $sid, 'status' => $session->status, 'nilai_akhir' => $session->nilai_akhir ?? null]);
    }

    private function ownedApiSession(Request $request, string $hash): object
    {
        $sessionId = $this->ids->decode($hash);
        $session = DB::table('sesi_ujians')->where(['id' => $sessionId, 'user_id' => Auth::guard('api')->id()])->first();
        abort_unless($session, 403);
        $this->apiDeviceCheck($request, $session);

        if (($session->status ?? null) === 'aktif' && $this->exams->remaining($session) <= 0) {
            return $this->exams->submit($session);
        }

        return $session;
    }

    private function apiDeviceCheck(Request $request, ?object $session = null): void
    {
        $apiUser = Auth::guard('api')->user();
        if (! $apiUser) {
            return;
        }

        $this->rejectStaleDeviceResetSession($apiUser);
        $this->enforceDeviceFingerprintForUser($apiUser, $request, $session);
    }

    private function singleQuestionPayload(object $session, int $number): array
    {
        $item = DB::table('sesi_ujian_soals')->where(['sesi_ujian_id' => $session->id, 'nomor_soal' => $number])->first();
        abort_unless($item, 404);
        $question = DB::table('bank_soals')->where('id', $item->bank_soal_id)->first();
        abort_unless($question, 404);
        $answer = DB::table('jawaban_siswas')->where(['sesi_ujian_id' => $session->id, 'bank_soal_id' => $question->id])->first();
        $pending = $this->pendingAnswer((int) $session->id, (int) $question->id);
        $pendingOptionId = $pending['opsi_jawaban_id'] ?? null;
        $pendingEssay = $pending['jawaban_essay'] ?? null;

        return [
            'nomor' => (int) $item->nomor_soal,
            'hashid' => $this->ids->encode((int) $question->id),
            'hash_id' => $this->ids->encode((int) $question->id),
            'tipe' => $question->tipe_soal,
            'pertanyaan' => $question->pertanyaan,
            'gambar_url' => $question->gambar_url,
            'opsi' => $this->questionOptions($question, $item),
            'jawaban_siswa' => $pendingOptionId
                ? $this->ids->encode((int) $pendingOptionId)
                : ($pending !== null ? $pendingEssay : ($answer?->opsi_jawaban_id ? $this->ids->encode((int) $answer->opsi_jawaban_id) : ($answer?->jawaban_essay))),
            'ragu' => (bool) $item->ditandai,
        ];
    }

    private function pendingAnswer(int $sessionId, int $questionId): ?array
    {
        $raw = Redis::hget("queue_jawaban:$sessionId", (string) $questionId);
        if (! $raw) {
            return null;
        }

        $payload = json_decode($raw, true);

        return is_array($payload) ? $payload : null;
    }

    private function questionOptions(object $question, object $item): array
    {
        if ($question->tipe_soal !== 'PG') {
            return [];
        }
        $order = json_decode($item->opsi_order ?: '[]', true);
        $query = DB::table('opsi_jawabans')->where('bank_soal_id', $question->id);
        $options = ! empty($order)
            ? $query->whereIn('id', $order)->get()->sortBy(fn ($option) => array_search($option->id, $order, true))->values()
            : $query->orderBy('kode')->get();

        return $options->map(fn ($option) => [
            'hashid' => $this->ids->encode((int) $option->id),
            'hash_id' => $this->ids->encode((int) $option->id),
            'kode' => $option->kode,
            'teks' => $option->teks_opsi,
        ])->values()->all();
    }

    private function studentSchedules(User $user): array
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

    private function safeUser(?User $user): array
    {
        return [
            'id' => $user?->id,
            'name' => $user?->name,
            'username' => $user?->username,
            'role' => $user?->role,
        ];
    }

    private function audit(string $action, array $payload, Request $request, ?int $userId = null): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => $userId,
            'action' => $action,
            'payload' => json_encode($payload),
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ok(mixed $data = null): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => null,
            'error' => null,
        ]);
    }
}
