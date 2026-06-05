<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tingkats', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('nama_tingkat')->unique();
            $table->timestamps();
        });

        foreach (DB::table('kelas_aktifs')->distinct()->pluck('tingkat') as $tingkat) {
            DB::table('tingkats')->insert([
                'nama_tingkat' => $tingkat,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tingkats');
    }
};
