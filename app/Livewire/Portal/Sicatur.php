<?php

namespace App\Livewire\Portal;

use App\Livewire\Concerns\Toasts;
use App\Models\PortalLink;
use App\Models\Surat;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

#[Title('SICATUR · Sistem Pencatatan Surat')]
#[Layout('layouts.portal')]
class Sicatur extends Component
{
    use Toasts, WithFileUploads, WithPagination;

    /** beranda | <kode jenis> | riwayat | link | pengaturan */
    #[Url(except: 'beranda')]
    public string $tab = 'beranda';

    // ---- Riwayat ----
    #[Url(except: '')]
    public string $fJenis = '';

    #[Url(except: '')]
    public string $search = '';

    #[Url]
    public string $tahun = '';

    #[Url(except: '')]
    public string $bulan = '';

    #[Url(except: false)]
    public bool $milikSaya = false;

    // ---- Form nomor ----
    public array $form = [];
    public $file;
    public ?int $editId = null;
    public bool $editTampil = false;
    public ?array $baru = null;
    public ?int $confirmDeleteId = null;

    // ---- Link surat (admin) ----
    public bool $linkTampil = false;
    public ?int $linkId = null;
    public array $linkForm = [];
    public ?int $hapusLinkId = null;

    // ---- Pengaturan (admin) ----
    public array $format = [];
    public array $mulai = [];
    public string $pedomanUrl = '';
    public $pedomanFile;
    public $imporFile;

    public function mount(): void
    {
        $this->tahun = $this->tahun ?: now()->format('Y');
        if (! $this->tabValid($this->tab)) {
            $this->tab = 'beranda';
        }
        if (Surat::jenis($this->tab)) {
            $this->siapkanForm();
        }
        if ($this->tab === 'pengaturan') {
            $this->siapkanPengaturan();
        }
    }

    private function tabValid(string $t): bool
    {
        return in_array($t, ['beranda', 'riwayat', 'link'], true) || Surat::jenis($t) || ($t === 'pengaturan' && Auth::user()->is_admin);
    }

    public function bukaTab(string $t): void
    {
        if (! $this->tabValid($t)) {
            return;
        }
        $this->tab = $t;
        $this->baru = null;
        $this->resetValidation();
        if (Surat::jenis($t)) {
            $this->siapkanForm();
        }
        if ($t === 'pengaturan') {
            $this->siapkanPengaturan();
        }
        $this->resetPage();
    }

    public function updating($name): void
    {
        if (in_array($name, ['fJenis', 'search', 'tahun', 'bulan', 'milikSaya'], true)) {
            $this->resetPage();
        }
    }

    // =====================================================================
    // Terbitkan nomor
    // =====================================================================

    private function siapkanForm(): void
    {
        $j = Surat::jenis($this->tab);
        $this->reset('file', 'editId');
        $this->form = [
            'tanggal' => now()->toDateString(),
            'klasifikasi' => $j['klasifikasi'] ?? '',
            'perihal' => '', 'pihak' => '', 'tempat' => '', 'pegawai' => [],
            'tanggal_mulai' => '', 'tanggal_selesai' => '', 'keterangan' => '', 'link' => '',
        ];
    }

    #[Computed]
    public function preview(): ?string
    {
        $kode = $this->editId ? null : $this->tab;
        if (! $kode || ! Surat::jenis($kode)) {
            return null;
        }
        try {
            $tgl = ! empty($this->form['tanggal']) ? Carbon::parse($this->form['tanggal']) : now();
        } catch (\Throwable) {
            $tgl = now();
        }

        return Surat::buatNomor($kode, $tgl, $this->form['klasifikasi'] ?? null)[2];
    }

