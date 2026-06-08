<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $indexes = [
            "CREATE UNIQUE INDEX CONCURRENTLY IF NOT EXISTS uq_sesi_user_jadwal ON sesi_ujians (user_id, jadwal_ujian_id)",
            "CREATE UNIQUE INDEX CONCURRENTLY IF NOT EXISTS uq_jawaban_session_soal ON jawaban_siswas (sesi_ujian_id, bank_soal_id)",
            "CREATE UNIQUE INDEX CONCURRENTLY IF NOT EXISTS uq_sesi_soal ON sesi_ujian_soals (sesi_ujian_id, bank_soal_id)",
            "CREATE UNIQUE INDEX CONCURRENTLY IF NOT EXISTS uq_sesi_nomor ON sesi_ujian_soals (sesi_ujian_id, nomor_soal)",
            "CREATE UNIQUE INDEX CONCURRENTLY IF NOT EXISTS uq_bank_soals_paket_urutan ON bank_soals (paket_soal_id, urutan)",
            "CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_sesi_jadwal_status ON sesi_ujians (jadwal_ujian_id, status)",
            "CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_sesi_user_status ON sesi_ujians (user_id, status)",
            "CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_sesi_last_seen ON sesi_ujians (last_seen_at)",
            "CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_jawaban_session_tipe ON jawaban_siswas (sesi_ujian_id, tipe_soal)",
            "CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_jawaban_scoring_status ON jawaban_siswas (scoring_status)",
            "CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_jadwal_ujian_kelas_kelas_jadwal ON jadwal_ujian_kelas (kelas_aktif_id, jadwal_ujian_id)",
            "CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_jadwal_ujians_active_time ON jadwal_ujians (diarsipkan_at, waktu_mulai, waktu_selesai)",
            "CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_jadwal_ujians_master ON jadwal_ujians (master_ujian_id)",
            "CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_session_events_session_created ON session_events (sesi_ujian_id, created_at DESC)",
        ];

        foreach ($indexes as $sql) {
            DB::unprepared($sql);
        }

        DB::unprepared("CREATE INDEX IF NOT EXISTS idx_audit_logs_session_action_created ON audit_logs (sesi_ujian_id, action, created_at DESC)");
        DB::unprepared("CREATE INDEX IF NOT EXISTS idx_audit_logs_user_created ON audit_logs (user_id, created_at DESC)");

        DB::unprepared("
            CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_opsi_correct
            ON opsi_jawabans (bank_soal_id, id)
            WHERE is_benar = true
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared('DROP INDEX IF EXISTS idx_audit_logs_user_created');
        DB::unprepared('DROP INDEX IF EXISTS idx_audit_logs_session_action_created');

        foreach ([
            'idx_opsi_correct',
            'idx_session_events_session_created',
            'idx_jadwal_ujians_master',
            'idx_jadwal_ujians_active_time',
            'idx_jadwal_ujian_kelas_kelas_jadwal',
            'idx_jawaban_scoring_status',
            'idx_jawaban_session_tipe',
            'idx_sesi_last_seen',
            'idx_sesi_user_status',
            'idx_sesi_jadwal_status',
            'uq_bank_soals_paket_urutan',
            'uq_sesi_nomor',
            'uq_sesi_soal',
            'uq_jawaban_session_soal',
            'uq_sesi_user_jadwal',
        ] as $index) {
            DB::unprepared("DROP INDEX CONCURRENTLY IF EXISTS {$index}");
        }
    }
};
