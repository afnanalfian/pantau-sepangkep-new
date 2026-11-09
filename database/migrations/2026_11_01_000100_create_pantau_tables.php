<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color', 20)->default('blue');
            $table->string('icon', 40)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_core')->default(false); // 6 tim utama, tidak bisa dihapus
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('team_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('anggota'); // ketua | anggota
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['team_id', 'user_id']);
        });

        Schema::create('activity_catalogs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('rumpun')->nullable(); // Pranata Komputer, Statistisi, Keuangan, Tata Usaha, Umum
            $table->string('name');
            $table->string('unit')->default('kegiatan'); // satuan output
            $table->decimal('points', 6, 2)->default(1);  // poin per satuan
            $table->string('complexity', 10)->default('rendah'); // rendah | sedang | tinggi
            $table->text('rhk')->nullable(); // butir RHK SKP (KIPPAPP)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->foreignId('parent_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('catalog_id')->nullable()->constrained('activity_catalogs')->nullOnDelete();
            $table->foreignId('creator_id')->constrained('users');
            $table->foreignId('assignee_id')->constrained('users');
            $table->foreignId('verifier_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->longText('description')->nullable();
            $table->string('type', 20)->default('penugasan'); // penugasan | disposisi | mandiri
            $table->string('status', 30)->default('todo')->index();
            $table->string('priority', 10)->default('sedang'); // rendah | sedang | tinggi
            $table->string('complexity', 10)->default('rendah');
            $table->decimal('quantity', 8, 2)->default(1);
            $table->decimal('points', 8, 2)->default(0);
            $table->unsignedTinyInteger('progress')->default(0);
            $table->unsignedInteger('position')->default(0);

            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->date('extension_until')->nullable();
            $table->text('extension_reason')->nullable();

            $table->boolean('sla_escalated')->default(false);
            $table->boolean('reminder_sent')->default(false);
            $table->boolean('overdue_notified')->default(false);

            $table->string('drive_folder_id')->nullable();
            $table->string('drive_folder_url')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['assignee_id', 'status']);
            $table->index(['verifier_id', 'status']);
        });

        Schema::create('task_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 20); // surat_tugas | dokumen | gambar | output
            $table->string('kind', 10)->default('file'); // file | link
            $table->string('name');
            $table->text('url')->nullable(); // link eksternal / webViewLink Google Drive
            $table->string('folder_label')->nullable(); // nama folder jika diunggah per-folder
            $table->string('local_path')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('drive_file_id')->nullable();
            $table->string('sync_status', 15)->default('local'); // local | pending | synced | failed
            $table->text('sync_error')->nullable();
            $table->timestamps();
        });

        Schema::create('task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 15)->default('comment'); // comment | revisi
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('task_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40);
            $table->string('description');
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // cuti | dinas_luar | diklat | sakit
            $table->date('start_date');
            $table->date('end_date');
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            $table->string('endpoint_hash', 64)->unique();
            $table->string('public_key')->nullable();
            $table->string('auth_token')->nullable();
            $table->string('content_encoding', 20)->default('aes128gcm');
            $table->string('device')->nullable();
            $table->timestamps();
        });

        Schema::create('drive_folders', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // root | user:5 | user:5:2026-11 | task:10 | task:10:output
            $table->string('drive_id');
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['notifications', 'drive_folders', 'push_subscriptions', 'settings', 'leaves', 'task_activities',
            'task_comments', 'task_attachments', 'tasks', 'activity_catalogs', 'team_user', 'teams'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
