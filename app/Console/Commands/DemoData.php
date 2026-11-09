<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DemoData extends Command
{
    protected $signature = 'pantau:demo {--hapus : Hapus semua data demo (akun demo.* beserta tugasnya)}';

    protected $description = 'Mengisi / menghapus data demo untuk presentasi';

    public function handle(): int
    {
        if ($this->option('hapus')) {
            return $this->purge();
        }

        if (User::where('username', 'like', 'demo.%')->exists()) {
            $this->warn('Data demo sudah ada. Hapus dulu dengan: php artisan pantau:demo --hapus');

            return self::FAILURE;
        }

        $this->info('Membuat data demo…');
        $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);

        $this->newLine();
        $this->table(['Peran', 'Username', 'Password'], [
            ['Pimpinan (Kepala BPS)', 'demo.kepala', DemoSeeder::PASSWORD],
            ['Pimpinan (Kasubbag Umum)', 'demo.kasubbag', DemoSeeder::PASSWORD],
            ['Ketua Tim IPDS', 'demo.ketua.ipds', DemoSeeder::PASSWORD],
            ['Ketua Tim Sosial', 'demo.ketua.sosial', DemoSeeder::PASSWORD],
            ['Anggota (beban tinggi)', 'demo.nanda', DemoSeeder::PASSWORD],
            ['Anggota (beban rendah)', 'demo.fajar', DemoSeeder::PASSWORD],
            ['Anggota lain', 'demo.reza / demo.ratna / …', DemoSeeder::PASSWORD],
        ]);
        $this->info(Task::whereIn('assignee_id', User::where('username', 'like', 'demo.%')->select('id'))->count().' tugas demo dibuat.');

        return self::SUCCESS;
    }

    private function purge(): int
    {
        $ids = User::withTrashed()->where('username', 'like', 'demo.%')->pluck('id');
        if ($ids->isEmpty()) {
            $this->info('Tidak ada data demo.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Hapus '.$ids->count().' akun demo beserta seluruh tugas, lampiran, dan notifikasinya?', true)) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($ids) {
            $taskIds = Task::withTrashed()
                ->where(fn ($q) => $q->whereIn('assignee_id', $ids)->orWhereIn('creator_id', $ids))
                ->pluck('id');

            Task::withTrashed()->whereIn('parent_id', $taskIds)->update(['parent_id' => null]);
            DB::table('task_attachments')->whereIn('task_id', $taskIds)->delete();
            DB::table('task_comments')->whereIn('task_id', $taskIds)->delete();
            DB::table('task_activities')->whereIn('task_id', $taskIds)->delete();
            Task::withTrashed()->whereIn('id', $taskIds)->forceDelete();

            DB::table('notifications')->where('notifiable_type', User::class)->whereIn('notifiable_id', $ids)->delete();
            DB::table('notifications')->where('data', 'like', '%"task_id":%')->get()
                ->filter(fn ($n) => in_array(json_decode($n->data, true)['task_id'] ?? null, $taskIds->all()))
                ->each(fn ($n) => DB::table('notifications')->where('id', $n->id)->delete());

            DB::table('sessions')->whereIn('user_id', $ids)->delete();
            User::withTrashed()->whereIn('id', $ids)->forceDelete();   // team_user, leaves, push ikut terhapus (cascade)

            if (in_array((int) \App\Support\Settings::get('primary_pimpinan_id'), $ids->all())) {
                \App\Support\Settings::set('primary_pimpinan_id', null);
            }
        });

        $this->info('Data demo dihapus. Tim, katalog, dan akun asli tidak tersentuh.');

        return self::SUCCESS;
    }
}
