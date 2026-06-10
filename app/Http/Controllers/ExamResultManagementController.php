<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ExamResultManagementController extends Controller
{
    public function __construct(private ReportService $reports)
    {
    }

    public function options(): JsonResponse
    {
        $this->authorizeResults();

        return $this->ok([
            'classes' => $this->classes(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeResults();
        $data = $this->validateJson($request, [
            'tingkat' => ['required', 'integer'],
            'jurusan_id' => ['required', 'integer', 'exists:jurusans,id'],
            'rombel_id' => ['required', 'integer', 'exists:rombels,id'],
            'jadwal_id' => ['nullable', 'integer', 'exists:jadwal_ujians,id'],
        ]);

        $class = DB::table('kelas_aktifs as k')
            ->join('jurusans as j', 'j.id', '=', 'k.jurusan_id')
            ->join('rombels as r', 'r.id', '=', 'k.rombel_id')
            ->where('k.tingkat', $data['tingkat'])
            ->where('k.jurusan_id', $data['jurusan_id'])
            ->where('k.rombel_id', $data['rombel_id'])
            ->first([
                'k.id',
                'k.nama_kelas',
                'k.tingkat',
                'j.kode_jurusan',
                'j.nama_jurusan',
                'r.nama_rombel',
            ]);
        abort_unless($class, 404, 'Kelas tidak ditemukan.');

        $scheduleQuery = DB::table('jadwal_ujians as j')
            ->join('jadwal_ujian_kelas as jk', 'jk.jadwal_ujian_id', '=', 'j.id')
            ->join('master_ujians as m', 'm.id', '=', 'j.master_ujian_id')
            ->join('paket_soals as p', 'p.id', '=', 'm.paket_soal_id')
            ->join('mata_pelajarans as mp', 'mp.id', '=', 'p.mata_pelajaran_id')
            ->where('jk.kelas_aktif_id', $class->id)
            ->whereNull('j.diarsipkan_at')
            ->orderByDesc('j.waktu_mulai');

        if (! empty($data['jadwal_id'])) {
            $scheduleQuery->where('j.id', $data['jadwal_id']);
        }

        $rawSchedules = $scheduleQuery->get([
            'j.id',
            'm.judul',
            'mp.nama_mapel',
            'j.waktu_mulai',
            'j.waktu_selesai',
        ]);

        if (empty($data['jadwal_id'])) {
            return $this->ok([
                'kelas' => $class,
                'schedules' => $rawSchedules->map(function ($schedule) {
                    $schedule->waktu_mulai = $this->wib($schedule->waktu_mulai);
                    $schedule->waktu_selesai = $this->wib($schedule->waktu_selesai);

                    return $schedule;
                }),
            ]);
        }

        abort_unless($rawSchedules->isNotEmpty(), 404, 'Jadwal ujian tidak ditemukan untuk kelas ini.');

        $scheduleIds = $rawSchedules->pluck('id');

        // FIX: Ambil semua sesi dan statistik sekaligus untuk semua jadwal (1 query, bukan N query)
        $allRows = DB::table('users as u')
            ->join('jadwal_ujian_kelas as jk', fn($j) => $j->on('u.kelas_aktif_id', '=', 'jk.kelas_aktif_id')->where('u.role', '=', 'Siswa'))
            ->whereIn('jk.jadwal_ujian_id', $scheduleIds)
            ->leftJoin('sesi_ujians as s', fn($j) => $j->on('s.user_id', '=', 'u.id')->on('s.jadwal_ujian_id', '=', 'jk.jadwal_ujian_id'))
            ->leftJoin('jawaban_siswas as a', 'a.sesi_ujian_id', '=', 's.id')
            ->select(
                'jk.jadwal_ujian_id',
                'u.username',
                'u.name',
                'u.status_kehadiran',
                DB::raw("coalesce(s.status,'belum_masuk') as status"),
                's.waktu_submit',
                DB::raw("coalesce(sum(case when a.tipe_soal='PG' then a.skor else 0 end),0) as nilai_pg"),
                DB::raw("coalesce(sum(case when a.tipe_soal='ISIAN' then a.skor else 0 end),0) as nilai_isian"),
                DB::raw('coalesce(s.nilai_akhir,0) as nilai_akhir'),
                's.id as sesi_id'
            )
            ->groupBy('jk.jadwal_ujian_id', 'u.username', 'u.name', 'u.status_kehadiran', 's.status', 's.waktu_submit', 's.nilai_akhir', 's.id')
            ->orderByDesc('s.nilai_akhir')
            ->orderBy('u.name')
            ->get()
            ->groupBy('jadwal_ujian_id');

        $schedules = $rawSchedules->map(function ($schedule) use ($allRows) {
            $rows = collect($allRows->get($schedule->id, []));
            $rank = 0;
            $rows = $rows->map(function ($row) use (&$rank) {
                $row->ranking     = $row->sesi_id ? ++$rank : null;
                $row->waktu_submit = $row->waktu_submit ? $this->wib($row->waktu_submit) : null;
                return $row;
            });

            $taken                   = $rows->whereNotNull('sesi_id');
            $schedule->waktu_mulai   = $this->wib($schedule->waktu_mulai);
            $schedule->waktu_selesai = $this->wib($schedule->waktu_selesai);
            $schedule->statistik     = [
                'total_target'    => $rows->count(),
                'sudah_masuk'     => $taken->count(),
                'belum_masuk'     => $rows->whereNull('sesi_id')->count(),
                'rata_rata_nilai' => round((float) $taken->avg('nilai_akhir'), 2),
                'nilai_tertinggi' => $taken->max('nilai_akhir'),
                'nilai_terendah'  => $taken->min('nilai_akhir'),
            ];
            $schedule->hasil = $rows->values();

            return $schedule;
        });

        return $this->ok([
            'kelas'     => $class,
            'schedules' => $schedules,
        ]);
    }

    private function classes()
    {
        return DB::table('kelas_aktifs as k')
            ->join('jurusans as j', 'j.id', '=', 'k.jurusan_id')
            ->join('rombels as r', 'r.id', '=', 'k.rombel_id')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('jadwal_ujian_kelas as jk')
                    ->join('jadwal_ujians as ju', 'ju.id', '=', 'jk.jadwal_ujian_id')
                    ->whereColumn('jk.kelas_aktif_id', 'k.id')
                    ->whereNull('ju.diarsipkan_at');
            })
            ->orderBy('k.tingkat')
            ->orderBy('j.kode_jurusan')
            ->orderByRaw("CAST(r.nama_rombel AS INTEGER)")
            ->get([
                'k.id',
                'k.tingkat',
                'k.jurusan_id',
                'j.kode_jurusan',
                'j.nama_jurusan',
                'k.rombel_id',
                'r.nama_rombel',
                'k.nama_kelas',
            ]);
    }

    private function authorizeResults(): void
    {
        $user = Auth::user();
        abort_unless(
            $user && ($user->can('view-reports') || in_array($user->role, ['SuperAdmin', 'Admin', 'Guru', 'Panitia'], true)),
            403
        );
    }

    private function validateJson(Request $request, array $rules): array
    {
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Filter kelas belum lengkap.',
                'errors' => $validator->errors(),
            ], 422));
        }

        return $validator->validated();
    }

    private function wib(string $datetime): string
    {
        return Carbon::parse($datetime, 'UTC')->timezone('Asia/Jakarta')->format('d M Y H:i');
    }

    private function ok(mixed $data = null): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data]);
    }
}
