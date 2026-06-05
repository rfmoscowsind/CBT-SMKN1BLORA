<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SchoolMasterController extends Controller
{
    private function authorizeManagement(): void
    {
        // PERF-1 FIX: Drop ->fresh()->getAllPermissions() — it fires an extra DB query
        // per request. Use ->can() which relies on Spatie's cached permission set.
        abort_unless(
            Auth::user()?->can('manage-master'),
            403
        );
    }

    public function index(): JsonResponse
    {
        $this->authorizeManagement();

        return $this->ok([
            'tingkats' => DB::table('tingkats')->orderBy('nama_tingkat')->get(),
            'jurusans' => DB::table('jurusans')->orderBy('kode_jurusan')->get(),
            'kelas' => $this->classes(),
            'mapels' => DB::table('mata_pelajarans')->orderBy('kode_mapel')->get(),
        ]);
    }

    public function storeTingkat(Request $request): JsonResponse
    {
        $this->authorizeManagement();
        $data = $this->validateJson($request, [
            'nama_tingkat' => ['required', 'integer', 'min:1', 'max:20', 'unique:tingkats,nama_tingkat'],
        ]);

        $id = DB::table('tingkats')->insertGetId([
            ...$data,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->ok(['id' => $id], 201);
    }

    public function destroyTingkat(int $id): JsonResponse
    {
        $this->authorizeManagement();
        $tingkat = DB::table('tingkats')->find($id);
        abort_unless($tingkat, 404);
        abort_if(
            DB::table('kelas_aktifs')->where('tingkat', $tingkat->nama_tingkat)->exists(),
            422,
            'Tingkat masih digunakan oleh kelas.'
        );
        DB::table('tingkats')->where('id', $id)->delete();

        return $this->ok();
    }

    public function storeJurusan(Request $request): JsonResponse
    {
        $this->authorizeManagement();
        $request->merge(['kode_jurusan' => strtoupper((string) $request->input('kode_jurusan'))]);
        $data = $this->validateJson($request, [
            'kode_jurusan' => ['required', 'string', 'max:10', 'unique:jurusans,kode_jurusan'],
            'nama_jurusan' => ['required', 'string', 'max:255'],
        ]);

        $id = DB::table('jurusans')->insertGetId([
            'kode_jurusan' => $data['kode_jurusan'],
            'nama_jurusan' => $data['nama_jurusan'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->ok(['id' => $id], 201);
    }

    public function updateJurusan(Request $request, int $id): JsonResponse
    {
        $this->authorizeManagement();
        abort_unless(DB::table('jurusans')->where('id', $id)->exists(), 404);
        $request->merge(['kode_jurusan' => strtoupper((string) $request->input('kode_jurusan'))]);
        $data = $this->validateJson($request, [
            'kode_jurusan' => ['required', 'string', 'max:10', Rule::unique('jurusans', 'kode_jurusan')->ignore($id)],
            'nama_jurusan' => ['required', 'string', 'max:255'],
        ]);
        DB::table('jurusans')->where('id', $id)->update([
            'kode_jurusan' => $data['kode_jurusan'],
            'nama_jurusan' => $data['nama_jurusan'],
            'updated_at' => now(),
        ]);

        return $this->ok(['id' => $id]);
    }

    public function destroyJurusan(int $id): JsonResponse
    {
        $this->authorizeManagement();
        abort_if(DB::table('kelas_aktifs')->where('jurusan_id', $id)->exists(), 422, 'Jurusan masih digunakan oleh kelas.');
        DB::table('jurusans')->where('id', $id)->delete();

        return $this->ok();
    }

    public function generateClass(Request $request): JsonResponse
    {
        $this->authorizeManagement();
        $data = $this->validateJson($request, [
            'tingkat_id' => ['required', 'exists:tingkats,id'],
            'jurusan_id' => ['required', 'exists:jurusans,id'],
        ]);

        $class = DB::transaction(function () use ($data) {
            $tingkat = DB::table('tingkats')->lockForUpdate()->find($data['tingkat_id']);
            $jurusan = DB::table('jurusans')->find($data['jurusan_id']);
            $numbers = DB::table('kelas_aktifs as k')
                ->join('rombels as r', 'r.id', '=', 'k.rombel_id')
                ->where('k.tingkat', $tingkat->nama_tingkat)
                ->where('k.jurusan_id', $jurusan->id)
                ->pluck('r.nama_rombel')
                ->map(fn ($value) => (int) $value);
            $next = ($numbers->max() ?? 0) + 1;
            $rombelId = DB::table('rombels')->where('nama_rombel', (string) $next)->value('id')
                ?? DB::table('rombels')->insertGetId([
                    'nama_rombel' => (string) $next,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            $id = DB::table('kelas_aktifs')->insertGetId([
                'tingkat' => $tingkat->nama_tingkat,
                'jurusan_id' => $jurusan->id,
                'rombel_id' => $rombelId,
                'nama_kelas' => "{$tingkat->nama_tingkat} {$jurusan->kode_jurusan} {$next}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return DB::table('kelas_aktifs')->where('id', $id)->first();
        });

        return $this->ok(['kelas' => $class], 201);
    }

    public function importStudents(Request $request, int $id): JsonResponse
    {
        $this->authorizeManagement();
        abort_unless(DB::table('kelas_aktifs')->where('id', $id)->exists(), 404);
        $this->validateJson($request, ['file' => ['required', 'file', 'mimes:xlsx,xls,csv']]);
        $rows = IOFactory::load($request->file('file')->getRealPath())->getActiveSheet()->toArray();
        $imported = 0;
        $errors = [];

        foreach (array_slice($rows, 1) as $index => $row) {
            try {
                $username = trim((string) ($row[0] ?? ''));
                $name = trim((string) ($row[1] ?? ''));
                if ($username === '' || $name === '') {
                    throw new \RuntimeException('NISN dan nama wajib diisi.');
                }
                $user = User::firstOrNew(['username' => $username]);
                if ($user->exists && $user->role !== 'Siswa') {
                    throw new \RuntimeException('Username sudah dipakai oleh akun staf.');
                }
                $user->fill([
                    'name' => $name,
                    'email' => $row[2] ?: $username.'@siswa.local',
                    'role' => 'Siswa',
                    'kelas_aktif_id' => $id,
                    'status_kehadiran' => 'hadir',
                ]);
                if (! $user->exists || ! empty($row[3])) {
                    $user->password = $row[3] ?: 'siswa123';
                }
                $user->save();
                $user->syncRoles(['Siswa']);
                $imported++;
            } catch (\Throwable $exception) {
                $errors[] = ['baris' => $index + 2, 'pesan' => $exception->getMessage()];
            }
        }

        return $this->ok(['imported' => $imported, 'failed' => count($errors), 'errors' => $errors]);
    }

    private function classes()
    {
        return DB::table('kelas_aktifs as k')
            ->join('jurusans as j', 'j.id', '=', 'k.jurusan_id')
            ->join('rombels as r', 'r.id', '=', 'k.rombel_id')
            ->orderBy('k.tingkat')
            ->orderBy('j.kode_jurusan')
            ->orderByRaw("CAST(r.nama_rombel AS INTEGER)")
            ->get([
                'k.id',
                'k.tingkat',
                'k.jurusan_id',
                'j.kode_jurusan',
                'j.nama_jurusan',
                'r.nama_rombel',
                'k.nama_kelas',
            ]);
    }

    private function validateJson(Request $request, array $rules): array
    {
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Data master belum valid.',
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
