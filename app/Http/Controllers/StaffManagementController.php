<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StaffManagementController extends Controller
{
    private function authorizeManagement(): void
    {
        abort_unless(
            Auth::user()?->can('manage-users'), // FIXED: no fresh(), pakai Spatie cache
            403
        );
    }

    public function index(): JsonResponse
    {
        $this->authorizeManagement();

        $staff = User::query()
            ->leftJoin('mata_pelajarans as mp', 'mp.id', '=', 'users.mata_pelajaran_id')
            ->where('role', '!=', 'Siswa')
            ->orderBy('users.role')
            ->orderBy('users.name')
            ->get([
                'users.id',
                'users.username as nip',
                'users.name as nama',
                'users.role',
                'users.status_kehadiran',
                'users.mata_pelajaran_id',
                'mp.nama_mapel as mapel',
            ])
            ->map(function ($s) {
                $s->status = $s->status_kehadiran === 'alpha' ? 'nonaktif' : 'aktif';
                $s->mapel = $s->mapel ?: '-';
                return $s;
            });

        return response()->json([
            'success' => true,
            'data' => [
                'staff' => $staff,
                'mapels' => DB::table('mata_pelajarans')
                    ->orderBy('kode_mapel')
                    ->get(['id', 'kode_mapel', 'nama_mapel']),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManagement();

        $data = $this->validateJson($request, [
            'nip' => ['required', 'string', 'max:255', 'unique:users,username'],
            'nama' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::in(['SuperAdmin', 'Admin', 'Guru', 'Pengawas'])],
            'mata_pelajaran_id' => ['nullable', 'exists:mata_pelajarans,id'],
            'password' => ['required', 'string', 'min:6'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
        ]);

        $user = User::create([
            'username' => $data['nip'],
            'name' => $data['nama'],
            'email' => $data['nip'].'@staff.local',
            'password' => $data['password'],
            'role' => $data['role'],
            'mata_pelajaran_id' => $data['role'] === 'Guru' ? ($data['mata_pelajaran_id'] ?? null) : null,
            'status_kehadiran' => $data['status'] === 'aktif' ? 'hadir' : 'alpha',
        ]);
        $user->syncRoles([$data['role']]);

        return response()->json(['success' => true, 'data' => ['id' => $user->id]], 201);
    }

    public function update(Request $request, User $staf): JsonResponse
    {
        $this->authorizeManagement();
        $this->ensureStaff($staf);

        $data = $this->validateJson($request, [
            'nip' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($staf->id)],
            'nama' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::in(['SuperAdmin', 'Admin', 'Guru', 'Pengawas'])],
            'mata_pelajaran_id' => ['nullable', 'exists:mata_pelajarans,id'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
        ]);

        $staf->update([
            'username' => $data['nip'],
            'name' => $data['nama'],
            'email' => $data['nip'].'@staff.local',
            'role' => $data['role'],
            'mata_pelajaran_id' => $data['role'] === 'Guru' ? ($data['mata_pelajaran_id'] ?? null) : null,
            'status_kehadiran' => $data['status'] === 'aktif' ? 'hadir' : 'alpha',
        ]);
        $staf->syncRoles([$data['role']]);

        return response()->json(['success' => true, 'data' => ['id' => $staf->id]]);
    }

    public function password(Request $request, User $staf): JsonResponse
    {
        $this->authorizeManagement();
        $this->ensureStaff($staf);

        $data = $this->validateJson($request, [
            'password' => ['required', 'string', 'min:6'],
        ]);

        $staf->update(['password' => $data['password']]);

        return response()->json(['success' => true, 'message' => 'Password staf diperbarui.']);
    }

    public function destroy(User $staf): JsonResponse
    {
        $this->authorizeManagement();
        $this->ensureStaff($staf);

        abort_if($staf->id === Auth::id(), 422, 'Tidak dapat menghapus akun sendiri.');
        abort_if(
            DB::table('sesi_ujians')->where('user_id', $staf->id)->exists(),
            422,
            'User sudah memiliki riwayat ujian. Nonaktifkan aksesnya, jangan hapus histori.'
        );

        $staf->delete();

        return response()->json(['success' => true]);
    }

    private function ensureStaff(User $user): void
    {
        abort_if($user->role === 'Siswa', 404);
    }

    private function validateJson(Request $request, array $rules): array
    {
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Data staf belum valid.',
                'errors' => $validator->errors(),
            ], 422));
        }

        return $validator->validated();
    }
}
