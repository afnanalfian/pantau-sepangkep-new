<?php

namespace App\Livewire\Portal;

use App\Livewire\Concerns\Toasts;
use App\Models\SicaturKlasifikasi;
use App\Models\Surat;
use App\Services\SicaturImporter;
use App\Services\SicaturService;
use App\Support\Settings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * SICATUR — Sistem Pencatatan Surat. Tata letak, isian, dan aturan mengikuti aplikasi Apps Script lama:
 * Beranda · tab per jenis · Riwayat · Link Surat (SK).
 */
#[Title('SICATUR')]
#[Layout('layouts.portal')]
class Sicatur extends Component
{
    use Toasts, WithFileUploads, WithPagination;

    #[Url(except: 'beranda')]
    public string $tab = 'beranda';

    /** Tautan pengunggah: ?tab=link&isi=<id> membuka langsung jendela link surat */
    #[Url(except: null)]
    public ?int $isi = null;

    // ---- Form terbitkan ----
    public array $isian = [];       // [jenis][attr]
    public array $mundur = [];      // [jenis] bool
    public string $pembuat = '';    // dibawa ke semua tab
    public array $hasil = [];       // [jenis] nomor yang baru terbit

    // ---- Riwayat ----
    public string $rKata = '';
    public string $rJenis = '';
    public string $rDari = '';
    public string $rSampai = '';
    public array $rKriteria = ['kata' => '', 'jenis' => '', 'dari' => '', 'sampai' => ''];

    // ---- Link surat ----
    public string $lKata = '';
    public string $lStatus = 'menunggu';
    public string $lKataAktif = '';
    public ?int $lId = null;
    public string $linkBaru = '';

    // ---- Hapus ----
    public ?int $hId = null;
    public string $hAlasan = '';

    // ---- Pengaturan (admin) ----
    public $imporFile;
    public string $pedomanUrl = '';
    public $pedomanFile;
    public string $webhookSk = '';
    public string $webhookSkLink = '';
    public ?string $laporanImpor = null;
    public ?array $hasilUji = null;

    public function mount(SicaturService $svc): void
    {
        $this->pembuat = $this->namaAkun();
        foreach (config('sicatur.jenis') as $jenis => $c) {
            $this->kosongkanForm($jenis);
        }
        if (! $this->tabValid($this->tab)) {
            $this->tab = 'beranda';
        }
        if ($this->isi) {
            $this->tab = 'link';
            $this->bukaLink($this->isi);
        }
        if ($this->tab === 'pengaturan') {
            $this->siapkanPengaturan();
        }
    }

    /** Pembuat surat selalu akun yang sedang masuk. */
    private function namaAkun(): string
    {
        $u = Auth::user();

        return trim((string) ($u->name ?: $u->username));
    }

    private function tabValid(string $t): bool
    {
        return in_array($t, ['beranda', 'riwayat', 'link'], true) || Surat::cfg($t) || ($t === 'pengaturan' && Auth::user()->is_admin);
    }

    public function pilihTab(string $t): void
    {
        if (! $this->tabValid($t)) {
            return;
        }
        $this->tab = $t;
        if ($t === 'pengaturan') {
            $this->siapkanPengaturan();
        }
        $this->js('window.scrollTo(0, 0)');
    }

    private function kosongkanForm(string $jenis): void
    {
        $c = Surat::cfg($jenis);
        foreach ($c['form'] as $f) {
            if ($f['tipe'] !== 'pembuat') {
                $this->isian[$jenis][$f['attr']] = ($c['tanggal'] === $f['attr']) ? now()->toDateString() : '';
            }
        }
        $this->mundur[$jenis] = false;
    }

    /** Isian yang belum lengkap → teks petunjuk dan tombol nonaktif (seperti perbarui() di Skrip.html). */
    public function kurang(string $jenis): array
    {
        $c = Surat::cfg($jenis);
        $hariIni = now()->toDateString();
        $adaKlas = SicaturKlasifikasi::query()->exists();
        $k = [];
        foreach ($c['form'] as $f) {
            $v = $f['tipe'] === 'pembuat' ? $this->pembuat : trim((string) ($this->isian[$jenis][$f['attr']] ?? ''));
            if ($v === '') {
                $k[] = $f['kolom'];
            } elseif ($f['tipe'] === 'tautan' && ! preg_match(SicaturService::POLA_TAUTAN, $v)) {
                $k[] = $f['kolom'].' (harus berupa tautan)';
            } elseif ($f['tipe'] === 'klasifikasi' && $adaKlas && ! SicaturKlasifikasi::where('kode', $v)->exists()) {
                $k[] = $f['kolom'].' (pilih dari daftar)';
            } elseif ($c['tanggal'] === $f['attr']) {
                if ($v > $hariIni) {
                    $k[] = $f['kolom'].' tidak boleh setelah hari ini';
                } elseif ($v < $hariIni && empty($this->mundur[$jenis])) {
                    $k[] = 'centang Tanggal mundur';
                }
            }
        }

        return $k;
    }

