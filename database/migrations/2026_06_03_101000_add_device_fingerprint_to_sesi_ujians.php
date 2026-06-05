<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sesi_ujians', function (Blueprint $table) {
            $table->string('device_fingerprint', 64)->nullable()->after('device_info');
            $table->json('device_fingerprint_raw')->nullable()->after('device_fingerprint');
            $table->boolean('is_device_locked')->default(false)->after('device_fingerprint_raw');
            
            $table->index('device_fingerprint', 'idx_sesi_device_fingerprint');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sesi_ujians', function (Blueprint $table) {
            $table->dropIndex('idx_sesi_device_fingerprint');
            $table->dropColumn(['device_fingerprint', 'device_fingerprint_raw', 'is_device_locked']);
        });
    }
};
