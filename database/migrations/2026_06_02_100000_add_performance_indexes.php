<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // sesi_ujian_soals: bank_soal_id sering di-filter (full scan tanpa index)
        Schema::table('sesi_ujian_soals', function (Blueprint $table) {
            $table->index('bank_soal_id', 'idx_ses_bank_soal_id');
            $table->index(['sesi_ujian_id', 'bank_soal_id'], 'idx_ses_sesi_bank');
        });

        // jawaban_siswas: sesi_ujian_id tidak ada index eksplisit di PG
        Schema::table('jawaban_siswas', function (Blueprint $table) {
            $table->index('sesi_ujian_id', 'idx_jaw_sesi_ujian_id');
            $table->index(['sesi_ujian_id', 'bank_soal_id'], 'idx_jaw_sesi_bank');
        });

        // audit_logs: user_id dan sesi_ujian_id sering difilter
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('user_id', 'idx_audit_user_id');
            $table->index('sesi_ujian_id', 'idx_audit_sesi_id');
        });

        // session_events: sesi_ujian_id sering difilter
        Schema::table('session_events', function (Blueprint $table) {
            $table->index('sesi_ujian_id', 'idx_sevt_sesi_id');
        });

        // sesi_ujians: user_id, jadwal_ujian_id, status — key lookup fields
        Schema::table('sesi_ujians', function (Blueprint $table) {
            $table->index('user_id', 'idx_sesi_user_id');
            $table->index('jadwal_ujian_id', 'idx_sesi_jadwal_id');
            $table->index('status', 'idx_sesi_status');
        });
    }

    public function down(): void
    {
        Schema::table('sesi_ujian_soals', function (Blueprint $table) {
            $table->dropIndex('idx_ses_bank_soal_id');
            $table->dropIndex('idx_ses_sesi_bank');
        });
        Schema::table('jawaban_siswas', function (Blueprint $table) {
            $table->dropIndex('idx_jaw_sesi_ujian_id');
            $table->dropIndex('idx_jaw_sesi_bank');
        });
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('idx_audit_user_id');
            $table->dropIndex('idx_audit_sesi_id');
        });
        Schema::table('session_events', function (Blueprint $table) {
            $table->dropIndex('idx_sevt_sesi_id');
        });
        Schema::table('sesi_ujians', function (Blueprint $table) {
            $table->dropIndex('idx_sesi_user_id');
            $table->dropIndex('idx_sesi_jadwal_id');
            $table->dropIndex('idx_sesi_status');
        });
    }
};