    public function updatedIsian($value, $key): void
    {
        [$jenis] = explode('.', $key);
        unset($this->hasil[$jenis]);   // nomor yang baru terbit disembunyikan saat mulai mengisi surat berikutnya
        $this->resetErrorBag('isian.'.$key);
    }

    public function terbitkan(string $jenis, SicaturService $svc): void
    {
        $c = $svc->cfg($jenis);
        if ($this->kurang($jenis)) {
            return;
        }
        $this->pembuat = $this->namaAkun();
        $isian = ['pembuat' => $this->pembuat] + $this->isian[$jenis];
        try {
            $h = $svc->terbitkan($jenis, $isian, (bool) ($this->mundur[$jenis] ?? false), Auth::user());
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->addError('terbit.'.$jenis, $e->getMessage());

            return;
        }
        $s = $h['surat'];
        $catatan = ($h['sisipan'] ? 'Nomor sisipan, induk '.$h['induk'].'. ' : '').'Tercatat sebagai No Urut '.$s->no_urut.'.';
        if (! $h['urutanTerjaga']) {
            $catatan .= ' Belum ada nomor sebelum tanggal ini, jadi urutan tanggal tidak bisa dijaga untuk nomor ini.';
        }
        if ($jenis === config('sicatur.lengkapi.jenis')) {
            $catatan .= ' Status: '.config('sicatur.lengkapi.proses').'. Kirim tautan pengunggah ke rekan yang akan mengisi link surat.';
        }
        $this->hasil[$jenis] = [
            'id' => $s->id,
            'nomor' => $s->nomor_surat,
            'catatan' => $catatan,
            'tautan' => $jenis === config('sicatur.lengkapi.jenis') ? route('portal.sicatur', ['tab' => 'link', 'isi' => $s->id]) : null,
        ];
        $this->kosongkanForm($jenis);
    }

    // =====================================================================
    // Hapus nomor (nomor lain tidak berubah, nomor yang dihapus tidak dipakai lagi)
    // =====================================================================

    public function bukaHapus(int $id, SicaturService $svc): void
    {
        $s = Surat::findOrFail($id);
        if (! $svc->bolehHapus($s, Auth::user())) {
            $this->toast('Hanya pembuat nomor atau admin yang bisa menghapus nomor ini.', 'error');

            return;
        }
        $this->hId = $s->id;
        $this->hAlasan = '';
        $this->resetErrorBag('hAlasan');
    }

    public function tutupHapus(): void
    {
        $this->reset(['hId', 'hAlasan']);
        $this->resetErrorBag('hAlasan');
    }

    public function hapus(SicaturService $svc): void
    {
        $s = Surat::findOrFail($this->hId);
        $nomor = $s->nomorTampil();
        $svc->hapus($s, $this->hAlasan, Auth::user());
        foreach ($this->hasil as $j => $x) {
            if ($x['id'] === $s->id) {
                unset($this->hasil[$j]);
            }
        }
        $this->tutupHapus();
        $this->toast('Nomor '.$nomor.' dihapus. Nomor lain tidak berubah.');
    }

    // =====================================================================
    // Riwayat
    // =====================================================================

    public function cari(): void
    {
        if ($this->rDari && $this->rSampai && $this->rDari > $this->rSampai) {
            $this->addError('rDari', 'Tanggal "Dari" tidak boleh setelah "Sampai".');

            return;
        }
        $this->rKriteria = ['kata' => trim($this->rKata), 'jenis' => $this->rJenis, 'dari' => $this->rDari, 'sampai' => $this->rSampai];
        $this->resetPage('rh');
    }

    public function updatedRJenis(): void
    {
        $this->cari();
    }

    public function aturUlang(): void
    {
        $this->reset(['rKata', 'rJenis', 'rDari', 'rSampai']);
        $this->cari();
    }

    private function cariKata($q, string $kata): void
    {
        foreach (preg_split('/\s+/', $kata) as $w) {
            $q->where(fn ($x) => $x->where('nomor_surat', 'like', "%{$w}%")->orWhere('no_urut', 'like', "%{$w}%")
                ->orWhere('perihal', 'like', "%{$w}%")->orWhere('tujuan', 'like', "%{$w}%")
                ->orWhere('pembuat', 'like', "%{$w}%")->orWhere('klasifikasi', 'like', "%{$w}%")
                ->orWhere('tim_teknis', 'like', "%{$w}%")
                ->orWhere('nama_petugas', 'like', "%{$w}%")->orWhere('honor', 'like', "%{$w}%")
                ->orWhere('pihak', 'like', "%{$w}%"));
        }
    }

