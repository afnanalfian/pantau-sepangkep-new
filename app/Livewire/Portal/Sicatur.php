<?php

namespace App\Livewire\Portal;

use App\Livewire\Concerns\Toasts;
use App\Models\Surat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Title('SICATUR · Sistem Pencatatan Surat')]
#[Layout('layouts.portal')]
class Sicatur extends Component
{
    use Toasts, WithFileUploads, WithPagination;

    #[Url]
    public string $jenis = 'keluar'; // keluar | masuk

    #[Url(except: '')]
    public string $search = '';

    #[Url]
    public string $tahun = '';

    #[Url(except: '')]
    public string $bulan = '';

    #[Url(except: false)]
    public bool $milikSaya = false;

    public bool $showForm = false;

    public ?int $editId = null;

    public array $form = [];

    public $file;

    public ?array $baru = null; // nomor yang baru dibuat (ditampilkan besar)

    public ?int $confirmDeleteId = null;

    public function mount(): void
    {
        $this->tahun = $this->tahun ?: now()->format('Y');
        $this->jenis = in_array($this->jenis, ['keluar', 'masuk']) ? $this->jenis : 'keluar';
    }

    public function updating($name): void
    {
        if (in_array($name, ['jenis', 'search', 'tahun', 'bulan', 'milikSaya'])) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function preview(): string
    {
        $tgl = ! empty($this->form['tanggal']) ? Carbon::parse($this->form['tanggal']) : now();

        return Surat::buatNomor($this->form['jenis'] ?? $this->jenis, $tgl, $this->form['sifat'] ?? 'B', $this->form['klasifikasi'] ?? null)[2];
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->reset('editId', 'file', 'baru');
        $this->form = [
            'jenis' => $this->jenis, 'tanggal' => now()->toDateString(), 'sifat' => 'B', 'klasifikasi' => '',
            'perihal' => '', 'pihak' => '', 'nomor_asal' => '', 'keterangan' => '', 'link' => '',
        ];
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $s = Surat::findOrFail($id);
        abort_unless($this->canEdit($s), 403);
        $this->resetValidation();
        $this->reset('file', 'baru');
        $this->editId = $s->id;
        $this->form = $s->only(['jenis', 'sifat', 'klasifikasi', 'perihal', 'pihak', 'nomor_asal', 'keterangan', 'link']) + ['tanggal' => $s->tanggal->toDateString()];
        $this->showForm = true;
    }

    public function canEdit(Surat $s): bool
    {
        return Auth::user()->is_admin || $s->user_id === Auth::id();
    }

    public function save(): void
    {
        $data = $this->validate([
            'form.jenis' => 'required|in:keluar,masuk',
            'form.tanggal' => 'required|date',
            'form.sifat' => 'nullable|in:B,P,T,R',
            'form.klasifikasi' => 'nullable|string|max:30',
            'form.perihal' => 'required|string|max:255',
            'form.pihak' => 'required|string|max:255',
            'form.nomor_asal' => 'nullable|string|max:120',
            'form.keterangan' => 'nullable|string|max:2000',
            'form.link' => 'nullable|url|max:500',
            'file' => 'nullable|file|max:20480',
        ], [], [
            'form.perihal' => 'perihal', 'form.pihak' => $this->form['jenis'] === 'keluar' ? 'tujuan' : 'asal surat',
            'form.tanggal' => 'tanggal surat', 'form.link' => 'link', 'file' => 'file surat',
        ])['form'];
        $data['klasifikasi'] = $data['klasifikasi'] ? strtoupper(trim($data['klasifikasi'])) : null;

        if ($this->editId) {
            $s = Surat::findOrFail($this->editId);
            abort_unless($this->canEdit($s), 403);
            unset($data['jenis'], $data['tanggal']); // nomor tidak berubah setelah diterbitkan
            $s->fill($data);
        } else {
            // Nomor urut unik per jenis & tahun; bila dua orang menyimpan bersamaan, coba lagi.
            $u = Auth::user();
            for ($try = 0; ; $try++) {
                try {
                    [$tahun, $urut, $nomor] = Surat::buatNomor($data['jenis'], Carbon::parse($data['tanggal']), $data['sifat'] ?? 'B', $data['klasifikasi']);
                    $s = Surat::create($data + [
                        'tahun' => $tahun, 'nomor_urut' => $urut, 'nomor_surat' => $nomor,
                        'user_id' => $u->id, 'pembuat' => $u->name ?: $u->username,
                    ]);
                    break;
                } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                    if ($try >= 4) {
                        throw $e;
                    }
                }
            }
        }

        if ($this->file) {
            if ($s->file_path) {
                Storage::disk('local')->delete($s->file_path);
            }
            $s->file_path = $this->file->store('surat/'.($s->tahun ?? now()->year), 'local');
            $s->file_name = $this->file->getClientOriginalName();
        }
        $s->save();

        $this->showForm = false;
        if (! $this->editId) {
            $this->baru = ['nomor' => $s->nomor_surat, 'perihal' => $s->perihal, 'jenis' => $s->jenis];
        } else {
            $this->toast('Data surat diperbarui.');
        }
        $this->jenis = $s->jenis;
    }

    public function delete(): void
    {
        $s = Surat::findOrFail($this->confirmDeleteId);
        abort_unless($this->canEdit($s), 403);
        $s->delete(); // soft delete: nomor tidak dipakai ulang
        $this->confirmDeleteId = null;
        $this->toast('Surat dihapus. Nomornya tidak akan dipakai ulang.');
    }

    public function render()
    {
        $q = Surat::query()->with('user')->where('jenis', $this->jenis)
            ->where('tahun', (int) $this->tahun)
            ->when($this->bulan, fn ($q) => $q->whereMonth('tanggal', (int) $this->bulan))
            ->when($this->milikSaya, fn ($q) => $q->where('user_id', Auth::id()))
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w->where('nomor_surat', 'like', "%{$this->search}%")
                ->orWhere('perihal', 'like', "%{$this->search}%")->orWhere('pihak', 'like', "%{$this->search}%")->orWhere('pembuat', 'like', "%{$this->search}%")))
            ->orderByDesc('nomor_urut');

        $tahunList = Surat::query()->select('tahun')->distinct()->pluck('tahun')->push((int) now()->format('Y'))->unique()->sortDesc()->values();

        return view('livewire.portal.sicatur', [
            'surats' => $q->paginate(20),
            'tahunList' => $tahunList,
            'counts' => [
                'keluar' => Surat::where('jenis', 'keluar')->where('tahun', (int) $this->tahun)->count(),
                'masuk' => Surat::where('jenis', 'masuk')->where('tahun', (int) $this->tahun)->count(),
            ],
            'srikandi' => config('portal.sicatur.srikandi'),
        ]);
    }
}