    private function aturan(array $j): array
    {
        $isian = $j['isian'];
        $r = [
            'form.tanggal' => 'required|date',
            'form.perihal' => 'required|string|max:500',
            'form.keterangan' => 'nullable|string|max:2000',
            'form.link' => 'nullable|url|max:500',
            'file' => 'nullable|file|max:20480',
        ];
        if (str_contains($j['format'], '{klasifikasi}')) {
            $r['form.klasifikasi'] = 'required|string|max:30';
        }
        if (isset($isian['pihak'])) {
            $r['form.pihak'] = (str_contains($isian['pihak'], 'opsional') ? 'nullable' : 'required').'|string|max:255';
        }
        if (isset($isian['tempat'])) {
            $r['form.tempat'] = 'nullable|string|max:255';
        }
        if (isset($isian['pegawai'])) {
            $r['form.pegawai'] = 'required|array|min:1';
            $r['form.pegawai.*'] = 'string|max:255';
        }
        if (isset($isian['pelaksanaan'])) {
            $r['form.tanggal_mulai'] = 'nullable|date';
            $r['form.tanggal_selesai'] = 'nullable|date|after_or_equal:form.tanggal_mulai';
        }

        return $r;
    }

    private function namaAtribut(array $j): array
    {
        $n = ['form.tanggal' => 'tanggal', 'form.klasifikasi' => 'klasifikasi', 'form.link' => 'link', 'file' => 'file'];
        foreach ($j['isian'] as $k => $label) {
            $n['form.'.$k] = mb_strtolower(preg_replace('/\s*\(.*\)$/', '', $label));
        }
        $n['form.tanggal_mulai'] = 'tanggal mulai';
        $n['form.tanggal_selesai'] = 'tanggal selesai';

        return $n;
    }

    private function dataForm(array $j): array
    {
        $f = $this->form;
        $d = [
            'perihal' => trim($f['perihal']),
            'klasifikasi' => str_contains($j['format'], '{klasifikasi}') ? strtoupper(trim((string) $f['klasifikasi'])) : null,
            'pihak' => isset($j['isian']['pihak']) && trim((string) $f['pihak']) !== '' ? trim($f['pihak']) : null,
            'tempat' => isset($j['isian']['tempat']) && trim((string) $f['tempat']) !== '' ? trim($f['tempat']) : null,
            'pegawai' => isset($j['isian']['pegawai']) ? array_values($f['pegawai']) : null,
            'tanggal_mulai' => isset($j['isian']['pelaksanaan']) && $f['tanggal_mulai'] ? $f['tanggal_mulai'] : null,
            'tanggal_selesai' => isset($j['isian']['pelaksanaan']) && $f['tanggal_selesai'] ? $f['tanggal_selesai'] : null,
            'keterangan' => trim((string) $f['keterangan']) ?: null,
            'link' => trim((string) $f['link']) ?: null,
        ];

        return $d;
    }

    public function terbitkan(): void
    {
        $j = Surat::jenis($this->tab);
        abort_unless($j, 404);
        $this->validate($this->aturan($j), [], $this->namaAtribut($j));
        $data = $this->dataForm($j);
        $u = Auth::user();

        // Nomor urut unik per jenis & tahun; bila dua orang menyimpan bersamaan, coba lagi.
        for ($try = 0; ; $try++) {
            try {
                [$tahun, $urut, $nomor] = Surat::buatNomor($this->tab, Carbon::parse($this->form['tanggal']), $data['klasifikasi']);
                $s = Surat::create($data + [
                    'jenis' => $this->tab, 'tanggal' => $this->form['tanggal'], 'sifat' => 'B',
                    'tahun' => $tahun, 'nomor_urut' => $urut, 'nomor_surat' => $nomor,
                    'user_id' => $u->id, 'pembuat' => $u->name ?: $u->username,
                ]);
                break;
            } catch (UniqueConstraintViolationException $e) {
                if ($try >= 4) {
                    throw $e;
                }
            }
        }
        $this->simpanFile($s);

        $this->baru = ['nomor' => $s->nomor_surat, 'perihal' => $s->perihal, 'jenis' => $j['singkat']];
        $this->siapkanForm();
        unset($this->preview);
        $this->js("window.scrollTo({ top: 0, behavior: 'smooth' })");
    }

