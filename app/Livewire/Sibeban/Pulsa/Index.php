<?php

namespace App\Livewire\Sibeban\Pulsa;

use App\Models\Sibeban\Pulsa;
use App\Services\Sibeban\PulsaService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.portal')]
#[Title('SIBEBAN · Pulsa Petugas')]
class Index extends Component
{
    use PilihPetugas, WithPagination;

    #[Url(except: '')]
    public string $cari = '';

    #[Url(except: '')]
    public string $tahun = '';

    public bool $formTampil = false;

    public string $bulan_mulai = '';

    public string $bulan_selesai = '';

    public string $jumlah = '';

    public string $nama = '';

    public ?int $hapusId = null;

    protected function periodePilih(): array
    {
        return [$this->bulan_mulai, $this->bulan_selesai];
    }

    public function updated(string $p): void
    {
        if (in_array($p, ['cari', 'tahun'], true)) {
            $this->resetPage();
        }
        if (in_array($p, ['bulan_mulai', 'bulan_selesai'], true)) {
            if ($p === 'bulan_mulai' && PulsaService::bulanValid($this->bulan_mulai) && (! PulsaService::bulanValid($this->bulan_selesai) || $this->bulan_selesai < $this->bulan_mulai)) {
                $this->bulan_selesai = $this->bulan_mulai;
            }
            $this->pDipilih = []; // daftar petugas berubah mengikuti periode
            $this->resetErrorBag();
        }
    }

    public function tambah(): void
    {
        $this->reset(['bulan_mulai', 'bulan_selesai', 'jumlah', 'nama']);
        $this->resetPilih();
        $this->resetValidation();
        $this->bulan_mulai = $this->bulan_selesai = now()->format('Y-m');
        $this->formTampil = true;
    }

    public function simpan(PulsaService $svc)
    {
        $this->jumlah = preg_replace('/\D/', '', $this->jumlah);
        $data = $this->validate([
            'bulan_mulai' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'bulan_selesai' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/', function ($a, $v, $gagal) {
                if ((string) $v < $this->bulan_mulai) {
                    $gagal('Bulan selesai tidak boleh sebelum bulan mulai.');
                }
            }],
            'jumlah' => 'required|integer|min:1000|max:10000000',
            'nama' => 'nullable|string|max:150',
            'pDipilih' => 'required|array|min:1',
        ], [
            'pDipilih.required' => 'Centang minimal satu petugas.',
        ], ['bulan_mulai' => 'bulan mulai', 'bulan_selesai' => 'bulan selesai', 'jumlah' => 'jumlah pulsa', 'nama' => 'keterangan']);

        $p = $svc->buat([
            'bulan_mulai' => $data['bulan_mulai'], 'bulan_selesai' => $data['bulan_selesai'],
            'jumlah' => (int) $data['jumlah'], 'nama' => $data['nama'] ?: null,
        ], $this->pDipilih, Auth::user());
        session()->flash('sukses', 'Pulsa '.$p->periode(false).' untuk '.count($this->pDipilih).' petugas disimpan.');

        return $this->redirectRoute('sibeban.pulsa.show', $p, navigate: true);
    }

    public function hapus(): void
    {
        $p = Pulsa::findOrFail($this->hapusId);
        abort_unless($p->bisaDiubah(), 403);
        $p->delete();
        $this->hapusId = null;
        session()->flash('sukses', 'Pengaturan pulsa '.$p->periode(false).' dihapus. Petugasnya bisa diberi pulsa lagi di periode itu.');
    }

    public function render()
    {
        $pulsas = Pulsa::query()->withCount('mitras')
            ->when($this->cari !== '', fn ($q) => $q->where(fn ($w) => $w->where('nama', 'like', "%{$this->cari}%")->orWhere('pembuat', 'like', "%{$this->cari}%")))
            ->when($this->tahun !== '', fn ($q) => $q->beririsan($this->tahun.'-01', $this->tahun.'-12'))
            ->orderByDesc('bulan_mulai')->orderByDesc('id')
            ->paginate(15);

        return view('livewire.sibeban.pulsa.index', ['pulsas' => $pulsas] + ($this->formTampil ? $this->dataPilih() : ['kandidat' => [], 'jumlahKandidat' => 0, 'opsi' => null]));
    }
}
