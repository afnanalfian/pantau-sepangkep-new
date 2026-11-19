<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SICATUR mengikuti aplikasi Apps Script lama:
 *  - nomor urut per kelompok (tahun; Form Permintaan: bulan + tim teknis) dan nomor sisipan (10.1)
 *  - isian khusus tiap jenis (tujuan, tim teknis, jenis FP, nama petugas, honor, berlaku, link surat SK)
 *  - master kode klasifikasi (Klas Teknis & Klas Fasilitatif) dan log perubahan
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surats', function (Blueprint $t) {
            $t->dropUnique(['jenis', 'tahun', 'nomor_urut']);
        });

        Schema::table('surats', function (Blueprint $t) {
            $t->string('grup', 30)->default('')->after('tahun');          // 2026 | 2026-01-04 (FP: bulan-tim)
            $t->unsignedSmallInteger('sisip')->default(0)->after('nomor_urut'); // 0 = nomor biasa; 1 → 010.1
            $t->string('no_urut', 20)->nullable()->after('sisip');        // teks tampil: 010 / 010.1
            $t->boolean('lengkap')->default(true)->after('nomor_surat');
            $t->text('tujuan')->nullable()->after('perihal');
            $t->string('tim_teknis', 40)->nullable()->after('tujuan');
            $t->string('jenis_fp', 40)->nullable()->after('tim_teknis');
            $t->date('tanggal_fp')->nullable()->after('jenis_fp');
            $t->string('nama_petugas', 1000)->nullable()->after('tanggal_fp');
            $t->string('honor')->nullable()->after('nama_petugas');
            $t->string('link_surat', 1000)->nullable()->after('honor');
            $t->string('status', 10)->nullable()->after('link_surat');   // Proses | Selesai (SK)
            $t->string('link_oleh')->nullable()->after('status');
            $t->timestamp('link_at')->nullable()->after('link_oleh');
        });

        DB::table('surats')->update(['grup' => DB::raw('tahun')]);

        Schema::table('surats', function (Blueprint $t) {
            $t->unique(['jenis', 'grup', 'nomor_urut', 'sisip']);
            $t->index(['jenis', 'tanggal']);
        });

        Schema::create('sicatur_klasifikasis', function (Blueprint $t) {
            $t->id();
            $t->string('kode', 40);
            $t->string('uraian', 500)->nullable();
            $t->string('sumber', 20);          // Teknis | Fasilitatif
            $t->unsignedInteger('urutan')->default(0);
            $t->timestamps();
            $t->unique(['sumber', 'kode']);
        });

        Schema::create('surat_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('surat_id')->constrained('surats')->cascadeOnDelete();
            $t->string('kolom', 60);
            $t->text('nilai_lama')->nullable();
            $t->text('nilai_baru')->nullable();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('oleh')->nullable();
            $t->string('alasan')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surat_logs');
        Schema::dropIfExists('sicatur_klasifikasis');
        Schema::table('surats', function (Blueprint $t) {
            $t->dropUnique(['jenis', 'grup', 'nomor_urut', 'sisip']);
            $t->dropIndex(['jenis', 'tanggal']);
            $t->dropColumn(['grup', 'sisip', 'no_urut', 'lengkap', 'tujuan', 'tim_teknis', 'jenis_fp', 'tanggal_fp', 'nama_petugas', 'honor', 'link_surat', 'status', 'link_oleh', 'link_at']);
            $t->unique(['jenis', 'tahun', 'nomor_urut']);
        });
    }
};