    private function simpanFile(Surat $s): void
    {
        if (! $this->file) {
            return;
        }
        if ($s->file_path) {
            Storage::disk('local')->delete($s->file_path);
        }
        $s->file_path = $this->file->store('surat/'.$s->tahun, 'local');
        $s->file_name = $this->file->getClientOriginalName();
        $s->save();
        $this->reset('file');
    }

    public function canEdit(Surat $s): bool
    {
        return Auth::user()->is_admin || $s->user_id === Auth::id();
    }

    public function edit(int $id): void
    {
        $s = Surat::findOrFail($id);
        abort_unless($this->canEdit($s), 403);
        $this->resetValidation();
        $this->reset('file');
        $this->editId = $s->id;
        $this->form = [
            'tanggal' => $s->tanggal->toDateString(),
            'klasifikasi' => (string) $s->klasifikasi,
            'perihal' => $s->perihal, 'pihak' => (string) $s->pihak, 'tempat' => (string) $s->tempat,
            'pegawai' => $s->pegawai ?? [],
            'tanggal_mulai' => $s->tanggal_mulai?->toDateString() ?? '', 'tanggal_selesai' => $s->tanggal_selesai?->toDateString() ?? '',
            'keterangan' => (string) $s->keterangan, 'link' => (string) $s->link,
        ];
        $this->editTampil = true;
    }

    public function simpanEdit(): void
    {
        $s = Surat::findOrFail($this->editId);
        abort_unless($this->canEdit($s), 403);
        $j = Surat::jenis($s->jenis) ?? ['format' => '', 'isian' => ['perihal' => 'Perihal', 'pihak' => 'Pihak (opsional)']];
        $aturan = $this->aturan($j);
        unset($aturan['form.tanggal'], $aturan['form.klasifikasi']); // nomor tidak berubah setelah terbit
        $this->validate($aturan, [], $this->namaAtribut($j));
        $data = $this->dataForm($j);
        unset($data['klasifikasi']);
        $s->update($data);
        $this->simpanFile($s);
        $this->editTampil = false;
        $this->editId = null;
        if (Surat::jenis($this->tab)) {
            $this->siapkanForm();
        }
        $this->toast('Data nomor diperbarui.');
    }

    public function tutupEdit(): void
    {
        $this->editTampil = false;
        $this->editId = null;
        if (Surat::jenis($this->tab)) {
            $this->siapkanForm();
        }
    }

    public function delete(): void
    {
        $s = Surat::findOrFail($this->confirmDeleteId);
        abort_unless($this->canEdit($s), 403);
        $s->delete(); // soft delete: nomor tidak dipakai ulang
        $this->confirmDeleteId = null;
        $this->toast('Dihapus. Nomornya tidak akan dipakai ulang.');
    }

    // =====================================================================
    // Link surat (admin)
    // =====================================================================

    public function tambahLink(): void
    {
        abort_unless(Auth::user()->is_admin, 403);
        $this->linkId = null;
        $this->linkForm = ['judul' => '', 'url' => '', 'keterangan' => ''];
        $this->resetValidation();
        $this->linkTampil = true;
    }

    public function ubahLink(int $id): void
    {
        abort_unless(Auth::user()->is_admin, 403);
        $l = PortalLink::where('grup', 'sicatur')->findOrFail($id);
        $this->linkId = $l->id;
        $this->linkForm = ['judul' => $l->judul, 'url' => $l->url, 'keterangan' => (string) $l->keterangan];
        $this->resetValidation();
        $this->linkTampil = true;
    }

