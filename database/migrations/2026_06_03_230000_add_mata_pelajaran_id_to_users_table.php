<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'mata_pelajaran_id')) {
                $table->foreignId('mata_pelajaran_id')
                    ->nullable()
                    ->after('kelas_aktif_id')
                    ->constrained('mata_pelajarans')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'mata_pelajaran_id')) {
                $table->dropConstrainedForeignId('mata_pelajaran_id');
            }
        });
    }
};
