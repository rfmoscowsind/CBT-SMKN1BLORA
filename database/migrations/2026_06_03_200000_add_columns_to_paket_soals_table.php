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
        Schema::table('paket_soals', function (Blueprint $table) {
            $table->string('kode_paket', 50)->nullable();
            $table->integer('jumlah_pg')->default(0);
            $table->boolean('has_isian')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('paket_soals', function (Blueprint $table) {
            $table->dropColumn(['kode_paket', 'jumlah_pg', 'has_isian']);
        });
    }
};
