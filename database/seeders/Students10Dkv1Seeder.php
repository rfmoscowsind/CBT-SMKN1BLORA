<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class Students10Dkv1Seeder extends Seeder
{
    public function run(): void
    {
        $classId = DB::table('kelas_aktifs')
            ->where('nama_kelas', '10 DKV 1')
            ->value('id');

        if (! $classId) {
            throw new \RuntimeException('Kelas "10 DKV 1" tidak ditemukan.');
        }

        $now = now();

        for ($number = 1; $number <= 36; $number++) {
            $username = 'siswa' . str_pad((string) $number, 2, '0', STR_PAD_LEFT);

            DB::table('users')->updateOrInsert(
                ['username' => $username],
                [
                    'name' => 'Siswa ' . str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                    'email' => $username . '@cbt.local',
                    'password' => Hash::make($username),
                    'role' => 'Siswa',
                    'kelas_aktif_id' => $classId,
                    'status_kehadiran' => 'hadir',
                    'email_verified_at' => $now,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }
}
