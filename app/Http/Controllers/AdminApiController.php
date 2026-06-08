<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesDeviceFingerprints;
use App\Models\User;
use App\Services\ImageService;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdminApiController extends Controller
{
    use HandlesDeviceFingerprints;

    public function __construct(private ReportService $reports, private ImageService $images)
    {
    }

    public function students(): JsonResponse
    {
        $this->permission('manage-users', ['SuperAdmin', 'Admin']);

        return $this->ok(User::query()
            ->leftJoin('kelas_aktifs as k', 'k.id', '=', 'users.kelas_aktif_id')
            ->where('users.role', 'Siswa')
            ->orderBy('users.name')
            ->get(['users.id', 'users.username', 'users.name', 'users.status_kehadiran', 'k.nama_kelas as kelas']));
    }

    public function storeStudent(Request $request): JsonResponse
    {
        $this->permission('manage-users', ['SuperAdmin', 'Admin']);
        $data = $request->validate([
            'username' => ['required', 'string', 'unique:users,username'],
            'name' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6'],
            'kelas_aktif_id' => ['required', 'exists:kelas_aktifs,id'],
        ]);
        $user = User::create([
            'username' => $data['username'],
            'name' => $data['name'],
            'email' => $data['username'].'@siswa.local',
            'password' => $data['password'],
            'role' => 'Siswa',
            'kelas_aktif_id' => $data['kelas_aktif_id'],
            'status_kehadiran' => 'hadir',
        ]);
        $user->syncRoles(['Siswa']);

        return $this->ok(['id' => $user->id]);
    }

    public function storeUser(Request $request): JsonResponse
    {
        $this->permission('manage-users', ['SuperAdmin', 'Admin']);
        $data = $request->validate([
            'username' => ['required', 'string', 'unique:users,username'],
            'name' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'in:SuperAdmin,Admin,Guru,Pengawas,Siswa'],
            'kelas_aktif_id' => ['nullable', 'exists:kelas_aktifs,id'],
        ]);
        $user = User::create([
            'username' => $data['username'],
            'name' => $data['name'],
            'email' => $data['username'].'@api.local',
            'password' => $data['password'],
            'role' => $data['role'],
            'kelas_aktif_id' => $data['role'] === 'Siswa' ? ($data['kelas_aktif_id'] ?? null) : null,
            'status_kehadiran' => 'hadir',
        ]);
        $user->syncRoles([$data['role']]);

        return $this->ok(['id' => $user->id]);
    }

    public function updateUser(Request $request, int $id): JsonResponse
    {
        $this->permission('manage-users', ['SuperAdmin', 'Admin']);
        $data = $request->validate([
            'name' => ['required', 'string'],
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
        User::findOrFail($id)->syncRoles([$data['role']]);

        return $this->ok(['id' => $id]);
    }

    public function destroyUser(int $id): JsonResponse
    {
        $this->permission('manage-users', ['SuperAdmin', 'Admin']);
        abort_if((int) Auth::guard('api')->id() === $id, 422, 'Akun sendiri tidak dapat dihapus.');
        abort_if(
            DB::table('sesi_ujians')->where('user_id', $id)->exists(),
            422,
            'User sudah memiliki riwayat ujian. Nonaktifkan aksesnya, jangan hapus histori.'
        );
        DB::table('users')->where('id', $id)->delete();

        return $this->ok();
    }

    public function attendance(Request $request, int $id): JsonResponse
    {
        $this->permission('manage-users', ['SuperAdmin', 'Admin']);
        $data = $request->validate(['status_kehadiran' => ['required', 'in:hadir,izin,sakit,alpha']]);
        DB::table('users')->where('id', $id)->update($data + ['updated_at' => now()]);

        return $this->ok();
    }

    public function rolesPermissions(): JsonResponse
    {
        abort_unless($this->apiUser()?->role === 'SuperAdmin', 403);

        return $this->ok([
            'roles' => Role::with('permissions:id,name')->orderBy('name')->get(['id', 'name']),
            'permissions' => Permission::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function syncRolePermissions(Request $request, int $id): JsonResponse
    {
        abort_unless($this->apiUser()?->role === 'SuperAdmin', 403);
        $data = $request->validate(['permissions' => ['nullable', 'array'], 'permissions.*' => ['string']]);
        Role::findOrFail($id)->syncPermissions($data['permissions'] ?? []);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->ok();
    }

    public function storeClass(Request $request): JsonResponse
    {
        $this->permission('manage-master', ['SuperAdmin', 'Admin']);
        $data = $request->validate([
            'tingkat' => ['required', 'integer'],
            'jurusan_id' => ['required', 'exists:jurusans,id'],
            'rombel_id' => ['required', 'exists:rombels,id'],
            'nama_kelas' => ['required', 'string'],
        ]);
        $id = DB::table('kelas_aktifs')->insertGetId($data + ['created_at' => now(), 'updated_at' => now()]);

        return $this->ok(['id' => $id]);
    }

    public function masters(string $type): JsonResponse
    {
        $this->permission('manage-master', ['SuperAdmin', 'Admin']);
        [$table] = $this->masterConfig($type);

        return $this->ok(DB::table($table)->orderBy('id')->get());
    }

    public function storeMaster(Request $request, string $type): JsonResponse
    {
        $this->permission('manage-master', ['SuperAdmin', 'Admin']);
        [$table, $rules] = $this->masterConfig($type);
        $data = $request->validate($rules);
        $id = DB::table($table)->insertGetId($data + ['created_at' => now(), 'updated_at' => now()]);

        return $this->ok(['id' => $id]);
    }

    public function updateMaster(Request $request, string $type, int $id): JsonResponse
    {
        $this->permission('manage-master', ['SuperAdmin', 'Admin']);
        [$table, $rules] = $this->masterConfig($type);
        DB::table($table)->where('id', $id)->update($request->validate($rules) + ['updated_at' => now()]);

        return $this->ok(['id' => $id]);
    }

    public function destroyMaster(string $type, int $id): JsonResponse
    {
        $this->permission('manage-master', ['SuperAdmin', 'Admin']);
        [$table] = $this->masterConfig($type);
        DB::table($table)->where('id', $id)->delete();

        return $this->ok();
    }

    public function bulkStudents(Request $request): JsonResponse
    {
        $this->permission('manage-users', ['SuperAdmin', 'Admin']);
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:2048']]);
        $rows = IOFactory::load($request->file('file')->getRealPath())->getActiveSheet()->toArray();
        abort_if(count($rows) > 2001, 422, 'Maksimal 2000 baris per import.');
        $imported = 0;
        foreach (array_slice($rows, 1) as $row) {
            $username = trim((string) ($row[0] ?? ''));
            $name = trim((string) ($row[1] ?? ''));
            $classId = (int) ($row[2] ?? 0);
            if ($username === '' || $name === '' || $classId < 1) {
                continue;
            }
            $user = User::updateOrCreate(['username' => $username], [
                'name' => $name,
                'email' => $username.'@siswa.local',
                'password' => (string) ($row[3] ?? $username.'123'),
                'role' => 'Siswa',
                'kelas_aktif_id' => $classId,
                'status_kehadiran' => 'hadir',
            ]);
            $user->syncRoles(['Siswa']);
            $imported++;
        }

        return $this->ok(['imported' => $imported]);
    }

    public function packages(): JsonResponse
    {
        $this->permission('manage-questions', ['SuperAdmin', 'Admin', 'Guru']);

        return $this->ok($this->packageQuery()
            ->leftJoin('bank_soals as b', 'b.paket_soal_id', '=', 'p.id')
            ->groupBy('p.id', 'p.judul', 'p.status', 'mp.nama_mapel', 'u.name')
            ->orderByDesc('p.id')
            ->get(['p.id', 'p.judul', 'p.status', 'mp.nama_mapel', 'u.name as guru', DB::raw('count(b.id) as jumlah_soal')]));
    }

    public function storePackage(Request $request): JsonResponse
    {
        $this->permission('manage-questions', ['SuperAdmin', 'Admin', 'Guru']);
        $data = $request->validate(['judul' => ['required', 'string'], 'mata_pelajaran_id' => ['required', 'exists:mata_pelajarans,id']]);
        $id = DB::table('paket_soals')->insertGetId($data + [
            'pembuat_user_id' => Auth::guard('api')->id(),
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->ok(['id' => $id]);
    }

    public function readyPackage(int $id): JsonResponse
    {
        $this->ownedPackage($id);
        $questions = DB::table('bank_soals')->where('paket_soal_id', $id)->get();
        abort_if($questions->isEmpty(), 422, 'Paket harus memiliki minimal satu soal.');

        $soalIds = $questions->pluck('id')->all();
        $options = DB::table('opsi_jawabans')
            ->whereIn('bank_soal_id', $soalIds)
            ->get(['id', 'bank_soal_id', 'is_benar'])
            ->groupBy('bank_soal_id');

        foreach ($questions as $question) {
            if ($question->tipe_soal === 'PG') {
                $opts = $options->get($question->id, collect());
                abort_if($opts->count() < 2, 422, 'Soal PG minimal memiliki dua opsi.');
                abort_unless($opts->where('is_benar', true)->count() === 1, 422, 'Soal PG harus memiliki tepat satu kunci.');
            }
        }

        DB::table('paket_soals')->where('id', $id)->update(['status' => 'ready', 'updated_at' => now()]);

        return $this->ok();
    }

    public function previewPackage(int $id): JsonResponse
    {
        $package = $this->ownedPackage($id);
        $questions = DB::table('bank_soals')->where('paket_soal_id', $id)->orderBy('urutan')->get();
        $options = DB::table('opsi_jawabans')->whereIn('bank_soal_id', $questions->pluck('id'))->orderBy('kode')->get()->groupBy('bank_soal_id');

        return $this->ok(['package' => $package, 'questions' => $questions, 'options' => $options]);
    }

    public function bulkQuestions(Request $request, int $id): JsonResponse
    {
        $this->ownedPackage($id);
        $request->validate(['file' => ['required', 'file']]);

        return $this->ok(['imported' => 0]);
    }

    public function storeMasterExam(Request $request): JsonResponse
    {
        $this->permission('manage-schedules', ['SuperAdmin', 'Admin']);
        $data = $request->validate([
            'judul' => ['required', 'string'],
            'paket_soal_id' => ['required', 'exists:paket_soals,id'],
            'acak_soal' => ['nullable', 'boolean'],
            'acak_opsi' => ['nullable', 'boolean'],
            'tampilkan_nilai_akhir' => ['nullable', 'boolean'],
            'hasil_visibilitas' => ['nullable', 'in:instant,manual,scheduled'],
            'tanggal_rilis_hasil' => ['nullable', 'date'],
        ]);
        $id = DB::table('master_ujians')->insertGetId([
            'judul' => $data['judul'],
            'paket_soal_id' => $data['paket_soal_id'],
            'acak_soal' => (bool) ($data['acak_soal'] ?? false),
            'acak_opsi' => (bool) ($data['acak_opsi'] ?? false),
            'tampilkan_nilai_akhir' => (bool) ($data['tampilkan_nilai_akhir'] ?? false),
            'hasil_visibilitas' => $data['hasil_visibilitas'] ?? 'manual',
            'tanggal_rilis_hasil' => $data['tanggal_rilis_hasil'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->ok(['id' => $id]);
    }

    public function storeSchedule(Request $request): JsonResponse
    {
        $this->permission('manage-schedules', ['SuperAdmin', 'Admin']);
        $data = $request->validate([
            'master_ujian_id' => ['required', 'exists:master_ujians,id'],
            'kelas_aktif_id' => ['required', 'exists:kelas_aktifs,id'],
            'waktu_mulai' => ['required', 'date'],
            'waktu_selesai' => ['required', 'date'],
            'durasi_menit' => ['required', 'integer'],
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

        return $this->ok(['id' => $id]);
    }

    public function storeQuestion(Request $request, int $paket): JsonResponse
    {
        $this->ownedPackage($paket);
        $data = $request->validate([
            'tipe_soal' => ['required', 'in:PG,ISIAN'],
            'pertanyaan' => ['required', 'string'],
            'bobot_nilai' => ['nullable', 'numeric'],
            'opsi' => ['nullable', 'array'],
        ]);
        $id = DB::table('bank_soals')->insertGetId([
            'paket_soal_id' => $paket,
            'urutan' => (int) DB::table('bank_soals')->where('paket_soal_id', $paket)->max('urutan') + 1,
            'tipe_soal' => $data['tipe_soal'],
            'pertanyaan' => $data['pertanyaan'],
            'bobot_nilai' => $data['bobot_nilai'] ?? 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->replaceOptions($id, $data['tipe_soal'], $data['opsi'] ?? []);

        return $this->ok(['id' => $id]);
    }

    public function updateQuestion(Request $request, int $paket, int $id): JsonResponse
    {
        $this->ownedPackage($paket);
        $data = $request->validate([
            'tipe_soal' => ['required', 'in:PG,ISIAN'],
            'pertanyaan' => ['required', 'string'],
            'bobot_nilai' => ['nullable', 'numeric'],
            'opsi' => ['nullable', 'array'],
        ]);
        DB::table('bank_soals')->where(['id' => $id, 'paket_soal_id' => $paket])->update([
            'tipe_soal' => $data['tipe_soal'],
            'pertanyaan' => $data['pertanyaan'],
            'bobot_nilai' => $data['bobot_nilai'] ?? 1,
            'updated_at' => now(),
        ]);
        $this->replaceOptions($id, $data['tipe_soal'], $data['opsi'] ?? []);

        return $this->ok(['id' => $id]);
    }

    public function deleteQuestion(int $paket, int $id): JsonResponse
    {
        $this->ownedPackage($paket);
        DB::table('bank_soals')->where(['id' => $id, 'paket_soal_id' => $paket])->delete();

        return $this->ok();
    }

    public function regenerate(int $id): JsonResponse
    {
        $this->permission('manage-schedules', ['SuperAdmin', 'Admin']);
        $token = Str::upper(Str::random(6));
        DB::table('jadwal_ujians')->where('id', $id)->update(['token' => $token, 'updated_at' => now()]);

        return $this->ok(['token' => $token]);
    }

    public function resetSession(int $id): JsonResponse
    {
        $this->permission('monitor-exams', ['SuperAdmin', 'Admin', 'Pengawas']);
        DB::transaction(function () use ($id) {
            $session = DB::table('sesi_ujians')->where('id', $id)->lockForUpdate()->first();
            abort_unless($session, 404);

            $this->clearDeviceAccessForUser((int) $session->user_id, Auth::guard('api')->id(), 'api_session_reset');
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

        return $this->ok(['message' => 'Sesi berhasil direset dan antrean jawaban Redis dibersihkan.']);
    }

    public function report(int $jadwal, string $format = 'json')
    {
        $this->permission('view-reports', ['SuperAdmin', 'Admin', 'Guru']);
        $rows = $this->reports->rows($jadwal);
        $stats = $this->reports->stats($rows);

        return $this->ok(['statistik' => $stats, 'hasil_per_siswa' => $rows, 'format' => $format]);
    }

    private function packageQuery()
    {
        $query = DB::table('paket_soals as p')
            ->join('mata_pelajarans as mp', 'mp.id', '=', 'p.mata_pelajaran_id')
            ->join('users as u', 'u.id', '=', 'p.pembuat_user_id');

        if (! in_array($this->apiUser()?->role, ['SuperAdmin', 'Admin'], true)) {
            $query->where('p.pembuat_user_id', Auth::guard('api')->id());
        }

        return $query;
    }

    private function ownedPackage(int $id): object
    {
        $this->permission('manage-questions', ['SuperAdmin', 'Admin', 'Guru']);
        $package = $this->packageQuery()->where('p.id', $id)->first('p.*');
        abort_unless($package, 404);

        return $package;
    }

    private function replaceOptions(int $questionId, string $type, array $options): void
    {
        DB::table('opsi_jawabans')->where('bank_soal_id', $questionId)->delete();
        if ($type !== 'PG') {
            return;
        }
        foreach ($options as $index => $option) {
            DB::table('opsi_jawabans')->insert([
                'bank_soal_id' => $questionId,
                'kode' => $option['kode'] ?? chr(65 + $index),
                'teks_opsi' => $option['teks_opsi'] ?? $option['teks'] ?? '',
                'is_benar' => (bool) ($option['is_benar'] ?? false),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function masterConfig(string $type): array
    {
        return match ($type) {
            'jurusan' => ['jurusans', ['kode_jurusan' => ['required', 'string'], 'nama_jurusan' => ['required', 'string']]],
            'rombel' => ['rombels', ['nama_rombel' => ['required', 'string']]],
            'mapel' => ['mata_pelajarans', ['kode_mapel' => ['required', 'string'], 'nama_mapel' => ['required', 'string']]],
            'kelas' => ['kelas_aktifs', ['tingkat' => ['required', 'integer'], 'jurusan_id' => ['required', 'exists:jurusans,id'], 'rombel_id' => ['required', 'exists:rombels,id'], 'nama_kelas' => ['required', 'string']]],
            default => abort(404),
        };
    }

    private function permission(string $permission, array $roles = []): void
    {
        $user = $this->apiUser();
        abort_unless($user, 401);
        if ($user->role === 'SuperAdmin' || in_array($user->role, $roles, true) || $user->can($permission)) {
            return;
        }
        abort(403);
    }

    private function apiUser(): ?User
    {
        return Auth::guard('api')->user();
    }

    private function ok(mixed $data = null): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data, 'message' => null, 'error' => null]);
    }
}
