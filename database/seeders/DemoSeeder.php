<?php

namespace Database\Seeders;

use App\Models\ActivityCatalog;
use App\Models\Leave;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\Team;
use App\Models\User;
use App\Services\AttachmentService;
use App\Services\TaskService;
use App\Support\Settings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * DATA DEMO untuk presentasi.
 *  - Semua akun berawalan "demo." dengan password "demo12345".
 *  - Hapus semua data demo:  php artisan pantau:demo --hapus
 *
 * Jalankan:  php artisan pantau:demo
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'demo12345';

    private TaskService $svc;

    private AttachmentService $files;

    private array $catalog;

    public function run(): void
    {
        mt_srand(2026);
        $this->svc = app(TaskService::class);
        $this->files = app(AttachmentService::class);
        $this->catalog = ActivityCatalog::where('is_active', true)->get()->groupBy('rumpun')->all();

        // Notifikasi push tidak dikirim selama seeding (akun demo belum punya perangkat)
        config(['webpush.public_key' => null]);

        DB::transaction(function () {
            [$kepala, $kasubbag, $teams] = $this->people();
            $this->tasks($kepala, $kasubbag, $teams);
        });
    }

    /* ================================================================ Pegawai & tim */

    private function people(): array
    {
        $make = fn (string $u, string $name, string $jabatan, array $extra = []) => User::updateOrCreate(
            ['username' => $u],
            array_merge([
                'name' => $name, 'jabatan' => $jabatan, 'password' => self::PASSWORD,
                'nip' => '19'.mt_rand(70, 99).str_pad((string) mt_rand(1, 12), 2, '0', STR_PAD_LEFT).str_pad((string) mt_rand(1, 28), 2, '0', STR_PAD_LEFT).'20'.mt_rand(10, 22).'0'.mt_rand(1, 2).'1'.str_pad((string) mt_rand(1, 999), 3, '0', STR_PAD_LEFT),
                'is_active' => true, 'must_change_password' => false,
            ], $extra)
        );

        $kepala = $make('demo.kepala', 'Dr. Andi Rahmat Hidayat, S.Si., M.Si.', 'Kepala BPS Kabupaten', ['is_pimpinan' => true]);
        $kasubbag = $make('demo.kasubbag', 'Nurul Hikmah, S.E.', 'Kepala Subbagian Umum', ['is_pimpinan' => true]);
        Settings::set('primary_pimpinan_id', $kepala->id);

        $roster = [
            'tim-distribusi' => [['demo.ketua.distribusi', 'Syamsul Bahri, S.ST.', 'Statistisi Ahli Muda'],
                ['demo.ratna', 'Ratna Sari, S.Tr.Stat.', 'Statistisi Ahli Pertama'], ['demo.fajar', 'Fajar Ramadhan, A.Md.', 'Statistisi Terampil'], ['demo.dewi', 'Dewi Kartika, S.Stat.', 'Statistisi Ahli Pertama']],
            'tim-neraca' => [['demo.ketua.neraca', 'Muh. Arif Budiman, S.E., M.Si.', 'Statistisi Ahli Muda'],
                ['demo.hasanah', 'Nur Hasanah, S.Tr.Stat.', 'Statistisi Ahli Pertama'], ['demo.irwan', 'Irwan Saputra, S.E.', 'Statistisi Ahli Pertama']],
            'tim-produksi' => [['demo.ketua.produksi', 'Hj. Sitti Aminah, S.P.', 'Statistisi Ahli Muda'],
                ['demo.rizky', 'Rizky Pratama, S.Tr.Stat.', 'Statistisi Ahli Pertama'], ['demo.yusuf', 'Yusuf Maulana, A.Md.', 'Statistisi Terampil'], ['demo.wahyuni', 'Sri Wahyuni, S.P.', 'Statistisi Ahli Pertama']],
            'tim-sosial' => [['demo.ketua.sosial', 'Andi Muhammad Akbar, S.ST., M.Ec.Dev.', 'Statistisi Ahli Muda'],
                ['demo.fitri', 'Fitriani, S.Tr.Stat.', 'Statistisi Ahli Pertama'], ['demo.ilham', 'Ilham Nur, S.Sos.', 'Statistisi Ahli Pertama'], ['demo.marlina', 'Marlina, A.Md.', 'Statistisi Terampil']],
            'tim-ipds' => [['demo.ketua.ipds', 'Ahmad Fauzan, S.Kom., M.T.', 'Pranata Komputer Ahli Muda'],
                ['demo.nanda', 'Nanda Pratiwi, S.Tr.Stat.', 'Pranata Komputer Ahli Pertama'], ['demo.reza', 'Reza Firmansyah, A.Md.Kom.', 'Pranata Komputer Terampil']],
            'tim-umum' => [['demo.ketua.umum', 'Hasmawati, S.E.', 'Analis Keuangan'],
                ['demo.rahma', 'Rahmawati, S.Ak.', 'Bendahara'], ['demo.kurniawan', 'Kurniawan, S.H.', 'Analis SDM Aparatur'], ['demo.sulfiani', 'Sulfiani, A.Md.', 'Arsiparis Terampil']],
        ];

        $teams = [];
        foreach ($roster as $slug => $members) {
            $team = Team::where('slug', $slug)->first();
            if (! $team) {
                continue;
            }
            foreach ($members as $i => [$u, $name, $jab]) {
                $user = $make($u, $name, $jab);
                $team->members()->syncWithoutDetaching([$user->id => ['role' => $i === 0 ? 'ketua' : 'anggota', 'sort_order' => $i]]);
            }
            $teams[$slug] = $team->fresh('members');
        }

        // Kasubbag juga anggota Tim Umum, beberapa pegawai multi-tim
        $teams['tim-umum']->members()->syncWithoutDetaching([$kasubbag->id => ['role' => 'anggota', 'sort_order' => 9]]);
        $teams['tim-ipds']->members()->syncWithoutDetaching([User::where('username', 'demo.irwan')->value('id') => ['role' => 'anggota', 'sort_order' => 9]]);
        $teams['tim-sosial']->members()->syncWithoutDetaching([User::where('username', 'demo.nanda')->value('id') => ['role' => 'anggota', 'sort_order' => 9]]);

        // Ketua Tim Produksi sedang dinas luar → contoh pengalihan verifikasi
        Leave::updateOrCreate(['user_id' => User::where('username', 'demo.ketua.produksi')->value('id'), 'type' => 'dinas_luar'], [
            'start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addDays(3)->toDateString(),
            'note' => 'Rapat koordinasi Sensus Pertanian di BPS Provinsi', 'created_by' => $kepala->id,
        ]);
        Leave::updateOrCreate(['user_id' => User::where('username', 'demo.marlina')->value('id'), 'type' => 'cuti'], [
            'start_date' => now()->startOfMonth()->addDays(2)->toDateString(), 'end_date' => now()->startOfMonth()->addDays(6)->toDateString(),
            'note' => 'Cuti tahunan', 'created_by' => $kepala->id,
        ]);

        return [$kepala, $kasubbag, collect($teams)];
    }

    /* ================================================================ Tugas */

    private function tasks(User $kepala, User $kasubbag, $teams): void
    {
        $titles = [
            'tim-distribusi' => ['Pencacahan Survei Harga Produsen', 'Updating direktori usaha perdagangan', 'Validasi data Survei Harga Konsumen', 'Penyusunan berita resmi statistik inflasi', 'Pemeriksaan dokumen Survei Perdagangan Antar Wilayah'],
            'tim-neraca' => ['Penyusunan tabel PDRB triwulanan', 'Rekonsiliasi data PDRB pengeluaran', 'Penyusunan publikasi PDRB Kecamatan', 'Kompilasi data investasi daerah', 'Analisis indikator ekonomi makro'],
            'tim-produksi' => ['Pengolahan data Ubinan padi', 'Updating listing Survei Pertanian Terintegrasi', 'Validasi data luas panen KSA', 'Pencacahan Survei Industri Mikro Kecil', 'Supervisi lapangan Survei Peternakan'],
            'tim-sosial' => ['Pencacahan Susenas Maret', 'Pemeriksaan dokumen Sakernas', 'Validasi data Susenas Kor', 'Penyusunan publikasi Statistik Kesejahteraan Rakyat', 'Updating rumah tangga sampel Sakernas'],
            'tim-ipds' => ['Backup database & pemeliharaan server', 'Pembaruan konten website BPS Pangkep', 'Pengolahan peta digital wilkerstat', 'Tata letak publikasi Kabupaten Dalam Angka', 'Pembuatan dashboard statistik daerah'],
            'tim-umum' => ['Penyusunan SPJ perjalanan dinas', 'Rekonsiliasi rekening bank bulanan', 'Inventarisasi BMN ruang kerja', 'Pengelolaan usulan kenaikan pangkat', 'Notulensi rapat koordinasi bulanan'],
        ];
        $rumpun = ['tim-ipds' => 'Pranata Komputer', 'tim-umum' => 'Keuangan'];

        foreach ($teams as $slug => $team) {
            $leader = $team->members->firstWhere('pivot.role', 'ketua');
            $members = $team->members->where('pivot.role', 'anggota')->where('is_pimpinan', false)->values();
            $pool = $titles[$slug];

            // 1) Disposisi pimpinan ke ketua tim, diteruskan ke anggota (sub-tugas)
            $parent = $this->make($kepala, $leader, 'Koordinasi '.$pool[0], $this->cat($rumpun[$slug] ?? 'Statistisi', 2), 3, 'tinggi', -12, 10,
                'Mohon dikoordinasikan dengan seluruh anggota tim. Laporkan progres setiap Jumat.');
            $this->svc->start($parent, $leader);
            foreach ($members->take(2) as $m) {
                $child = $this->svc->assign([
                    'title' => $pool[0].' – wilayah '.['Pangkajene', 'Bungoro', 'Labakkang', 'Ma\'rang', 'Segeri'][mt_rand(0, 4)],
                    'catalog_id' => $parent->catalog_id, 'quantity' => mt_rand(2, 4), 'priority' => 'tinggi',
                    'due_date' => now()->addDays(mt_rand(3, 9))->toDateString(), 'team_id' => $team->id,
                    'description' => 'Diteruskan dari disposisi Kepala BPS.',
                ], [$m->id], $leader, $parent)->first();
                $this->backdate($child, -10);
                $this->progress($child, $m, mt_rand(0, 3));
            }

            // 2) Penugasan rutin ketua tim → anggota dengan beragam status
            foreach ($members as $idx => $m) {
                $n = $m->username === 'demo.fajar' ? 1 : mt_rand(3, 6); // demo.fajar sengaja ringan (underload)
                if (in_array($m->username, ['demo.rizky', 'demo.nanda'])) {
                    $n = 8; // contoh overload
                }
                for ($k = 0; $k < $n; $k++) {
                    $title = $pool[($idx + $k + 1) % count($pool)].($k > 2 ? ' tahap '.($k - 1) : '');
                    $ageDays = -mt_rand(2, 28);
                    $t = $this->make($leader, $m, $title, $this->cat($rumpun[$slug] ?? (mt_rand(0, 4) ? 'Statistisi' : 'Umum')), mt_rand(1, 5),
                        ['rendah', 'sedang', 'sedang', 'tinggi'][mt_rand(0, 3)], $ageDays, mt_rand(-4, 15));
                    $this->progress($t, $m, mt_rand(0, 5));
                }
            }

            // 3) Pengajuan mandiri menunggu persetujuan ketua tim
            if ($m = $members->get(1)) {
                $self = $this->svc->submitSelf([
                    'title' => 'Membantu entri data '.['Susenas', 'Sakernas', 'SHP', 'Ubinan'][mt_rand(0, 3)].' tim lain',
                    'quantity' => mt_rand(4, 8), 'complexity' => 'sedang', 'team_id' => $team->id,
                    'description' => 'Diminta membantu karena target entri mendekati batas waktu.',
                    'due_date' => now()->addDays(4)->toDateString(),
                ], $m);
                $this->backdate($self, -1);
            }

            // 4) Tugas ketua tim sendiri (diverifikasi pimpinan)
            $own = $this->make($leader, $leader, 'Laporan kinerja bulanan '.$team->name, $this->cat('Statistisi', 3), 1, 'sedang', -6, 2);
            $this->progress($own, $leader, 3);
        }

        // 5) Disposisi langsung pimpinan ke staf (tembusan ke ketua tim)
        $staf = User::where('username', 'demo.reza')->first();
        $t = $this->make($kepala, $staf, 'Perbaikan jaringan ruang rapat utama', $this->cat('Pranata Komputer', 0), 2, 'tinggi', -1, 1,
            'Sebelum rapat koordinasi Senin pagi.');
        $this->svc->start($t, $staf);

        // 6) Pengajuan perpanjangan waktu
        $ext = Task::where('assignee_id', User::where('username', 'demo.irwan')->value('id'))->whereIn('status', ['todo', 'in_progress'])->first();
        if ($ext) {
            $ext->forceFill(['due_date' => now()->subDay()->toDateString()])->saveQuietly();
            $this->svc->requestExtension($ext, $ext->assignee, now()->addDays(4)->toDateString(), 'Data dari OPD baru diterima kemarin sore.');
        }

        // 7) Satu tugas lewat batas SLA review untuk demo eskalasi
        $late = Task::where('status', 'review')->whereHas('verifier', fn ($q) => $q->where('is_pimpinan', false))->first();
        if ($late) {
            $late->forceFill(['submitted_at' => now()->subDays(4)])->saveQuietly();
        }

        // 7b) Beberapa hasil kerja dikembalikan untuk revisi
        $notes = ['Angka total belum sesuai dengan rekap kecamatan, mohon dicek ulang.', 'Lampirkan foto dokumentasi kegiatan.', 'Format tabel ikuti template terbaru dari provinsi.'];
        Task::where('status', 'review')->where('id', '!=', $late?->id)->with('verifier')->take(3)->get()
            ->each(function (Task $t, $i) use ($notes) {
                if ($t->verifier && ! $t->verifier->isOutOfOffice()) {
                    $this->svc->requestRevision($t, $t->verifier, $notes[$i % 3]);
                }
            });

        // 8) Kasubbag Umum memberi tugas ke Tim Umum
        foreach (['demo.rahma', 'demo.sulfiani'] as $u) {
            $m = User::where('username', $u)->first();
            $t = $this->make($kasubbag, $m, 'Penyiapan bahan rapat evaluasi anggaran', $this->cat('Tata Usaha'), 2, 'sedang', -3, 3);
            $this->progress($t, $m, mt_rand(1, 3));
        }
    }

    /* ================================================================ Helper */

    private function cat(string $rumpun, ?int $index = null): ?ActivityCatalog
    {
        $items = $this->catalog[$rumpun] ?? $this->catalog['Umum'] ?? null;
        if (! $items || $items->isEmpty()) {
            return null;
        }

        return $index !== null ? $items->get(min($index, $items->count() - 1)) : $items->random();
    }

    private function make(User $from, User $to, string $title, ?ActivityCatalog $cat, int $qty, string $priority, int $createdOffset, int $dueOffset, ?string $desc = null): Task
    {
        $t = $this->svc->assign([
            'title' => $title,
            'catalog_id' => $cat?->id,
            'quantity' => $cat ? $qty : $qty * 2,
            'complexity' => $cat->complexity ?? 'sedang',
            'priority' => $priority,
            'start_date' => now()->addDays($createdOffset)->toDateString(),
            'due_date' => now()->addDays($dueOffset)->toDateString(),
            'description' => $desc ?? 'Kerjakan sesuai SOP dan pedoman teknis. Unggah output pada bagian "Output / hasil kerja".',
        ], [$to->id], $from)->first();
        $this->backdate($t, $createdOffset);

        return $t;
    }

    /**
     * 0 = belum dikerjakan, 1 = dikerjakan, 2 = dikerjakan + progres,
     * 3 = menunggu verifikasi, 4 = selesai, 5 = selesai (dengan revisi sebelumnya)
     */
    private function progress(Task $t, User $m, int $stage): void
    {
        if ($stage === 0 || ! $t->exists) {
            return;
        }
        $this->svc->start($t, $m);
        if ($stage >= 2) {
            $this->svc->updateProgress($t, $m, [30, 50, 70][mt_rand(0, 2)]);
        }
        if ($stage >= 3) {
            $this->files->addLink($t, 'https://drive.google.com/drive/folders/contoh-output-'.$t->id, 'Output '.$t->code.'.pdf', 'output', $m);
            if (mt_rand(0, 1)) {
                $this->files->addLink($t, 'https://docs.google.com/spreadsheets/d/contoh-'.$t->id, 'Rekap data '.$t->code, 'dokumen', $m);
            }
            $this->svc->submit($t->fresh(), $m, mt_rand(0, 1) ? 'Sudah selesai, mohon diperiksa.' : null);
        }
        if ($stage === 5) {
            $verifier = $t->fresh()->verifier;
            if ($verifier) {
                $this->svc->requestRevision($t->fresh(), $verifier, 'Tolong lengkapi tabel rekapitulasi per kecamatan.');
                $this->svc->start($t->fresh(), $m);
                $this->svc->submit($t->fresh(), $m, 'Sudah dilengkapi.');
            }
        }
        if ($stage >= 4) {
            $t = $t->fresh();
            $verifier = $t->verifier;
            if ($t->status === 'review' && $verifier && ! $verifier->isOutOfOffice()) {
                $this->svc->approve($t, $verifier);
                // Sebar tanggal selesai di bulan berjalan agar grafik tren terisi
                $done = Carbon::now()->startOfMonth()->addDays(mt_rand(0, max(now()->day - 1, 0)))->setTime(mt_rand(8, 16), mt_rand(0, 59));
                $t->forceFill(['completed_at' => $done->min(now()), 'submitted_at' => $done->copy()->subHours(mt_rand(2, 30))->min(now())])->saveQuietly();
            }
        }
    }

    private function backdate(Task $t, int $days): void
    {
        $at = now()->addDays($days)->setTime(mt_rand(7, 15), mt_rand(0, 59));
        $t->forceFill(['created_at' => $at])->saveQuietly();
        TaskActivity::where('task_id', $t->id)->where('action', 'created')->update(['created_at' => $at]);
    }
}
