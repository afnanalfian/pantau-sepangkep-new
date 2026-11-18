<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PANTAU v2
 *  - Peran tambahan "petugas presensi" & penanda "dihitung sebagai pegawai"
 *  - Presensi kegiatan (apel / rapat)
 *  - SICATUR (pencatatan & penomoran surat)
 *  - Inovator (Portal Inovasi)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_pegawai')->default(true)->after('is_pimpinan');
            $table->boolean('is_petugas_presensi')->default(false)->after('is_pegawai');
        });
        // Akun admin bawaan bukan pegawai (tidak ikut presensi & matriks)
        DB::table('users')->where('username', 'admin')->update(['is_pegawai' => false]);

        Schema::create('presensi_kegiatans', function (Blueprint $table) {
            $table->id();
            $table->string('jenis', 10); // apel | rapat
            $table->string('nama');
            $table->date('tanggal')->index();
            $table->time('jam')->nullable();
            $table->string('tempat')->nullable();
            $table->text('keterangan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('presensi_kehadirans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presensi_kegiatan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 10); // hadir | izin | absen
            $table->string('catatan')->nullable();
            $table->foreignId('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['presensi_kegiatan_id', 'user_id']);
        });

        Schema::create('surats', function (Blueprint $table) {
            $table->id();
            $table->string('jenis', 10); // keluar | masuk
            $table->unsignedSmallInteger('tahun');
            $table->unsignedInteger('nomor_urut');
            $table->string('nomor_surat');
            $table->date('tanggal');
            $table->string('sifat', 5)->nullable();      // B | R | T | P (surat keluar)
            $table->string('klasifikasi', 30)->nullable();
            $table->string('perihal');
            $table->string('pihak')->nullable();         // tujuan (keluar) / asal (masuk)
            $table->string('nomor_asal')->nullable();     // nomor surat dari pengirim (masuk)
            $table->text('keterangan')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('link')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pembuat');                    // nama lengkap pembuat saat dicatat
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['jenis', 'tahun', 'nomor_urut']);
        });

        Schema::create('portal_inovators', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('inovasi')->nullable();
            $table->string('keterangan')->nullable();
            $table->string('foto_path')->nullable();
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_inovators');
        Schema::dropIfExists('surats');
        Schema::dropIfExists('presensi_kehadirans');
        Schema::dropIfExists('presensi_kegiatans');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['is_pegawai', 'is_petugas_presensi']));
    }
};
