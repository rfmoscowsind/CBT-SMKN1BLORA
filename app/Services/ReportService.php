<?php
namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function rows(int $scheduleId): Collection
    {
        return $this->rowsForClass($scheduleId);
    }

    public function rowsForClass(int $scheduleId, ?int $classId = null): Collection
    {
        $answers = DB::table('jawaban_siswas as a')
            ->join('sesi_ujians as s2', 's2.id', '=', 'a.sesi_ujian_id')
            ->where('s2.jadwal_ujian_id', $scheduleId)
            ->select(
                'a.sesi_ujian_id',
                DB::raw("coalesce(sum(case when a.tipe_soal='PG' then a.skor else 0 end),0) as nilai_pg"),
                DB::raw("coalesce(sum(case when a.tipe_soal='ISIAN' then a.skor else 0 end),0) as nilai_isian")
            )
            ->groupBy('a.sesi_ujian_id');
        $query = DB::table('jadwal_ujian_kelas as jk')
            ->join('users as u', fn ($join) => $join->on('u.kelas_aktif_id', '=', 'jk.kelas_aktif_id')->where('u.role', '=', 'Siswa'))
            ->leftJoin('sesi_ujians as s', fn ($join) => $join->on('s.user_id', '=', 'u.id')->on('s.jadwal_ujian_id', '=', 'jk.jadwal_ujian_id'))
            ->leftJoinSub($answers, 'a', 'a.sesi_ujian_id', '=', 's.id')
            ->where('jk.jadwal_ujian_id', $scheduleId);
        if ($classId !== null) {
            $query->where('jk.kelas_aktif_id', $classId);
        }
        $rows = $query->select(
            'u.username',
            'u.name',
            'u.status_kehadiran',
            DB::raw("coalesce(s.status,'belum_masuk') as status"),
            's.waktu_submit',
            DB::raw('coalesce(a.nilai_pg,0) as nilai_pg'),
            DB::raw('coalesce(a.nilai_isian,0) as nilai_isian'),
            DB::raw('coalesce(s.nilai_akhir,0) as nilai_akhir'),
            's.id as sesi_id'
        )->orderByDesc('s.nilai_akhir')->orderBy('u.name')->get();
        $rank = 0;

        return $rows->map(function ($row) use (&$rank) {
            $row->ranking = $row->sesi_id ? ++$rank : null;

            return $row;
        });
    }

    public function stats(Collection $rows): array
    {
        $taken = $rows->whereNotNull('sesi_id');

        return [
            'total_target' => $rows->count(),
            'sudah_masuk' => $taken->count(),
            'belum_masuk' => $rows->whereNull('sesi_id')->count(),
            'rata_rata_nilai' => round((float) $taken->avg('nilai_akhir'), 2),
            'nilai_tertinggi' => $taken->max('nilai_akhir'),
            'nilai_terendah' => $taken->min('nilai_akhir'),
        ];
    }
}
