<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Kolom `nip` = NIP Baru (18 digit, NIP utama). NIP Lama disimpan sebagai informasi. */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nip_lama', 20)->nullable()->index()->after('nip');
            $table->string('golongan', 10)->nullable()->after('jabatan');
            $table->string('pangkat', 60)->nullable()->after('golongan');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['nip_lama', 'golongan', 'pangkat']));
    }
};
