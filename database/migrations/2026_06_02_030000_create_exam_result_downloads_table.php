<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('jadwal_ujians', function (Blueprint $table) {
            $table->timestamp('diarsipkan_at')->nullable();
        });

        Schema::create('hasil_ujian_unduhans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jadwal_ujian_id')->constrained('jadwal_ujians')->cascadeOnDelete();
            $table->foreignId('kelas_aktif_id')->constrained('kelas_aktifs')->cascadeOnDelete();
            $table->foreignId('diunduh_oleh_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diunduh_at');
            $table->timestamps();
            $table->unique(['jadwal_ujian_id', 'kelas_aktif_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hasil_ujian_unduhans');
        Schema::table('jadwal_ujians', function (Blueprint $table) {
            $table->dropColumn('diarsipkan_at');
        });
    }
};
