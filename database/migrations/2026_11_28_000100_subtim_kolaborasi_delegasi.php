<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - Subtim: tim di bawah naungan tim utama (teams.parent_id).
 * - Tugas bersama: satu tugas dikerjakan beberapa pegawai (task_collaborators).
 * - Alih tugas (delegasi) antar pegawai yang bisa diterima / ditolak (task_delegations).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $t) {
            $t->foreignId('parent_id')->nullable()->after('id')->constrained('teams')->nullOnDelete();
        });

        Schema::create('task_collaborators', function (Blueprint $t) {
            $t->id();
            $t->foreignId('task_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('status', 12)->default('aktif');      // aktif | diajukan (menunggu persetujuan pemberi tugas)
            $t->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['task_id', 'user_id']);
            $t->index(['user_id', 'status']);
        });

        Schema::create('task_delegations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('task_id')->constrained()->cascadeOnDelete();
            $t->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $t->text('note')->nullable();
            $t->string('status', 12)->default('menunggu');   // menunggu | diterima | ditolak | dibatalkan
            $t->text('reply')->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
            $t->index(['to_user_id', 'status']);
            $t->index(['task_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_delegations');
        Schema::dropIfExists('task_collaborators');
        Schema::table('teams', function (Blueprint $t) {
            $t->dropConstrainedForeignId('parent_id');
        });
    }
};
