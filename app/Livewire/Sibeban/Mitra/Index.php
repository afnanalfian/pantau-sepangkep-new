<?php

namespace App\Livewire\Sibeban\Mitra;

use App\Models\Sibeban\Mitra;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use PhpOffice\PhpSpreadsheet\IOFactory;

#[Layout('layouts.portal')]
#[Title('Master Mitra')]
class Index extends Component
{
    use WithFileUploads;
    use WithPagination;

    #[Url(except: '')]
    public string $cari = '';

    public bool $formTampil = false;
    public ?int $editId = null;
    public string $sobat_id = '';
    public string $nama = '';
    public string $posisi = '';
    public string $email = '';

    public $file;

    public function updatedCari(): void
    {
        $this->resetPage();
    }

    public function tambah(): void
    {
        $this->reset(['editId', 'sobat_id', 'nama', 'posisi', 'email']);
        $this->resetValidation();
        $this->formTampil = true;
    }

    public function edit(int $id): void
    {
        $m = Mitra::findOrFail($id);
        $this->editId = $m->id;
        $this->sobat_id = $m->sobat_id;
        $this->nama = $m->nama;
        $this->posisi = (string) $m->posisi;
        $this->email = (string) $m->email;
        $this->resetValidation();
        $this->formTampil = true;
    }

    public function simpan(): void
    {
        $data = $this->validate([
            'sobat_id' => ['required', 'string', 'max:30', Rule::unique('sibeban_mitras', 'sobat_id')->ignore($this->editId)],
            'nama' => 'required|string|max:255',
            'posisi' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
        ], [], ['sobat_id' => 'ID SOBAT']);

        Mitra::updateOrCreate(['id' => $this->editId], $data);
        $this->formTampil = false;
        session()->flash('sukses', 'Data mitra disimpan.');
    }

    public function hapus(int $id): void
    {
        $m = Mitra::withCount('penugasans')->findOrFail($id);
        $m->delete();
        session()->flash('sukses', "{$m->nama} dihapus".($m->penugasans_count ? " beserta {$m->penugasans_count} penugasannya." : '.'));
    }

    /** Impor/perbarui master mitra dari Excel: sobat_id, nama, posisi, email. */
    public function impor(): void
    {
        $this->validate(['file' => 'required|file|extensions:xlsx,xls,csv|max:10240'], [], ['file' => 'file Excel']);

        $rows = IOFactory::load($this->file->getRealPath())->getActiveSheet()->toArray(null, true, false, false);
        $norm = fn ($v) => preg_replace('/[^a-z]/', '', strtolower((string) $v));
        $alias = [
            'sobat_id' => ['sobatid', 'idsobat', 'id', 'sobat'],
            'nama' => ['nama', 'namalengkap', 'namamitra'],
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
            $this->addError('file', 'Kolom sobat_id dan nama tidak ditemukan pada 10 baris pertama.');

            return;
        }

        $n = 0;
        DB::transaction(function () use ($rows, $mulai, $kol, &$n) {
            foreach (array_slice($rows, $mulai) as $r) {
                $id = $r[$kol['sobat_id']] ?? null;
                $id = is_float($id) || is_int($id) ? number_format((float) $id, 0, '', '') : trim(ltrim((string) $id, "'"));
                $nama = trim((string) ($r[$kol['nama']] ?? ''));
                if ($id === '' || $id === '0' || $nama === '') {
                    continue;
                }
                $data = ['nama' => $nama];
                foreach (['posisi', 'email'] as $f) {
                    if (isset($kol[$f]) && trim((string) $r[$kol[$f]]) !== '') {
                        $data[$f] = trim((string) $r[$kol[$f]]);
                    }
                }
                Mitra::updateOrCreate(['sobat_id' => $id], $data);
                $n++;
            }
        });

        $this->reset('file');
        session()->flash('sukses', "{$n} mitra diimpor/diperbarui.");
    }

    public function render()
    {
        return view('livewire.sibeban.mitra.index', [
            'mitras' => Mitra::query()
                ->withCount('penugasans')
                ->when($this->cari !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->where('nama', 'like', "%{$this->cari}%")
                    ->orWhere('sobat_id', 'like', "%{$this->cari}%")
                    ->orWhere('posisi', 'like', "%{$this->cari}%")))
                ->orderBy('nama')
                ->paginate(25),
        ]);
    }
}