    public function simpanLink(): void
    {
        abort_unless(Auth::user()->is_admin, 403);
        $d = $this->validate([
            'linkForm.judul' => 'required|string|max:150',
            'linkForm.url' => 'required|url|max:1000',
            'linkForm.keterangan' => 'nullable|string|max:255',
        ], [], ['linkForm.judul' => 'judul', 'linkForm.url' => 'tautan'])['linkForm'];
        PortalLink::updateOrCreate(['id' => $this->linkId], $d + ['grup' => 'sicatur', 'urutan' => $this->linkId ? PortalLink::find($this->linkId)->urutan : (int) PortalLink::where('grup', 'sicatur')->max('urutan') + 1]);
        $this->linkTampil = false;
        $this->toast('Tautan disimpan.');
    }

    public function hapusLink(): void
    {
        abort_unless(Auth::user()->is_admin, 403);
        PortalLink::where('grup', 'sicatur')->whereKey($this->hapusLinkId)->delete();
        $this->hapusLinkId = null;
        $this->toast('Tautan dihapus.');
    }

    // =====================================================================
    // Pengaturan (admin)
    // =====================================================================

    private function siapkanPengaturan(): void
    {
        $th = (int) now()->year;
        foreach (Surat::jenisList() as $kode => $j) {
            $this->format[$kode] = $j['format'];
            $this->mulai[$kode] = Surat::nomorAwal($kode, $th) ?: '';
        }
        $this->pedomanUrl = (string) Settings::get('sicatur_pedoman_url', '');
    }

    public function simpanPengaturan(): void
    {
        abort_unless(Auth::user()->is_admin, 403);
        $this->validate([
            'format.*' => ['required', 'string', 'max:120', 'regex:/\{nomor\}/'],
            'mulai.*' => 'nullable|integer|min:0|max:99999',
            'pedomanUrl' => 'nullable|url|max:1000',
            'pedomanFile' => 'nullable|file|mimes:pdf|max:30720',
        ], ['format.*.regex' => 'Format wajib memuat {nomor}.'], ['format.*' => 'format', 'mulai.*' => 'nomor terakhir', 'pedomanUrl' => 'tautan buku pedoman']);

        $th = (int) now()->year;
        foreach ($this->format as $kode => $f) {
            $bawaan = config("sicatur.jenis.$kode.format");
            Settings::set('sicatur_format_'.$kode, trim($f) === $bawaan ? null : trim($f));
            Settings::set("sicatur_mulai_{$kode}_{$th}", $this->mulai[$kode] === '' || $this->mulai[$kode] === null ? null : (int) $this->mulai[$kode]);
        }
        Settings::set('sicatur_pedoman_url', $this->pedomanUrl ?: null);
        if ($this->pedomanFile) {
            if ($lama = Settings::get('sicatur_pedoman_file')) {
                Storage::disk('local')->delete($lama);
            }
            Settings::set('sicatur_pedoman_file', $this->pedomanFile->storeAs('sicatur', 'buku-pedoman-sicatur.pdf', 'local'));
            $this->reset('pedomanFile');
        }
        $this->toast('Pengaturan SICATUR disimpan.');
    }

    public function hapusPedomanFile(): void
    {
        abort_unless(Auth::user()->is_admin, 403);
        if ($lama = Settings::get('sicatur_pedoman_file')) {
            Storage::disk('local')->delete($lama);
        }
        Settings::set('sicatur_pedoman_file', null);
        $this->toast('File buku pedoman dihapus.');
    }

