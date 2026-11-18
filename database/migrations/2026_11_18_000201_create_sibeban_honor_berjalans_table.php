<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rekap honor berjalan mitra per bulan (sumber: file Excel yang diunggah pegawai
     * atau impor matriks). Satu baris per mitra per bulan — unggahan terbaru menimpa.
     */
    public function up(): void
    {
        Schema::create('sibeban_honor_berjalans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mitra_id')->constrained('sibeban_mitras')->cascadeOnDelete();
            $table->char('bulan', 7);                       // 'YYYY-MM'
            $table->unsignedBigInteger('jumlah')->default(0);
            // kegiatan yang unggahannya menjadi sumber rekap ini (null = impor matriks / manual)
            $table->foreignId('kegiatan_id')->nullable()->constrained('sibeban_kegiatans')->nullOnDelete();
            $table->string('sumber')->nullable();
            $table->timestamps();                           // updated_at = waktu rekap
            $table->unique(['mitra_id', 'bulan']);
            $table->index('bulan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sibeban_honor_berjalans');
    }
};
