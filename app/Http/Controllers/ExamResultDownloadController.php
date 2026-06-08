<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ExamResultDownloadController extends Controller
{
    public function __construct(private ReportService $reports)
    {
    }

    public function options(): JsonResponse
    {
        $this->authorizeResults();

        return $this->ok([
            'classes' => $this->classes(),
            'schedules' => $this->schedules(),
        ]);
    }

    public function preview(Request $request): Response
    {
        [$schedule, $class] = $this->target($request);

        return $this->pdf($schedule, $class)->stream($this->filename($schedule, $class));
    }

    public function download(Request $request): Response
    {
        [$schedule, $class] = $this->target($request);
        $pdf = $this->pdf($schedule, $class);

        DB::table('hasil_ujian_unduhans')->updateOrInsert([
            'jadwal_ujian_id' => $schedule->id,
            'kelas_aktif_id' => $class->id,
        ], [
            'diunduh_oleh_user_id' => Auth::id(),
            'diunduh_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $pdf->download($this->filename($schedule, $class));
    }

    private function target(Request $request): array
    {
        $this->authorizeResults();
        $data = $request->validate([
            'jadwal_id' => ['required', 'integer', 'exists:jadwal_ujians,id'],
            'kelas_aktif_id' => ['required', 'integer', 'exists:kelas_aktifs,id'],
        ]);
        $schedule = DB::table('jadwal_ujians as j')
            ->join('master_ujians as m', 'm.id', '=', 'j.master_ujian_id')
            ->join('paket_soals as p', 'p.id', '=', 'm.paket_soal_id')
            ->join('mata_pelajarans as mp', 'mp.id', '=', 'p.mata_pelajaran_id')
            ->where('j.id', $data['jadwal_id'])
            ->whereNull('j.diarsipkan_at')
            ->first([
                'j.id',
                'm.judul',
                'mp.nama_mapel',
                'mp.kode_mapel',
                'j.waktu_mulai',
                'j.waktu_selesai',
            ]);
        abort_unless($schedule, 404, 'Jadwal ujian tidak ditemukan.');
        abort_unless(DB::table('jadwal_ujian_kelas')->where([
            'jadwal_ujian_id' => $schedule->id,
            'kelas_aktif_id' => $data['kelas_aktif_id'],
        ])->exists(), 403, 'Kelas bukan target jadwal ujian.');
        $class = DB::table('kelas_aktifs as k')
            ->join('jurusans as j', 'j.id', '=', 'k.jurusan_id')
            ->join('rombels as r', 'r.id', '=', 'k.rombel_id')
            ->where('k.id', $data['kelas_aktif_id'])
            ->first(['k.id', 'k.nama_kelas', 'j.nama_jurusan', 'r.nama_rombel']);

        return [$schedule, $class];
    }

    private function pdf(object $schedule, object $class)
    {
        $rows = $this->reports->rowsForClass($schedule->id, $class->id);
        $stats = $this->reports->stats($rows);
        $schedule->waktu_mulai = $this->wib($schedule->waktu_mulai);
        $schedule->waktu_selesai = $this->wib($schedule->waktu_selesai);

        return Pdf::loadView('reports.archive-pdf', compact('schedule', 'class', 'rows', 'stats'))
            ->setPaper('a4', 'landscape');
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

    private function schedules()
    {
        $rawSchedules = DB::table('jadwal_ujians as j')
            ->join('jadwal_ujian_kelas as jk', 'jk.jadwal_ujian_id', '=', 'j.id')
            ->join('master_ujians as m', 'm.id', '=', 'j.master_ujian_id')
            ->join('paket_soals as p', 'p.id', '=', 'm.paket_soal_id')
            ->join('mata_pelajarans as mp', 'mp.id', '=', 'p.mata_pelajaran_id')
            ->leftJoin('hasil_ujian_unduhans as h', function ($join) {
                $join->on('h.jadwal_ujian_id', '=', 'j.id')->on('h.kelas_aktif_id', '=', 'jk.kelas_aktif_id');
            })
            ->whereNull('j.diarsipkan_at')
            ->orderByDesc('j.waktu_mulai')
            ->get([
                'j.id',
                'jk.kelas_aktif_id',
                'm.judul',
                'mp.nama_mapel',
                'mp.kode_mapel',
                'j.waktu_mulai',
                'j.waktu_selesai',
                'h.diunduh_at',
            ]);

        $scheduleIds = $rawSchedules->pluck('id')->unique();

        // FIX: Batch-hitung canArchive \u2014 2 query untuk semua jadwal, bukan N\u00d72 query
        $targetCounts = DB::table('jadwal_ujian_kelas')
            ->whereIn('jadwal_ujian_id', $scheduleIds)
            ->selectRaw('jadwal_ujian_id, count(*) as cnt')
            ->groupBy('jadwal_ujian_id')
            ->pluck('cnt', 'jadwal_ujian_id');

        $downloadCounts = DB::table('hasil_ujian_unduhans')
            ->whereIn('jadwal_ujian_id', $scheduleIds)
            ->selectRaw('jadwal_ujian_id, count(*) as cnt')
            ->groupBy('jadwal_ujian_id')
            ->pluck('cnt', 'jadwal_ujian_id');

        return $rawSchedules->map(function ($schedule) use ($targetCounts, $downloadCounts) {
            $targets   = $targetCounts->get($schedule->id, 0);
            $downloads = $downloadCounts->get($schedule->id, 0);

            $schedule->sedang_aktif   = now()->lt(Carbon::parse($schedule->waktu_selesai, 'UTC'));
            $schedule->waktu_mulai    = $this->wib($schedule->waktu_mulai);
            $schedule->waktu_selesai  = $this->wib($schedule->waktu_selesai);
            $schedule->sudah_diunduh  = (bool) $schedule->diunduh_at;
            $schedule->bisa_diarsipkan = $targets > 0 && $downloads >= $targets;

            return $schedule;
        });
    }

    private function canArchive(int $scheduleId): bool
    {
        $targets = DB::table('jadwal_ujian_kelas')->where('jadwal_ujian_id', $scheduleId)->count();
        $downloads = DB::table('hasil_ujian_unduhans')->where('jadwal_ujian_id', $scheduleId)->count();

        return $targets > 0 && $downloads >= $targets;
    }

    private function filename(object $schedule, object $class): string
    {
        return 'hasil-'.str($schedule->kode_mapel.'-'.$class->nama_kelas)->slug().'.pdf';
    }

    private function authorizeResults(): void
    {
        $user = Auth::user();
        abort_unless(
            $user && ($user->can('view-reports') || in_array($user->role, ['SuperAdmin', 'Admin', 'Guru', 'Panitia'], true)),
            403
        );
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
