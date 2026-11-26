<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SIBEBAN · Pulsa petugas.
 * Satu pengaturan pulsa = periode (bulan mulai–selesai) + nominal per petugas + daftar petugas.
 * Seorang petugas hanya boleh menerima 1 pulsa pada periode yang beririsan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sibeban_pulsas', function (Blueprint $t) {
            $t->id();
            $t->string('nama')->nullable();                // keterangan, mis. "Pulsa Susenas Maret"
            $t->char('bulan_mulai', 7);                     // YYYY-MM
            $t->char('bulan_selesai', 7);
            $t->unsignedBigInteger('jumlah');               // nominal per petugas (Rp)
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('pembuat')->nullable();
            $t->timestamps();
            $t->index(['bulan_mulai', 'bulan_selesai']);
        });

        Schema::create('sibeban_pulsa_petugas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pulsa_id')->constrained('sibeban_pulsas')->cascadeOnDelete();
            $t->foreignId('mitra_id')->constrained('sibeban_mitras')->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['pulsa_id', 'mitra_id']);
            $t->index('mitra_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sibeban_pulsa_petugas');
        Schema::dropIfExists('sibeban_pulsas');
    }
};
