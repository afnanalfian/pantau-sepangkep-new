<?php

namespace App\Livewire\Sibeban\Mitra;

use App\Livewire\Sibeban\Monitoring;
use App\Models\Sibeban\Mitra;
use App\Services\Sibeban\HonorExcelReader;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use PhpOffice\PhpSpreadsheet\IOFactory;

/** Database mitra. Semua pegawai dapat melihat; hanya admin yang dapat menambah/mengubah/menghapus/mengimpor. */
#[Layout('layouts.portal')]
#[Title('SIBEBAN · Database Mitra')]
class Index extends Component
{
    use WithFileUploads;
    use WithPagination;

    #[Url(except: '')]
    public string $cari = '';

    #[Url(except: '')]
    public string $kecamatan = '';

    #[Url(except: '')]
    public string $desa = '';

    public bool $formTampil = false;
    public ?int $editId = null;
    public array $form = [];

    public bool $imporTampil = false;
    public $file;

    public ?int $hapusId = null;

    private function admin(): void
    {
        abort_unless(Auth::user()->is_admin, 403, 'Hanya admin yang dapat mengubah database mitra.');
    }

    public function updated(string $p): void
    {
        if ($p === 'kecamatan') {
            $this->desa = '';
        }
        if (in_array($p, ['cari', 'kecamatan', 'desa'], true)) {
            $this->resetPage();
        }
    }

    public function tambah(): void
    {
        $this->admin();
        $this->reset('editId');
        $this->form = ['sobat_id' => '', 'nama' => '', 'kecamatan' => $this->kecamatan, 'desa' => $this->desa, 'posisi' => '', 'email' => ''];
        $this->resetValidation();
        $this->formTampil = true;
    }

    public function edit(int $id): void
    {
        $this->admin();
        $m = Mitra::findOrFail($id);
        $this->editId = $m->id;
        $this->form = array_map(fn ($v) => (string) $v, $m->only(['sobat_id', 'nama', 'kecamatan', 'desa', 'posisi', 'email']));
        $this->resetValidation();
        $this->formTampil = true;
    }

    public function simpan(): void
    {
        $this->admin();
        $data = $this->validate([
            'form.sobat_id' => ['required', 'string', 'max:30', Rule::unique('sibeban_mitras', 'sobat_id')->ignore($this->editId)],
            'form.nama' => 'required|string|max:255',
            'form.kecamatan' => 'nullable|string|max:100',
            'form.desa' => 'nullable|string|max:100',
            'form.posisi' => 'nullable|string|max:100',
            'form.email' => 'nullable|email|max:255',
        ], [], ['form.sobat_id' => 'Sobat ID', 'form.nama' => 'nama', 'form.desa' => 'desa/kelurahan'])['form'];
        $data = array_map(fn ($v) => is_string($v) && trim($v) === '' ? null : (is_string($v) ? trim($v) : $v), $data);

        Mitra::updateOrCreate(['id' => $this->editId], $data);
        $this->formTampil = false;
        session()->flash('sukses', 'Data mitra disimpan.');
    }

    public function hapus(): void
    {
        $this->admin();
        $m = Mitra::withCount('penugasans')->findOrFail($this->hapusId);
        $m->delete();
        $this->hapusId = null;
        session()->flash('sukses', "{$m->nama} dihapus".($m->penugasans_count ? " beserta {$m->penugasans_count} penugasannya." : '.'));
    }

    /** Impor/perbarui database mitra dari Excel: Sobat ID, Nama, Kecamatan, Desa/Kelurahan, Posisi, Email. */
    public function impor(): void
    {
        $this->admin();
        $this->validate(['file' => 'required|file|extensions:xlsx,xls,csv|max:10240'], [], ['file' => 'file Excel']);

        $rows = IOFactory::load($this->file->getRealPath())->getActiveSheet()->toArray(null, true, false, false);
        $norm = fn ($v) => preg_replace('/[^a-z]/', '', strtolower((string) $v));
        $alias = [
            'sobat_id' => ['sobatid', 'idsobat', 'id', 'sobat', 'idmitra'],
            'nama' => ['nama', 'namalengkap', 'namamitra'],
            'kecamatan' => ['kecamatan', 'kec', 'namakecamatan'],
            'desa' => ['desakelurahan', 'desa', 'kelurahan', 'desakel', 'namadesa'],
            'posisi' => ['posisi', 'jenismitra'],
            'email' => ['email', 'surel'],
        ];

        $kol = [];
        $mulai = null;
        foreach (array_slice($rows, 0, 10, true) as $i => $r) {
            $kol = [];
            foreach ($r as $idx => $judul) {
                foreach ($alias as $f => $a) {
                    if (! isset($kol[$f]) && in_array($norm($judul), $a, true)) {
                        $kol[$f] = $idx;
                    }
                }
            }
            if (isset($kol['sobat_id'], $kol['nama'])) {
                $mulai = $i + 1;
                break;
            }
        }
        if ($mulai === null) {
            $this->addError('file', 'Kolom "Sobat ID" dan "Nama" tidak ditemukan pada 10 baris pertama. Gunakan template.');

            return;
        }

        $stat = ['baru' => 0, 'ubah' => 0];
        DB::transaction(function () use ($rows, $mulai, $kol, &$stat) {
            foreach (array_slice($rows, $mulai) as $r) {
                $id = HonorExcelReader::sobat($r[$kol['sobat_id']] ?? null);
                $nama = trim((string) ($r[$kol['nama']] ?? ''));
                if ($id === '' || $nama === '' || $nama === 'Contoh Nama Mitra') {
                    continue;
                }
                $data = ['nama' => $nama];
                foreach (['kecamatan', 'desa', 'posisi', 'email'] as $f) {
                    if (isset($kol[$f]) && trim((string) $r[$kol[$f]]) !== '') {
                        $data[$f] = trim((string) $r[$kol[$f]]);
                    }
                }
                $m = Mitra::updateOrCreate(['sobat_id' => $id], $data);
                $stat[$m->wasRecentlyCreated ? 'baru' : 'ubah']++;
            }
        });

        $this->reset('file');
        $this->imporTampil = false;
        session()->flash('sukses', "Impor selesai: {$stat['baru']} mitra baru, {$stat['ubah']} diperbarui.");
    }

    public function render()
    {
        $q = Mitra::query()
            ->withCount('penugasans')
            ->when($this->cari !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('nama', 'like', "%{$this->cari}%")
                ->orWhere('sobat_id', 'like', "%{$this->cari}%")
                ->orWhere('posisi', 'like', "%{$this->cari}%")))
            ->when($this->kecamatan !== '', fn ($q) => $q->where('kecamatan', $this->kecamatan))
            ->when($this->desa !== '', fn ($q) => $q->where('desa', $this->desa));

        return view('livewire.sibeban.mitra.index', [
            'mitras' => (clone $q)->orderBy('nama')->paginate(25),
            'opsi' => Monitoring::opsiWilayah($this->kecamatan),
            'tanpaWilayah' => Mitra::query()->where(fn ($w) => $w->whereNull('kecamatan')->orWhere('kecamatan', ''))->count(),
            'admin' => Auth::user()->is_admin,
        ]);
    }
}
