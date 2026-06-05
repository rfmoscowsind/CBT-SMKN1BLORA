<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ScheduleManagementController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorizeSchedules();

        // FIX: Batch-hitung status canArchive sebelum loop map(),
        // bukan 2 query per jadwal di dalam loop.
        $rawSchedules = DB::table('jadwal_ujians as j')
            ->join('master_ujians as m', 'm.id', '=', 'j.master_ujian_id')
            ->join('jadwal_ujian_kelas as jk', 'jk.jadwal_ujian_id', '=', 'j.id')
            ->join('kelas_aktifs as k', 'k.id', '=', 'jk.kelas_aktif_id')
            ->whereNull('j.diarsipkan_at')
            ->orderByDesc('j.waktu_mulai')
            ->get([
                'j.id',
                'j.master_ujian_id',
                'jk.kelas_aktif_id',
                'm.judul as ujian',
                'k.nama_kelas as kelas',
                'j.waktu_mulai',
                'j.waktu_selesai',
                'j.durasi_menit',
                'j.token',
            ]);

        $scheduleIds = $rawSchedules->pluck('id');

        // 1 query untuk jumlah target kelas per jadwal
        $targetCounts = DB::table('jadwal_ujian_kelas')
            ->whereIn('jadwal_ujian_id', $scheduleIds)
            ->selectRaw('jadwal_ujian_id, count(*) as cnt')
            ->groupBy('jadwal_ujian_id')
            ->pluck('cnt', 'jadwal_ujian_id');

        // 1 query untuk jumlah download per jadwal
        $downloadCounts = DB::table('hasil_ujian_unduhans')
            ->whereIn('jadwal_ujian_id', $scheduleIds)
            ->selectRaw('jadwal_ujian_id, count(*) as cnt')
            ->groupBy('jadwal_ujian_id')
            ->pluck('cnt', 'jadwal_ujian_id');

        $schedules = $rawSchedules->map(function ($schedule) use ($targetCounts, $downloadCounts) {
            $schedule->waktu_mulai    = Carbon::parse($schedule->waktu_mulai, 'UTC')->timezone('Asia/Jakarta')->format('Y-m-d H:i:s');
            $schedule->waktu_selesai  = Carbon::parse($schedule->waktu_selesai, 'UTC')->timezone('Asia/Jakarta')->format('Y-m-d H:i:s');
            $targets   = $targetCounts->get($schedule->id, 0);
            $downloads = $downloadCounts->get($schedule->id, 0);
            $schedule->bisa_diarsipkan = $targets > 0 && $downloads >= $targets;

            return $schedule;
        });

        return $this->ok([
            'packages' => DB::table('paket_soals as p')
                ->join('mata_pelajarans as mp', 'mp.id', '=', 'p.mata_pelajaran_id')
                ->leftJoin('bank_soals as b', 'b.paket_soal_id', '=', 'p.id')
                ->where('p.status', 'ready')
                ->groupBy('p.id', 'p.judul', 'mp.nama_mapel')
                ->orderBy('mp.nama_mapel')
                ->orderBy('p.judul')
                ->get([
                    'p.id',
                    'p.judul',
                    'mp.nama_mapel',
                    DB::raw('count(b.id) as jumlah_soal'),
                ]),
            'masters' => DB::table('master_ujians as m')
                ->join('paket_soals as p', 'p.id', '=', 'm.paket_soal_id')
                ->leftJoin('bank_soals as b', 'b.paket_soal_id', '=', 'p.id')
                ->groupBy('m.id', 'm.judul', 'm.paket_soal_id', 'p.judul', 'm.acak_soal', 'm.acak_opsi', 'm.tampilkan_nilai_akhir', 'm.hasil_visibilitas', 'm.tanggal_rilis_hasil')
                ->orderByDesc('m.id')
                ->get([
                    'm.id',
                    'm.judul',
                    'm.paket_soal_id',
                    'p.judul as paket_soal',
                    'm.acak_soal',
                    'm.acak_opsi',
                    'm.tampilkan_nilai_akhir',
                    'm.hasil_visibilitas',
                    'm.tanggal_rilis_hasil',
                    DB::raw('count(b.id) as jumlah_soal'),
                ])->map(function ($master) {
                    $master->tanggal_rilis_hasil = $master->tanggal_rilis_hasil
                        ? Carbon::parse($master->tanggal_rilis_hasil, 'UTC')->timezone('Asia/Jakarta')->format('Y-m-d H:i:s')
                        : null;

                    return $master;
                }),
            'classes'   => DB::table('kelas_aktifs')->orderBy('nama_kelas')->get(['id', 'nama_kelas']),
            'schedules' => $schedules,
        ]);
    }

    public function storeMaster(Request $request): JsonResponse
    {
        $this->authorizeSchedules();
        $data = $this->validateJson($request, [
            'judul' => ['required', 'string', 'max:255'],
            'paket_soal_id' => ['required', 'exists:paket_soals,id'],
            'acak_soal' => ['required', 'boolean'],
            'acak_opsi' => ['required', 'boolean'],
            'tampilkan_nilai_akhir' => ['required', 'boolean'],
            'hasil_visibilitas' => ['required', 'in:instant,manual,scheduled'],
            'tanggal_rilis_hasil' => ['nullable', 'date', 'required_if:hasil_visibilitas,scheduled'],
        ]);
        abort_unless(DB::table('paket_soals')->where(['id' => $data['paket_soal_id'], 'status' => 'ready'])->exists(), 422, 'Paket soal belum ready.');

        $data['tanggal_rilis_hasil'] = ($data['tanggal_rilis_hasil'] ?? null)
            ? Carbon::parse($data['tanggal_rilis_hasil'], 'Asia/Jakarta')->utc()
            : null;
        $id = DB::table('master_ujians')->insertGetId($data + [
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->ok(['id' => $id], 201);
    }

    public function storeSchedule(Request $request): JsonResponse
    {
        $this->authorizeSchedules();
        $data = $this->validateJson($request, [
            'master_ujian_id' => ['required', 'exists:master_ujians,id'],
            'kelas_aktif_id' => ['required', 'exists:kelas_aktifs,id'],
            'waktu_mulai' => ['required', 'date'],
            'waktu_selesai' => ['required', 'date', 'after:waktu_mulai'],
            'durasi_menit' => ['required', 'integer', 'min:1'],
        ]);
        $start = Carbon::parse($data['waktu_mulai'], 'Asia/Jakarta')->utc();
        $end = Carbon::parse($data['waktu_selesai'], 'Asia/Jakarta')->utc();
        $token = Str::upper(Str::random(6));
        $id = DB::table('jadwal_ujians')->insertGetId([
            'master_ujian_id' => $data['master_ujian_id'],
            'waktu_mulai' => $start,
            'waktu_selesai' => $end,
            'durasi_menit' => $data['durasi_menit'],
            'gunakan_token' => true,
            'token' => $token,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('jadwal_ujian_kelas')->insert([
            'jadwal_ujian_id' => $id,
            'kelas_aktif_id' => $data['kelas_aktif_id'],
        ]);

        return $this->ok(['id' => $id, 'token' => $token], 201);
    }

    public function updateMaster(Request $request, int $id): JsonResponse
    {
        $this->authorizeSchedules();
        $master = DB::table('master_ujians')->find($id);
        abort_unless($master, 404);
        $data = $this->validateJson($request, [
            'judul' => ['required', 'string', 'max:255'],
            'paket_soal_id' => ['required', 'exists:paket_soals,id'],
            'acak_soal' => ['required', 'boolean'],
            'acak_opsi' => ['required', 'boolean'],
            'tampilkan_nilai_akhir' => ['required', 'boolean'],
            'hasil_visibilitas' => ['required', 'in:instant,manual,scheduled'],
            'tanggal_rilis_hasil' => ['nullable', 'date', 'required_if:hasil_visibilitas,scheduled'],
        ]);
        abort_unless(DB::table('paket_soals')->where(['id' => $data['paket_soal_id'], 'status' => 'ready'])->exists(), 422, 'Paket soal belum ready.');
        $changesExamShape = (int) $master->paket_soal_id !== (int) $data['paket_soal_id']
            || (bool) $master->acak_soal !== (bool) $data['acak_soal']
            || (bool) $master->acak_opsi !== (bool) $data['acak_opsi'];
        abort_if(
            $changesExamShape && DB::table('jadwal_ujians as j')->join('sesi_ujians as s', 's.jadwal_ujian_id', '=', 'j.id')->where('j.master_ujian_id', $id)->exists(),
            422,
            'Paket soal dan aturan acak tidak dapat diganti karena sesi siswa sudah tercatat.'
        );
        $data['tanggal_rilis_hasil'] = ($data['tanggal_rilis_hasil'] ?? null)
            ? Carbon::parse($data['tanggal_rilis_hasil'], 'Asia/Jakarta')->utc()
            : null;
        DB::table('master_ujians')->where('id', $id)->update($data + ['updated_at' => now()]);

        return $this->ok(['id' => $id]);
    }

    public function regenerateToken(int $id): JsonResponse
    {
        abort_unless(Auth::user()?->role === 'SuperAdmin', 403);
        abort_unless(DB::table('jadwal_ujians')->where('id', $id)->exists(), 404);
        $token = Str::upper(Str::random(6));
        DB::table('jadwal_ujians')->where('id', $id)->update(['token' => $token, 'updated_at' => now()]);

        return $this->ok(['token' => $token]);
    }

    public function updateSchedule(Request $request, int $id): JsonResponse
    {
        $this->authorizeSchedules();
        $schedule = DB::table('jadwal_ujians')->whereNull('diarsipkan_at')->find($id);
        abort_unless($schedule, 404);
        $data = $this->validateJson($request, [
            'master_ujian_id' => ['required', 'exists:master_ujians,id'],
            'kelas_aktif_id' => ['required', 'exists:kelas_aktifs,id'],
            'waktu_mulai' => ['required', 'date'],
            'waktu_selesai' => ['required', 'date', 'after:waktu_mulai'],
            'durasi_menit' => ['required', 'integer', 'min:1'],
        ]);
        $currentClasses = DB::table('jadwal_ujian_kelas')
            ->where('jadwal_ujian_id', $id)
            ->pluck('kelas_aktif_id');
        $changesTarget = (int) $schedule->master_ujian_id !== (int) $data['master_ujian_id']
            || $currentClasses->count() !== 1
            || (int) $currentClasses->first() !== (int) $data['kelas_aktif_id'];
        abort_if(
            $changesTarget && DB::table('sesi_ujians')->where('jadwal_ujian_id', $id)->exists(),
            422,
            'Master ujian atau kelas tidak dapat diganti karena sesi siswa sudah tercatat.'
        );

        DB::transaction(function () use ($id, $data, $changesTarget) {
            DB::table('jadwal_ujians')->where('id', $id)->update([
                'master_ujian_id' => $data['master_ujian_id'],
                'waktu_mulai' => Carbon::parse($data['waktu_mulai'], 'Asia/Jakarta')->utc(),
                'waktu_selesai' => Carbon::parse($data['waktu_selesai'], 'Asia/Jakarta')->utc(),
                'durasi_menit' => $data['durasi_menit'],
                'updated_at' => now(),
            ]);
            if ($changesTarget) {
                DB::table('jadwal_ujian_kelas')->where('jadwal_ujian_id', $id)->delete();
                DB::table('jadwal_ujian_kelas')->insert([
                    'jadwal_ujian_id' => $id,
                    'kelas_aktif_id' => $data['kelas_aktif_id'],
                ]);
            }
            DB::table('hasil_ujian_unduhans')->where('jadwal_ujian_id', $id)->delete();
        });

        return $this->ok(['id' => $id]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->authorizeSchedules();
        $schedule = DB::table('jadwal_ujians')->whereNull('diarsipkan_at')->find($id);
        abort_unless($schedule, 404);
        abort_unless($this->canArchive($id), 422, 'Download PDF hasil seluruh kelas target sebelum mengarsipkan jadwal.');
        DB::table('jadwal_ujians')->where('id', $id)->update([
            'diarsipkan_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->ok();
    }

    private function canArchive(int $id): bool
    {
        $targets = DB::table('jadwal_ujian_kelas')->where('jadwal_ujian_id', $id)->count();
        $downloads = DB::table('hasil_ujian_unduhans')->where('jadwal_ujian_id', $id)->count();

        return $targets > 0 && $downloads >= $targets;
    }

    private function authorizeSchedules(): void
    {
        // FIX: Hapus ->fresh() — gunakan ->can() yang memanfaatkan Spatie cache.
        abort_unless(Auth::user()?->can('manage-schedules'), 403);
    }

    private function validateJson(Request $request, array $rules): array
    {
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Data jadwal belum valid.',
                'errors' => $validator->errors(),
            ], 422));
        }

        return $validator->validated();
    }

    private function ok(mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }
}
