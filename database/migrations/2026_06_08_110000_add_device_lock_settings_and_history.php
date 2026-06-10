<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('app_settings')) {
            Schema::create('app_settings', function (Blueprint $table) {
                $table->string('key', 100)->primary();
                $table->text('value')->nullable();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('updated_at')->nullable();
            });
        }

        DB::table('app_settings')->updateOrInsert(
            ['key' => 'device_lock_enabled'],
            ['value' => filter_var(env('DEVICE_LOCK_ENABLED', true), FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false', 'updated_at' => now()]
        );

        if (! Schema::hasTable('device_fingerprint_histories')) {
            Schema::create('device_fingerprint_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('nis', 50)->nullable();
                $table->string('nisn', 50)->nullable();
                $table->string('username', 100)->nullable();
                $table->foreignId('sesi_ujian_id')->nullable()->constrained('sesi_ujians')->nullOnDelete();
                $table->foreignId('jadwal_ujian_id')->nullable()->constrained('jadwal_ujians')->nullOnDelete();
                $table->string('fingerprint', 191);
                $table->json('fingerprint_raw')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->boolean('lock_enabled')->default(true);
                $table->string('action', 50)->default('seen');
                $table->timestamp('created_at')->useCurrent();
            });

            Schema::table('device_fingerprint_histories', function (Blueprint $table) {
                $table->index(['user_id', 'created_at'], 'idx_device_history_user_created');
                $table->index(['nis', 'created_at'], 'idx_device_history_nis_created');
                $table->index(['nisn', 'created_at'], 'idx_device_history_nisn_created');
                $table->index(['sesi_ujian_id', 'created_at'], 'idx_device_history_session_created');
                $table->index(['fingerprint', 'created_at'], 'idx_device_history_fingerprint_created');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('device_fingerprint_histories');
        Schema::dropIfExists('app_settings');
    }
};
