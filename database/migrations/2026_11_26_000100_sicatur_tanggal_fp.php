<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Form Permintaan: tanggal yang ditampilkan & dasar nomor sekarang Tanggal FP (bukan tanggal terbit).
     * Nomor & kelompok nomor (grup) data lama tidak diubah.
     */
    public function up(): void
    {
        DB::table('surats')->where('jenis', 'fp')->whereNotNull('tanggal_fp')
            ->update(['tanggal' => DB::raw('tanggal_fp')]);
        foreach (DB::table('surats')->where('jenis', 'fp')->get(['id', 'tanggal']) as $r) {
            DB::table('surats')->where('id', $r->id)->update(['tahun' => (int) substr((string) $r->tanggal, 0, 4)]);
        }
    }

    public function down(): void
    {
        // tidak dikembalikan (tanggal terbit lama tidak tersimpan terpisah)
    }
};