    // =====================================================================
    // Link surat (SK)
    // =====================================================================

    public function cariLink(): void
    {
        $this->lKataAktif = trim($this->lKata);
        $this->resetPage('lh');
    }

    public function updatedLStatus(): void
    {
        $this->cariLink();
    }

    public function bukaLink(int $id): void
    {
        $s = Surat::where('jenis', config('sicatur.lengkapi.jenis'))->find($id);
        if (! $s) {
            $this->toast('Nomor SK tidak ditemukan.', 'error');
            $this->isi = null;

            return;
        }
        $this->lId = $s->id;
        $this->linkBaru = '';
        $this->resetErrorBag('linkBaru');
    }

    public function tutupLink(): void
    {
        $this->reset(['lId', 'linkBaru', 'isi']);
    }

    public function simpanLink(SicaturService $svc): void
    {
        $s = Surat::findOrFail($this->lId);
        $ganti = (bool) $s->link_surat;
        $svc->simpanLink($s, $this->linkBaru, Auth::user());
        $this->tutupLink();
        $this->toast(($ganti ? 'Link surat diganti' : 'Link surat tersimpan').'. Status: '.config('sicatur.lengkapi.selesai').'.');
    }

    // =====================================================================
    // Pengaturan (admin)
    // =====================================================================

    private function siapkanPengaturan(): void
    {
        $this->pedomanUrl = (string) Settings::get('sicatur_pedoman_url', config('sicatur.url_pedoman'));
        $this->webhookSk = (string) Settings::get('sicatur_webhook_sk', '');
        $this->webhookSkLink = (string) Settings::get('sicatur_webhook_sk_link', '');
    }

    public function simpanPengaturan(): void
    {
        abort_unless(Auth::user()->is_admin, 403);
        $this->validate([
            'pedomanUrl' => 'nullable|url|max:1000',
            'pedomanFile' => 'nullable|file|mimes:pdf|max:30720',
            'webhookSk' => 'nullable|url|max:1000',
            'webhookSkLink' => 'nullable|url|max:1000',
        ], [], ['pedomanUrl' => 'tautan buku pedoman', 'webhookSk' => 'webhook SK baru', 'webhookSkLink' => 'webhook link SK']);
        Settings::set('sicatur_pedoman_url', $this->pedomanUrl ?: null);
        Settings::set('sicatur_webhook_sk', $this->webhookSk ?: null);
        Settings::set('sicatur_webhook_sk_link', $this->webhookSkLink ?: null);
        if ($this->pedomanFile) {
            if ($lama = Settings::get('sicatur_pedoman_file')) {
                Storage::disk('local')->delete($lama);
            }
            Settings::set('sicatur_pedoman_file', $this->pedomanFile->storeAs('sicatur', 'buku-pedoman-sicatur.pdf', 'local'));
            $this->reset('pedomanFile');
        }
        $this->toast('Pengaturan SICATUR disimpan.');
    }