    /**
     * Impor riwayat nomor dari spreadsheet SICATUR lama.
     * Kolom: Jenis | Nomor Urut | Nomor Surat | Tanggal | Klasifikasi | Perihal | Pihak | Pembuat
     */
    public function imporRiwayat(): void
    {
        abort_unless(Auth::user()->is_admin, 403);
        $this->validate(['imporFile' => 'required|file|extensions:xlsx,xls,csv|max:10240'], [], ['imporFile' => 'file Excel']);
        $rows = IOFactory::load($this->imporFile->getRealPath())->getActiveSheet()->toArray(null, true, false, false);
        $norm = fn ($v) => preg_replace('/[^a-z]/', '', strtolower((string) $v));
        $alias = ['jenis' => ['jenis', 'jenissurat'], 'urut' => ['nomorurut', 'urut', 'no', 'nourut'], 'nomor' => ['nomorsurat', 'nomor'],
            'tanggal' => ['tanggal', 'tanggalsurat', 'tgl'], 'klasifikasi' => ['klasifikasi', 'kodeklasifikasi'], 'perihal' => ['perihal', 'hal', 'tentang', 'uraian'],
            'pihak' => ['pihak', 'tujuan', 'kepada', 'pihakkedua'], 'pembuat' => ['pembuat', 'nama', 'dibuatoleh', 'pengguna']];
        $kol = null;
        $mulai = 0;
        foreach (array_slice($rows, 0, 10, true) as $i => $r) {
            $k = [];
            foreach ($r as $idx => $judul) {
                foreach ($alias as $f => $a) {
                    if (! isset($k[$f]) && in_array($norm($judul), $a, true)) {
                        $k[$f] = $idx;
                    }
                }
            }
            if (isset($k['jenis'], $k['nomor'], $k['tanggal'])) {
                [$kol, $mulai] = [$k, $i + 1];
                break;
            }
        }
        if (! $kol) {
            $this->addError('imporFile', 'Kolom Jenis, Nomor Surat, dan Tanggal tidak ditemukan. Gunakan template.');

            return;
        }

        $peta = [];
        foreach (Surat::jenisList() as $kode => $j) {
            foreach ([$kode, $j['singkat'], $j['label']] as $n) {
                $peta[$norm($n)] = $kode;
            }
        }
        $peta[$norm('Surat Tugas')] = 'st';
        $peta[$norm('Surat Perintah')] = 'st';

        $n = 0;
        $gagal = [];
        DB::transaction(function () use ($rows, $mulai, $kol, $peta, $norm, &$n, &$gagal) {
            foreach (array_slice($rows, $mulai, null, true) as $i => $r) {
                $nomor = trim((string) ($r[$kol['nomor']] ?? ''));
                if ($nomor === '') {
                    continue;
                }
                $kode = $peta[$norm($r[$kol['jenis']] ?? '')] ?? null;
                $tgl = $r[$kol['tanggal']] ?? null;
                try {
                    $tgl = is_numeric($tgl) ? Carbon::instance(ExcelDate::excelToDateTimeObject((float) $tgl)) : Carbon::parse(str_replace('/', '-', (string) $tgl));
                } catch (\Throwable) {
                    $tgl = null;
                }
                if (! $kode || ! $tgl) {
                    $gagal[] = $i + 1;

                    continue;
                }
                $urut = isset($kol['urut']) && is_numeric($r[$kol['urut']] ?? null) ? (int) $r[$kol['urut']] : (preg_match('/(\d+)/', $nomor, $m) ? (int) $m[1] : 0);
                if ($urut <= 0) {
                    $gagal[] = $i + 1;

                    continue;
                }
                Surat::withTrashed()->updateOrCreate(
                    ['jenis' => $kode, 'tahun' => (int) $tgl->format('Y'), 'nomor_urut' => $urut],
                    [
                        'nomor_surat' => $nomor, 'tanggal' => $tgl->toDateString(), 'sifat' => 'B',
                        'klasifikasi' => isset($kol['klasifikasi']) ? (trim((string) $r[$kol['klasifikasi']]) ?: null) : null,
                        'perihal' => isset($kol['perihal']) ? (trim((string) $r[$kol['perihal']]) ?: '-') : '-',
                        'pihak' => isset($kol['pihak']) ? (trim((string) $r[$kol['pihak']]) ?: null) : null,
                        'pembuat' => isset($kol['pembuat']) ? (trim((string) $r[$kol['pembuat']]) ?: 'Impor') : 'Impor',
                        'keterangan' => 'Diimpor dari SICATUR lama',
                        'deleted_at' => null,
                    ]
                );
                $n++;
            }
        });
        $this->reset('imporFile');
        $this->toast("{$n} nomor diimpor.".($gagal ? ' Baris dilewati: '.implode(', ', array_slice($gagal, 0, 10)).(count($gagal) > 10 ? '…' : '') : ''), $gagal ? 'info' : 'success');
    }

