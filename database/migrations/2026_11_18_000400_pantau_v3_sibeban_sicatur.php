<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v3:
 *  - SIBEBAN: wilayah mitra (kecamatan, desa/kelurahan), jabatan bebas & boleh kosong
 *    (petugas dipilih dulu, jabatan & honor diisi lewat template), pembuat kegiatan.
 *  - SICATUR: kolom tambahan untuk surat tugas & dokumen lain, daftar tautan (Link Surat).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sibeban_mitras', function (Blueprint $t) {
            $t->string('kecamatan', 100)->nullable()->after('nama')->index();
            $t->string('desa', 100)->nullable()->after('kecamatan')->index();
        });

        Schema::table('sibeban_penugasans', function (Blueprint $t) {
            $t->string('jabatan', 60)->nullable()->change();
        });

        Schema::table('sibeban_kegiatans', function (Blueprint $t) {
            $t->foreignId('user_id')->nullable()->after('sumber')->constrained('users')->nullOnDelete();
            $t->string('pembuat')->nullable()->after('user_id');
        });

        Schema::table('surats', function (Blueprint $t) {
            $t->date('tanggal_mulai')->nullable()->after('tanggal');
            $t->date('tanggal_selesai')->nullable()->after('tanggal_mulai');
            $t->string('tempat')->nullable()->after('pihak');
            $t->text('pegawai')->nullable()->after('tempat'); // JSON daftar nama pegawai (surat tugas)
        });

        Schema::create('portal_links', function (Blueprint $t) {
            $t->id();
            $t->string('grup', 30)->index();   // sicatur
            $t->string('judul');
            $t->string('url', 1000);
            $t->string('keterangan')->nullable();
            $t->unsignedSmallInteger('urutan')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_links');
        Schema::table('surats', fn (Blueprint $t) => $t->dropColumn(['tanggal_mulai', 'tanggal_selesai', 'tempat', 'pegawai']));
        Schema::table('sibeban_kegiatans', function (Blueprint $t) {
            $t->dropConstrainedForeignId('user_id');
            $t->dropColumn('pembuat');
        });
        Schema::table('sibeban_mitras', fn (Blueprint $t) => $t->dropColumn(['kecamatan', 'desa']));
    }
};