    /** Kirim data contoh ke webhook (seperti ujiKirimWebhookSK di Apps Script). */
    public function ujiWebhook(string $jenis, SicaturService $svc): void
    {
        abort_unless(Auth::user()->is_admin, 403);
        $url = trim($jenis === 'link' ? $this->webhookSkLink : $this->webhookSk);
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            $this->hasilUji = ['jenis' => $jenis, 'terkirim' => false, 'pesan' => 'Isi URL webhook yang valid dulu.'];

            return;
        }
        $this->hasilUji = ['jenis' => $jenis] + $svc->uji($jenis, $url);
    }

    public function hapusPedomanFile(): void
    {
        abort_unless(Auth::user()->is_admin, 403);
        if ($lama = Settings::get('sicatur_pedoman_file')) {
            Storage::disk('local')->delete($lama);
        }
        Settings::set('sicatur_pedoman_file', null);
        $this->toast('File buku pedoman dihapus. Tombol Buku Pedoman kembali memakai tautan.');
    }

    public function imporSpreadsheet(SicaturImporter $importer): void
    {
        abort_unless(Auth::user()->is_admin, 403);
        $this->validate(['imporFile' => 'required|file|extensions:xlsx|max:30720'], [], ['imporFile' => 'file spreadsheet']);
        @set_time_limit(300);
        $h = $importer->impor($this->imporFile->getRealPath());
        $this->reset('imporFile');
        $rinci = collect($h['nomor'])->map(fn ($n, $l) => "{$l} {$n}")->implode(', ');
        $lewat = array_sum($h['dilewati']);
        $this->laporanImpor = "Impor selesai: {$h['klasifikasi']} kode klasifikasi; nomor: {$rinci}."
            .($lewat ? " {$lewat} baris dilewati (contoh/No Urut 0/tanpa tanggal)." : '')
            .($h['noWa'] ? " Nomor WA {$h['noWa']} pegawai diisi dari sheet Pegawai." : '')
            .($h['sheetHilang'] ? ' Sheet tidak ditemukan: '.implode(', ', $h['sheetHilang']).'.' : '');
        $this->toast('Impor selesai.');
    }

    // =====================================================================

    public function render(SicaturService $svc)
    {
        $jenisList = config('sicatur.jenis');
        $lk = config('sicatur.lengkapi');
        $data = [
            'jenisList' => $jenisList,
            'hariIni' => now()->toDateString(),
            'menunggu' => Surat::where('jenis', $lk['jenis'])->where('status', $lk['proses'])->count(),
            'pedoman' => Settings::get('sicatur_pedoman_file') ? route('portal.sicatur.pedoman') : (Settings::get('sicatur_pedoman_url', config('sicatur.url_pedoman')) ?: null),
            'adaKlasifikasi' => SicaturKlasifikasi::query()->exists(),
            // Versi daftar klasifikasi: berubah setiap kali diimpor, sehingga browser mengambil ulang
            'urlKlasifikasi' => route('portal.sicatur.klasifikasi', ['v' => SicaturKlasifikasi::count().'-'.optional(SicaturKlasifikasi::max('updated_at'), fn ($t) => strtotime($t))]),
        ];

        if ($this->tab === 'beranda') {
            $th = now()->year;
            $kartu = [];
            foreach ($jenisList as $j => $c) {
                $q = Surat::where('jenis', $j)->where('tahun', $th);
                $kartu[$j] = ['total' => (clone $q)->count(), 'terakhir' => (clone $q)->orderByDesc('tanggal')->orderByDesc('nomor_urut')->orderByDesc('sisip')->first()];
            }
            $data['kartu'] = $kartu;
        } elseif (isset($jenisList[$this->tab])) {
            $q = Surat::where('jenis', $this->tab)->where('tahun', now()->year);
            $data += [
                'c' => $jenisList[$this->tab],
                'kurang' => $this->kurang($this->tab),
                'pratinjau' => $svc->pratinjau($this->tab, ($this->isian[$this->tab] ?? []) + ['pembuat' => $this->pembuat], (bool) ($this->mundur[$this->tab] ?? false)),
                'terbaru' => (clone $q)->orderByDesc('tanggal')->orderByDesc('nomor_urut')->orderByDesc('sisip')->limit(8)->get(),
                'totalTab' => (clone $q)->count(),
            ];
        } elseif ($this->tab === 'riwayat') {
            $k = $this->rKriteria;
            $q = Surat::query()
                ->when($k['jenis'], fn ($q, $v) => $q->where('jenis', $v))
                ->when($k['dari'], fn ($q, $v) => $q->whereDate('tanggal', '>=', $v))
                ->when($k['sampai'], fn ($q, $v) => $q->whereDate('tanggal', '<=', $v))
                ->when($k['kata'], fn ($q, $v) => $this->cariKata($q, $v))
                ->orderByDesc('tanggal')->orderByDesc('nomor_urut')->orderByDesc('sisip');
            $data['hasilCari'] = $q->paginate(config('sicatur.per_halaman'), ['*'], 'rh');
        } elseif ($this->tab === 'link') {
            $base = Surat::where('jenis', $lk['jenis'])->when($this->lKataAktif, fn ($q, $v) => $this->cariKata($q, $v));
            $data['hitung'] = [
                'menunggu' => (clone $base)->where('status', $lk['proses'])->count(),
                'selesai' => (clone $base)->where('status', $lk['selesai'])->count(),
                'semua' => (clone $base)->count(),
            ];
            $data['daftarLink'] = (clone $base)
                ->when($this->lStatus === 'menunggu', fn ($q) => $q->where('status', $lk['proses']))
                ->when($this->lStatus === 'selesai', fn ($q) => $q->where('status', $lk['selesai']))
                ->orderByDesc('tanggal')->orderByDesc('nomor_urut')->orderByDesc('sisip')
                ->paginate(config('sicatur.per_halaman'), ['*'], 'lh');
        }

        $data['hSurat'] = $this->hId ? Surat::find($this->hId) : null;
        $data['bolehHapus'] = fn (Surat $s) => $svc->bolehHapus($s, Auth::user());
        $data['lSurat'] = $this->lId ? Surat::find($this->lId) : null;

        return view('livewire.portal.sicatur', $data);
    }
}
