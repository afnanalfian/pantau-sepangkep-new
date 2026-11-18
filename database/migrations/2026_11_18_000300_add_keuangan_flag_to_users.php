<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Akses baca matriks TK & ekspor (mis. staf keuangan yang bukan pimpinan/ketua tim)
            $table->boolean('is_keuangan')->default(false)->after('is_petugas_presensi');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_keuangan'));
    }
};