    // =====================================================================

    public function render()
    {
        $jenisList = Surat::jenisList();
        $th = (int) now()->year;
        $data = ['jenisList' => $jenisList, 'srikandi' => config('portal.sicatur.srikandi'), 'jumlahLink' => PortalLink::where('grup', 'sicatur')->count()];

        $pedomanFile = Settings::get('sicatur_pedoman_file');
        $data['pedoman'] = $pedomanFile ? route('portal.sicatur.pedoman') : (Settings::get('sicatur_pedoman_url') ?: null);
        $data['pedomanFile'] = $pedomanFile;

        if ($this->tab === 'beranda') {
            $stat = Surat::query()->where('tahun', $th)->groupBy('jenis')->selectRaw('jenis, COUNT(*) as n')->pluck('n', 'jenis');
            $terakhir = [];
            foreach ($jenisList as $kode => $j) {
                $terakhir[$kode] = Surat::where('jenis', $kode)->where('tahun', $th)->orderByDesc('nomor_urut')->first();
            }
            $data += ['stat' => $stat, 'terakhir' => $terakhir, 'tahunIni' => $th, 'sayaTerbaru' => Surat::where('user_id', Auth::id())->latest()->limit(5)->get()];
        } elseif (isset($jenisList[$this->tab])) {
            $data += [
                'j' => $jenisList[$this->tab],
                'daftar' => Surat::with('user')->where('jenis', $this->tab)->where('tahun', $th)->orderByDesc('nomor_urut')->limit(10)->get(),
                'jumlah' => Surat::where('jenis', $this->tab)->where('tahun', $th)->count(),
                'pegawaiList' => User::query()->pegawai()->orderBy('name')->get(['id', 'name', 'username', 'nip']),
                'klasifikasiList' => collect(config('sicatur.klasifikasi'))->merge(Surat::query()->whereNotNull('klasifikasi')->distinct()->pluck('klasifikasi'))->unique()->sort()->values(),
            ];
        } elseif ($this->tab === 'riwayat') {
            $q = Surat::query()->with('user')
                ->where('tahun', (int) $this->tahun)
                ->when($this->fJenis, fn ($q) => $q->where('jenis', $this->fJenis))
                ->when($this->bulan, fn ($q) => $q->whereMonth('tanggal', (int) $this->bulan))
                ->when($this->milikSaya, fn ($q) => $q->where('user_id', Auth::id()))
                ->when($this->search, fn ($q) => $q->where(fn ($w) => $w->where('nomor_surat', 'like', "%{$this->search}%")
                    ->orWhere('perihal', 'like', "%{$this->search}%")->orWhere('pihak', 'like', "%{$this->search}%")
                    ->orWhere('pegawai', 'like', "%{$this->search}%")->orWhere('pembuat', 'like', "%{$this->search}%")))
                ->orderByDesc('tanggal')->orderByDesc('id');
            $data += [
                'surats' => $q->paginate(25),
                'tahunList' => Surat::query()->select('tahun')->distinct()->pluck('tahun')->push($th)->unique()->sortDesc()->values(),
                'jenisRiwayat' => collect($jenisList)->map(fn ($j) => $j['singkat'])->all() + Surat::query()->whereIn('jenis', array_keys(config('sicatur.lama')))->distinct()->pluck('jenis')->mapWithKeys(fn ($k) => [$k => config("sicatur.lama.$k")])->all(),
            ];
        } elseif ($this->tab === 'link') {
            $data['links'] = PortalLink::where('grup', 'sicatur')->orderBy('urutan')->orderBy('id')->get();
        }

        return view('livewire.portal.sicatur', $data);
    }
}
