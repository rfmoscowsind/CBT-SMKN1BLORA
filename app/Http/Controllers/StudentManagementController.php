<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StudentManagementController extends Controller
{
    private function authorizeManagement(): void
    {
        // PERF-1 FIX: Drop ->fresh()->getAllPermissions() — extra DB query per request.
        // ->can() uses Spatie's cached permission set instead.
        abort_unless(
            Auth::user()?->can('manage-users'),
            403
        );
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeManagement();

        $tingkat = $request->query('tingkat');
        $jurusanId = $request->query('jurusan_id');
        $rombelId = $request->query('rombel_id');

        $students = [];
        if ($tingkat !== null && $jurusanId !== null && $rombelId !== null) {
            $students = User::query()
                ->leftJoin('kelas_aktifs as k', 'k.id', '=', 'users.kelas_aktif_id')
                ->leftJoin('jurusans as j', 'j.id', '=', 'k.jurusan_id')
                ->where('users.role', 'Siswa')
                ->where('k.tingkat', $tingkat)
                ->where('k.jurusan_id', $jurusanId)
                ->where('k.rombel_id', $rombelId)
                ->orderBy('users.name')
                ->get([
                    'users.id',
                    'users.username as nisn',
                    'users.name as nama',
                    'users.email',
                    'users.kelas_aktif_id',
                    'users.status_kehadiran',
                    'k.nama_kelas as kelas',
                    'j.nama_jurusan as jurusan',
                ])
                ->map(function ($student) {
                    $student->status = $student->status_kehadiran === 'alpha' ? 'nonaktif' : 'aktif';

                    return $student;
                });
        }

        $classes = \DB::table('kelas_aktifs as k')
            ->join('jurusans as j', 'j.id', '=', 'k.jurusan_id')
            ->join('rombels as r', 'r.id', '=', 'k.rombel_id')
            ->orderBy('k.nama_kelas')
            ->get([
                'k.id',
                'k.nama_kelas',
                'k.tingkat',
                'k.jurusan_id',
                'k.rombel_id',
                'j.nama_jurusan',
                'j.kode_jurusan',
                'r.nama_rombel',
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'students' => $students,
                'classes' => $classes,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManagement();

        $data = $this->validateJson($request, [
            'nisn' => ['required', 'string', 'max:255', 'unique:users,username'],
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'kelas_aktif_id' => ['required', 'exists:kelas_aktifs,id'],
            'password' => ['required', 'string', 'min:6'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
        ]);

        $student = User::create([
            'username' => $data['nisn'],
            'name' => $data['nama'],
            'email' => !empty($data['email']) ? $data['email'] : $data['nisn'].'@siswa.local',
            'password' => $data['password'],
            'role' => 'Siswa',
            'kelas_aktif_id' => $data['kelas_aktif_id'],
            'status_kehadiran' => $data['status'] === 'aktif' ? 'hadir' : 'alpha',
        ]);
        $student->syncRoles(['Siswa']);

        return response()->json(['success' => true, 'data' => ['id' => $student->id]], 201);
    }

    public function update(Request $request, User $student): JsonResponse
    {
        $this->authorizeManagement();
        $this->ensureStudent($student);

        $data = $this->validateJson($request, [
            'nisn' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($student->id)],
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($student->id)],
            'kelas_aktif_id' => ['required', 'exists:kelas_aktifs,id'],
            'password' => ['nullable', 'string', 'min:6'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
        ]);

        $student->update([
            'username' => $data['nisn'],
            'name' => $data['nama'],
            'email' => !empty($data['email']) ? $data['email'] : $data['nisn'].'@siswa.local',
            'kelas_aktif_id' => $data['kelas_aktif_id'],
            'status_kehadiran' => $data['status'] === 'aktif' ? 'hadir' : 'alpha',
            ...(! empty($data['password']) ? ['password' => $data['password']] : []),
        ]);

        return response()->json(['success' => true, 'data' => ['id' => $student->id]]);
    }

    public function password(Request $request, User $student): JsonResponse
    {
        $this->authorizeManagement();
        $this->ensureStudent($student);

        $data = $this->validateJson($request, [
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $student->update(['password' => $data['password']]);

        return response()->json(['success' => true, 'message' => 'Password siswa diperbarui.']);
    }

    public function destroy(User $student): JsonResponse
    {
        $this->authorizeManagement();
        $this->ensureStudent($student);
        $student->delete();

        return response()->json(['success' => true]);
    }

    private function ensureStudent(User $student): void
    {
        abort_unless($student->role === 'Siswa', 404);
    }

    private function validateJson(Request $request, array $rules): array
    {
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Data siswa belum valid.',
                'errors' => $validator->errors(),
            ], 422));
        }

        return $validator->validated();
    }
}
