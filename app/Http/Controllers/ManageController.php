<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesDeviceFingerprints;
use App\Models\User;
use App\Services\ExamService;
use App\Services\ImageService;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ManageController extends Controller
{
    use HandlesDeviceFingerprints;

    public function __construct(private ReportService $reports, private ImageService $images, private ExamService $exams)
    {
    }

    public function mapel(Request $request)
    {
        $this->authorizePermission('manage-master', ['SuperAdmin', 'Admin']);
        $data = $request->validate([
            'kode_mapel' => ['required', 'string', 'max:20', 'unique:mata_pelajarans,kode_mapel'],
            'nama_mapel' => ['required', 'string', 'max:255'],
        ]);
        DB::table('mata_pelajarans')->insert($data + ['created_at' => now(), 'updated_at' => now()]);

        return $this->done($request, 'Mata pelajaran tersimpan.');
    }

    public function jurusan(Request $request)
    {
        $this->authorizePermission('manage-master', ['SuperAdmin', 'Admin']);
        $data = $request->validate([
            'kode_jurusan' => ['required', 'string', 'max:10', 'unique:jurusans,kode_jurusan'],
            'nama_jurusan' => ['required', 'string', 'max:255'],
        ]);
        DB::table('jurusans')->insert($data + ['created_at' => now(), 'updated_at' => now()]);

        return $this->done($request, 'Jurusan tersimpan.');
    }

    public function rombel(Request $request)
    {
        $this->authorizePermission('manage-master', ['SuperAdmin', 'Admin']);
        $data = $request->validate(['nama_rombel' => ['required', 'string', 'max:20']]);
        DB::table('rombels')->insert($data + ['created_at' => now(), 'updated_at' => now()]);

        return $this->done($request, 'Rombel tersimpan.');
    }

    public function kelas(Request $request)
    {
        $this->authorizePermission('manage-master', ['SuperAdmin', 'Admin']);
        $data = $request->validate([
            'tingkat' => ['required', 'integer', 'min:1'],
            'jurusan_id' => ['required', 'exists:jurusans,id'],
            'rombel_id' => ['required', 'exists:rombels,id'],
            'nama_kelas' => ['required', 'string', 'max:255'],
        ]);
        DB::table('kelas_aktifs')->updateOrInsert(
            [
                'tingkat' => $data['tingkat'],
                'jurusan_id' => $data['jurusan_id'],
                'rombel_id' => $data['rombel_id'],
            ],
            $data + ['created_at' => now(), 'updated_at' => now()]
        );

        return $this->done($request, 'Kelas tersimpan.');
    }

    public function updateMaster(Request $request, string $type, int $id)
    {
        $this->authorizePermission('manage-master', ['SuperAdmin', 'Admin']);
        [$table, $rules] = $this->masterConfig($type);
        $data = $request->validate($rules);
        DB::table($table)->where('id', $id)->update($data + ['updated_at' => now()]);

        return $this->done($request, 'Data diperbarui.');
    }

    public function deleteMaster(Request $request, string $type, int $id)
    {
        $this->authorizePermission('manage-master', ['SuperAdmin', 'Admin']);
        [$table] = $this->masterConfig($type);
        DB::table($table)->where('id', $id)->delete();

        return $this->done($request, 'Data dihapus.');
    }

    public function student(Request $request)
    {
        $this->authorizePermission('manage-users', ['SuperAdmin', 'Admin']);
        $data = $request->validate([
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'name' => ['required', 'string', 'max:255'],
            'kelas_aktif_id' => ['required', 'exists:kelas_aktifs,id'],
            'password' => ['nullable', 'string', 'min:6'],
        ]);
        $student = User::create([
            'username' => $data['username'],
            'name' => $data['name'],
            'email' => $data['username'].'@siswa.local',
            'password' => $data['password'] ?? $data['username'].'123',
            'role' => 'Siswa',
            'kelas_aktif_id' => $data['kelas_aktif_id'],
            'status_kehadiran' => 'hadir',
        ]);
        $student->syncRoles(['Siswa']);

        return $this->done($request, 'Siswa tersimpan.', ['id' => $student->id], 201);
    }

    public function staffUser(Request $request)
    {
        $this->authorizePermission('manage-users', ['SuperAdmin', 'Admin']);
        $data = $request->validate([
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'in:SuperAdmin,Admin,Guru,Pengawas'],
        ]);
        $user = User::create([
            'username' => $data['username'],
            'name' => $data['name'],
            'email' => $data['username'].'@staff.local',
            'password' => $data['password'],
            'role' => $data['role'],
            'status_kehadiran' => 'hadir',
        ]);
        $user->syncRoles([$data['role']]);

        return $this->done($request, 'Staf tersimpan.', ['id' => $user->id], 201);
    }

    public function attendance(Request $request, int $id)
    {
        $this->authorizePermission('manage-users', ['SuperAdmin', 'Admin']);
        $data = $request->validate(['status_kehadiran' => ['required', 'in:hadir,izin,sakit,alpha']]);
        DB::table('users')->where('id', $id)->update($data + ['updated_at' => now()]);

        return $this->done($request, 'Kehadiran diperbarui.');
    }

    public function updateUser(Request $request, int $id)
    {
        $this->authorizePermission('manage-users', ['SuperAdmin', 'Admin']);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', 'in:SuperAdmin,Admin,Guru,Pengawas,Siswa'],
            'kelas_aktif_id' => ['nullable', 'exists:kelas_aktifs,id'],
            'password' => ['nullable', 'string', 'min:6'],
        ]);
        $payload = [
            'name' => $data['name'],
            'role' => $data['role'],
            'kelas_aktif_id' => $data['role'] === 'Siswa' ? ($data['kelas_aktif_id'] ?? null) : null,
            'updated_at' => now(),
        ];
        if (! empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }
        DB::table('users')->where('id', $id)->update($payload);
        if ($user = User::find($id)) {
            $user->syncRoles([$data['role']]);
        }

        return $this->done($request, 'User diperbarui.');
    }

    public function deleteUser(Request $request, int $id)
    {
        $this->authorizePermission('manage-users', ['SuperAdmin', 'Admin']);
        abort_if((int) Auth::id() === $id, 422, 'Akun sendiri tidak dapat dihapus.');
        abort_if(
            DB::table('sesi_ujians')->where('user_id', $id)->exists(),
            422,
            'User sudah memiliki riwayat ujian. Nonaktifkan aksesnya, jangan hapus histori.'
        );
        DB::table('users')->where('id', $id)->delete();

        return $this->done($request, 'User dihapus.');
    }

    public function syncRolePermissions(Request $request, int $id)
    {
        abort_unless(Auth::user()?->role === 'SuperAdmin', 403);
        $data = $request->validate(['permissions' => ['nullable', 'array'], 'permissions.*' => ['string']]);
        $role = Role::findOrFail($id);
        $role->syncPermissions($data['permissions'] ?? []);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->done($request, 'Hak akses diperbarui.');
    }

    public function studentTemplate()
    {
        $this->authorizePermission('manage-users', ['SuperAdmin', 'Admin']);

        return response("nisn,nama,kelas_aktif_id,password\n", 200, [
            'content-type' => 'text/csv',
            'content-disposition' => 'attachment; filename=template-siswa.csv',
        ]);
    }

    public function questionTemplate()
    {
        $this->authorizePermission('manage-questions', ['SuperAdmin', 'Guru']);

        return response("tipe_soal,pertanyaan,bobot,opsi_a,opsi_b,opsi_c,opsi_d,opsi_e,kunci_pg\n", 200, [
            'content-type' => 'text/csv',
            'content-disposition' => 'attachment; filename=template-soal.csv',
        ]);
    }

    public function bulk(Request $request)
    {
        $this->authorizePermission('manage-users', ['SuperAdmin', 'Admin']);
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:2048']]);
        $rows = $this->spreadsheetRows($request->file('file')->getRealPath());
        abort_if(count($rows) > 2001, 422, 'Maksimal 2000 baris per import.');
        $imported = 0;
        foreach (array_slice($rows, 1) as $row) {
            $username = trim((string) ($row[0] ?? ''));
            $name = trim((string) ($row[1] ?? ''));
            $classId = (int) ($row[2] ?? 0);
            if ($username === '' || $name === '' || $classId < 1) {
                continue;
            }
            $user = User::updateOrCreate(
                ['username' => $username],
                [
                    'name' => $name,
                    'email' => $username.'@siswa.local',
                    'password' => (string) ($row[3] ?? $username.'123'),
                    'role' => 'Siswa',
                    'kelas_aktif_id' => $classId,
                    'status_kehadiran' => 'hadir',
                ]
            );
            $user->syncRoles(['Siswa']);
            $imported++;
        }

        return $this->done($request, "Import selesai: $imported siswa.", ['imported' => $imported]);
    }

    public function storePackage(Request $request)
    {
        $this->authorizePermission('manage-questions', ['SuperAdmin', 'Guru']);
        $data = $request->validate([
            'mata_pelajaran_id' => ['required', 'exists:mata_pelajarans,id'],
            'judul' => ['required', 'string', 'max:255'],
        ]);
        $id = DB::table('paket_soals')->insertGetId($data + [
            'pembuat_user_id' => Auth::id(),
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->done($request, 'Paket dibuat.', ['id' => $id], 201);
    }

    public function previewPackage(int $id)
    {
        $package = DB::table('paket_soals')->find($id);
        abort_unless($package, 404);
        $questions = DB::table('bank_soals')->where('paket_soal_id', $id)->orderBy('urutan')->get();
        $options = DB::table('opsi_jawabans')->whereIn('bank_soal_id', $questions->pluck('id'))->orderBy('kode')->get()->groupBy('bank_soal_id');

        return view('manage.package-preview', ['p' => $package, 'questions' => $questions, 'options' => $options]);
    }

    public function importQuestions(Request $request, int $id)
    {
        $this->authorizePermission('manage-questions', ['SuperAdmin', 'Guru']);
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:2048']]);
        abort_unless(DB::table('paket_soals')->where('id', $id)->exists(), 404);
        $rows = $this->spreadsheetRows($request->file('file')->getRealPath());
        abort_if(count($rows) > 2001, 422, 'Maksimal 2000 baris per import.');
        $imported = 0;
        foreach (array_slice($rows, 1) as $row) {
            $type = strtoupper(trim((string) ($row[0] ?? '')));
            $question = trim((string) ($row[1] ?? ''));
            if (! in_array($type, ['PG', 'ISIAN'], true) || $question === '') {
                continue;
            }
            $this->insertQuestionFromRow($id, $type, $question, $row);
            $imported++;
        }
        DB::table('paket_soals')->where('id', $id)->update(['status' => 'draft', 'updated_at' => now()]);

        return $this->done($request, "Import soal selesai: $imported.", ['imported' => $imported]);
    }

    public function question(Request $request)
    {
        $this->authorizePermission('manage-questions', ['SuperAdmin', 'Guru']);
        $data = $this->questionPayload($request);
        $id = DB::transaction(function () use ($request, $data): int {
            $questionId = DB::table('bank_soals')->insertGetId([
                'paket_soal_id' => $data['paket_soal_id'],
                'urutan' => (int) DB::table('bank_soals')->where('paket_soal_id', $data['paket_soal_id'])->max('urutan') + 1,
                'tipe_soal' => $data['tipe_soal'],
                'pertanyaan' => $data['pertanyaan'],
                'gambar_url' => $request->hasFile('gambar') ? $this->images->webp($request->file('gambar')) : null,
                'bobot_nilai' => $data['bobot_nilai'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->replaceOptionsFromRequest($questionId, $request, $data['tipe_soal']);
            DB::table('paket_soals')->where('id', $data['paket_soal_id'])->update(['status' => 'draft', 'updated_at' => now()]);

            return $questionId;
        });

        return $this->done($request, 'Soal tersimpan.', ['id' => $id], 201);
    }

    public function updateQuestion(Request $request, int $id)
    {
        $this->authorizePermission('manage-questions', ['SuperAdmin', 'Guru']);
        $data = $this->questionPayload($request);
        $question = DB::table('bank_soals')->find($id);
        abort_unless($question, 404);
        DB::transaction(function () use ($request, $id, $data, $question): void {
            DB::table('bank_soals')->where('id', $id)->update([
                'paket_soal_id' => $data['paket_soal_id'],
                'tipe_soal' => $data['tipe_soal'],
                'pertanyaan' => $data['pertanyaan'],
                'gambar_url' => $request->hasFile('gambar') ? $this->images->webp($request->file('gambar')) : $question->gambar_url,
                'bobot_nilai' => $data['bobot_nilai'],
                'updated_at' => now(),
            ]);
            $this->replaceOptionsFromRequest($id, $request, $data['tipe_soal']);
            DB::table('paket_soals')->where('id', $data['paket_soal_id'])->update(['status' => 'draft', 'updated_at' => now()]);
        });

        return $this->done($request, 'Soal diperbarui.');
    }

    public function deleteQuestion(Request $request, int $id)
    {
        $this->authorizePermission('manage-questions', ['SuperAdmin', 'Guru']);
        DB::table('bank_soals')->where('id', $id)->delete();

        return $this->done($request, 'Soal dihapus.');
    }

    public function ready(Request $request, int $id)
    {
        $this->authorizePermission('manage-questions', ['SuperAdmin', 'Guru']);
        $questions = DB::table('bank_soals')->where('paket_soal_id', $id)->get();
        abort_if($questions->isEmpty(), 422, 'Paket harus memiliki minimal satu soal.');
        $options = DB::table('opsi_jawabans')
            ->whereIn('bank_soal_id', $questions->pluck('id'))
            ->get(['bank_soal_id', 'teks_opsi', 'is_benar'])
            ->groupBy('bank_soal_id');

        foreach ($questions as $question) {
            abort_if(trim((string) $question->pertanyaan) === '', 422, 'Pertanyaan tidak boleh kosong.');
            abort_if(str_starts_with((string) $question->pertanyaan, 'Pertanyaan soal nomor'), 422, 'Masih ada soal placeholder.');
            abort_if((float) $question->bobot_nilai <= 0, 422, 'Bobot soal harus lebih dari 0.');

            if ($question->tipe_soal === 'PG') {
                $opts = $options->get($question->id, collect());
                abort_if($opts->count() < 2, 422, 'Soal PG minimal memiliki dua opsi.');
                abort_if($opts->contains(fn ($opt) => trim((string) $opt->teks_opsi) === ''), 422, 'Teks opsi tidak boleh kosong.');
                abort_unless($opts->where('is_benar', true)->count() === 1, 422, 'Soal PG harus memiliki tepat satu kunci.');
            }
        }
        DB::table('paket_soals')->where('id', $id)->update(['status' => 'ready', 'updated_at' => now()]);

        return $this->done($request, 'Paket siap digunakan.');
    }

    public function masterExam(Request $request)
    {
        $this->authorizePermission('manage-schedules', ['SuperAdmin', 'Admin']);
        $data = $request->validate([
            'judul' => ['required', 'string', 'max:255'],
            'paket_soal_id' => ['required', 'exists:paket_soals,id'],
            'hasil_visibilitas' => ['nullable', 'in:instant,manual,scheduled'],
            'tanggal_rilis_hasil' => ['nullable', 'date'],
        ]);
        $id = DB::table('master_ujians')->insertGetId([
            'judul' => $data['judul'],
            'paket_soal_id' => $data['paket_soal_id'],
            'acak_soal' => $request->boolean('acak_soal'),
            'acak_opsi' => $request->boolean('acak_opsi'),
            'tampilkan_nilai_akhir' => $request->boolean('tampilkan_nilai_akhir'),
            'hasil_visibilitas' => $data['hasil_visibilitas'] ?? 'manual',
            'tanggal_rilis_hasil' => $data['tanggal_rilis_hasil'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->done($request, 'Master ujian dibuat.', ['id' => $id], 201);
    }

    public function schedule(Request $request)
    {
        $this->authorizePermission('manage-schedules', ['SuperAdmin', 'Admin']);
        $data = $request->validate([
            'master_ujian_id' => ['required', 'exists:master_ujians,id'],
            'kelas_aktif_id' => ['required', 'exists:kelas_aktifs,id'],
            'waktu_mulai' => ['required', 'date'],
            'waktu_selesai' => ['required', 'date', 'after:waktu_mulai'],
            'durasi_menit' => ['required', 'integer', 'min:1'],
        ]);
        $id = DB::table('jadwal_ujians')->insertGetId([
            'master_ujian_id' => $data['master_ujian_id'],
            'waktu_mulai' => $data['waktu_mulai'],
            'waktu_selesai' => $data['waktu_selesai'],
            'durasi_menit' => $data['durasi_menit'],
            'gunakan_token' => true,
            'token' => Str::upper(Str::random(6)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('jadwal_ujian_kelas')->insert(['jadwal_ujian_id' => $id, 'kelas_aktif_id' => $data['kelas_aktif_id']]);

        return $this->done($request, 'Jadwal dibuat.', ['id' => $id], 201);
    }

    public function regenerate(Request $request, int $id)
    {
        abort_unless(Auth::user()?->role === 'SuperAdmin', 403);
        $token = Str::upper(Str::random(6));
        DB::table('jadwal_ujians')->where('id', $id)->update(['token' => $token, 'updated_at' => now()]);

        return $this->done($request, 'Token diperbarui.', ['token' => $token]);
    }

    public function resetSession(Request $request, int $id)
    {
        $this->authorizePermission('monitor-exams', ['SuperAdmin', 'Admin', 'Pengawas']);
        DB::transaction(function () use ($id) {
            $session = DB::table('sesi_ujians')->where('id', $id)->lockForUpdate()->first();
            abort_unless($session, 404);

            $this->clearDeviceAccessForUser((int) $session->user_id, Auth::id(), 'session_reset');
            Redis::del("queue_jawaban:$id");
            DB::table('jawaban_siswas')->where('sesi_ujian_id', $id)->delete();
            DB::table('sesi_ujian_soals')->where('sesi_ujian_id', $id)->delete();
            DB::table('sesi_ujians')->where('id', $id)->update([
                'status' => 'reset',
                'waktu_submit' => null,
                'nilai_akhir' => null,
                'last_seen_at' => null,
                'updated_at' => now(),
            ]);
        });

        return response()->json(['success' => true, 'message' => 'Sesi berhasil direset dan antrean jawaban Redis dibersihkan.']);
    }

    public function report(int $id, string $format)
    {
        $this->authorizePermission('view-reports', ['SuperAdmin', 'Admin', 'Guru']);
        $rows = $this->reports->rows($id);
        $stats = $this->reports->stats($rows);
        if ($format === 'json') {
            return response()->json(['success' => true, 'data' => ['statistik' => $stats, 'hasil_per_siswa' => $rows]]);
        }
        if ($format === 'pdf') {
            return response()->view('reports.pdf', ['rows' => $rows, 'stats' => $stats], 200, ['content-type' => 'application/pdf']);
        }

        return response()->json(['success' => true, 'data' => ['statistik' => $stats, 'hasil_per_siswa' => $rows]]);
    }

    public function grade(Request $request, int $id)
    {
        $this->authorizePermission('grade-essays', ['SuperAdmin', 'Guru']);
        $data = $request->validate(['skor' => ['required', 'numeric', 'min:0'], 'komentar' => ['nullable', 'string']]);
        $answer = DB::table('jawaban_siswas')->find($id);
        abort_unless($answer, 404);
        $session = DB::table('sesi_ujians')->where('id', $answer->sesi_ujian_id)->first();
        abort_unless($session && $session->status === 'selesai', 422, 'Penilaian hanya untuk sesi selesai.');
        $this->exams->flushAll((int) $answer->sesi_ujian_id);
        $answer = DB::table('jawaban_siswas')->find($id);
        abort_unless($answer, 404);
        $max = DB::table('bank_soals')->where('id', $answer->bank_soal_id)->value('bobot_nilai');
        abort_if((float) $data['skor'] > (float) $max, 422, 'Skor melebihi bobot soal.');
        DB::table('jawaban_siswas')->where('id', $id)->update([
            'skor' => $data['skor'],
            'skor_manual' => $data['skor'],
            'komentar' => $data['komentar'] ?? null,
            'scoring_status' => 'manually_scored',
            'dinilai_oleh_user_id' => Auth::id(),
            'tanggal_dinilai' => now(),
            'updated_at' => now(),
        ]);
        $total = DB::table('jawaban_siswas')->where('sesi_ujian_id', $answer->sesi_ujian_id)->sum('skor');
        DB::table('sesi_ujians')->where('id', $answer->sesi_ujian_id)->update(['nilai_akhir' => $total, 'updated_at' => now()]);

        return $this->done($request, 'Nilai tersimpan.');
    }

    public function deviceFingerprints(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->role === 'SuperAdmin', 403);

        $rows = DB::table('users as u')
            ->leftJoin('kelas_aktifs as k', 'k.id', '=', 'u.kelas_aktif_id')
            ->leftJoin('sesi_ujians as s', function ($join) {
                $join->on('s.user_id', '=', 'u.id')
                    ->whereRaw('s.id = (select max(s2.id) from sesi_ujians s2 where s2.user_id = u.id)');
            })
            ->leftJoin('jadwal_ujians as j', 'j.id', '=', 's.jadwal_ujian_id')
            ->leftJoin('master_ujians as m', 'm.id', '=', 'j.master_ujian_id')
            ->leftJoin('paket_soals as p', 'p.id', '=', 'm.paket_soal_id')
            ->leftJoin('mata_pelajarans as mp', 'mp.id', '=', 'p.mata_pelajaran_id')
            ->where('u.role', 'Siswa')
            ->orderBy('k.nama_kelas')
            ->orderBy('u.name')
            ->get([
                'u.id',
                'u.name',
                'u.username',
                'u.device_fingerprint as user_device_fingerprint',
                'u.device_fingerprint_raw as user_device_fingerprint_raw',
                'u.is_device_locked',
                'k.nama_kelas as kelas',
                's.id as sesi_id',
                's.status',
                's.ip_address',
                's.device_info',
                's.device_fingerprint',
                's.device_fingerprint_raw',
                's.is_device_locked as session_is_device_locked',
                's.last_seen_at',
                'm.judul as jadwal',
                'mp.nama_mapel as mapel',
            ])
            ->map(function ($row) {
                $row->device_fingerprint = $row->device_fingerprint ?: $row->user_device_fingerprint;
                $row->device_fingerprint_raw = $this->decodeJson($row->device_fingerprint_raw ?: $row->user_device_fingerprint_raw);
                $row->device_info = $this->decodeJson($row->device_info);
                $row->is_device_locked = (bool) $row->is_device_locked || (bool) $row->session_is_device_locked || $row->status === 'terkunci';
                $row->session_is_device_locked = (bool) $row->session_is_device_locked;
                $row->mapel = $row->mapel ?: '-';
                $row->jadwal = $row->jadwal ?: '-';
                unset($row->user_device_fingerprint, $row->user_device_fingerprint_raw);

                return $row;
            });

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function unlockDeviceFingerprint(int $id): JsonResponse
    {
        abort_unless(Auth::user()?->role === 'SuperAdmin', 403);
        abort_unless(DB::table('users')->where(['id' => $id, 'role' => 'Siswa'])->exists(), 404);
        $this->clearDeviceAccessForUser($id, Auth::id(), 'device_unlock');

        return response()->json(['success' => true, 'message' => 'Kunci gawai siswa sudah dibuka. Siswa dapat login memakai gawai baru.']);
    }

    public function lockDeviceFingerprint(int $id): JsonResponse
    {
        abort_unless(Auth::user()?->role === 'SuperAdmin', 403);
        abort_unless(DB::table('users')->where(['id' => $id, 'role' => 'Siswa'])->exists(), 404);
        $this->lockDeviceAccessForUser($id, null, 'manual_device_lock');

        return response()->json(['success' => true, 'message' => 'Akses gawai siswa sudah dikunci.']);
    }

    private function masterConfig(string $type): array
    {
        return match ($type) {
            'jurusan' => ['jurusans', [
                'kode_jurusan' => ['required', 'string', 'max:10'],
                'nama_jurusan' => ['required', 'string', 'max:255'],
            ]],
            'rombel' => ['rombels', ['nama_rombel' => ['required', 'string', 'max:20']]],
            'mapel' => ['mata_pelajarans', [
                'kode_mapel' => ['required', 'string', 'max:20'],
                'nama_mapel' => ['required', 'string', 'max:255'],
            ]],
            'kelas' => ['kelas_aktifs', [
                'tingkat' => ['required', 'integer', 'min:1'],
                'jurusan_id' => ['required', 'exists:jurusans,id'],
                'rombel_id' => ['required', 'exists:rombels,id'],
                'nama_kelas' => ['required', 'string', 'max:255'],
            ]],
            default => abort(404),
        };
    }

    private function questionPayload(Request $request): array
    {
        return $request->validate([
            'paket_soal_id' => ['required', 'exists:paket_soals,id'],
            'tipe_soal' => ['required', 'in:PG,ISIAN'],
            'pertanyaan' => ['required', 'string'],
            'bobot_nilai' => ['nullable', 'numeric', 'min:0'],
            'gambar' => ['nullable', 'image', 'max:2048'],
        ]) + ['bobot_nilai' => 1];
    }

    private function replaceOptionsFromRequest(int $questionId, Request $request, string $type): void
    {
        DB::table('opsi_jawabans')->where('bank_soal_id', $questionId)->delete();
        if ($type !== 'PG') {
            return;
        }
        $correct = strtoupper((string) $request->input('correct'));
        foreach (['A', 'B', 'C', 'D', 'E'] as $code) {
            $text = trim((string) $request->input('option_'.$code));
            if ($text === '') {
                continue;
            }
            DB::table('opsi_jawabans')->insert([
                'bank_soal_id' => $questionId,
                'kode' => $code,
                'teks_opsi' => $text,
                'is_benar' => $correct === $code,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function insertQuestionFromRow(int $packageId, string $type, string $question, array $row): void
    {
        $questionId = DB::table('bank_soals')->insertGetId([
            'paket_soal_id' => $packageId,
            'urutan' => (int) DB::table('bank_soals')->where('paket_soal_id', $packageId)->max('urutan') + 1,
            'tipe_soal' => $type,
            'pertanyaan' => $question,
            'bobot_nilai' => (float) ($row[2] ?: 1),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($type === 'PG') {
            $correct = strtoupper((string) ($row[8] ?? ''));
            foreach (['A', 'B', 'C', 'D', 'E'] as $offset => $code) {
                $text = trim((string) ($row[3 + $offset] ?? ''));
                if ($text === '') {
                    continue;
                }
                DB::table('opsi_jawabans')->insert([
                    'bank_soal_id' => $questionId,
                    'kode' => $code,
                    'teks_opsi' => $text,
                    'is_benar' => $correct === $code,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function spreadsheetRows(string $path): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($extension === 'csv') {
            return array_map('str_getcsv', file($path) ?: []);
        }

        return IOFactory::load($path)->getActiveSheet()->toArray();
    }

    private function authorizePermission(string $permission, array $roles = []): void
    {
        $user = Auth::user();
        abort_unless($user, 401);
        if ($user->role === 'SuperAdmin' || in_array($user->role, $roles, true) || $user->can($permission)) {
            return;
        }
        abort(403);
    }

    private function done(Request $request, string $message = 'OK', mixed $data = null, int $status = 200)
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message, 'data' => $data], $status);
        }

        return back()->with('success', $message);
    }

    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value) || trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
