<?php

namespace App\Console\Commands;

use App\Models\DriveFolder;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Services\GoogleDriveService;
use Illuminate\Console\Command;

/**
 * Menyesuaikan folder Drive lama dengan struktur baru:
 *   - folder tugas "KODE - Judul" → "Judul"
 *   - file di subfolder kategori (1. Surat Tugas, 2. Dokumen Pendukung, …) dipindah langsung ke folder tugas
 *   - subfolder kategori yang sudah kosong dipindah ke sampah Drive (bisa dipulihkan)
 */
class DriveRapikan extends Command
{
    protected $signature = 'pantau:drive-rapikan {--coba : Hanya tampilkan rencana, tanpa mengubah Drive}';

    protected $description = 'Ubah folder Drive lama: nama folder tugas = judul, file langsung di folder tugas';

    public function handle(GoogleDriveService $drive): int
    {
        if (! $drive->enabled()) {
            $this->error('Google Drive belum aktif.');

            return self::FAILURE;
        }
        $coba = (bool) $this->option('coba');
        $tasks = Task::withTrashed()->whereNotNull('drive_folder_id')->with('assignee')->orderBy('id')->get();
        $this->info(($coba ? '[COBA] ' : '').$tasks->count().' folder tugas diperiksa.');

        $n = ['nama' => 0, 'file' => 0, 'folder' => 0, 'gagal' => 0];
        foreach ($tasks as $t) {
            try {
                $nama = $drive->taskFolderName($t);
                $this->line("• {$t->code}: folder → \"{$nama}\"");
                if (! $coba) {
                    $drive->rename($t->drive_folder_id, $nama);
                }
                $n['nama']++;

                foreach (TaskAttachment::where('task_id', $t->id)->whereNotNull('drive_file_id')->get() as $a) {
                    if (! $coba) {
                        $drive->move($a->drive_file_id, $t->drive_folder_id);
                    }
                    $n['file']++;
                }

                // Subfolder kategori & subfolder unggahan folder milik tugas ini
                $sub = DriveFolder::where('key', 'like', 'task:'.$t->id.':%')->get()
                    ->sortByDesc(fn ($f) => substr_count($f->key, ':')); // yang terdalam dulu
                foreach ($sub as $f) {
                    if ($coba) {
                        $n['folder']++;

                        continue;
                    }
                    if ($drive->countChildren($f->drive_id) === 0) {
                        $drive->delete($f->drive_id); // ke sampah
                        $n['folder']++;
                    }
                    $f->delete();
                }
            } catch (\Throwable $e) {
                $n['gagal']++;
                $this->warn("  gagal: {$t->code} — ".$e->getMessage());
            }
        }

        $this->newLine();
        $this->info(($coba ? '[COBA] Akan ' : 'Selesai: ')."{$n['nama']} folder dinamai ulang, {$n['file']} file dipindah, {$n['folder']} subfolder dikosongkan/dibuang."
            .($n['gagal'] ? " {$n['gagal']} tugas gagal (lihat di atas)." : ''));

        return self::SUCCESS;
    }
}
