<?php

namespace App\Livewire\Sibeban;

use App\Models\Sibeban\Pengaturan;
use App\Services\Sibeban\HonorService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.portal')]
#[Title('SIBEBAN · Limit Honor')]
class PengaturanLimit extends Component
{
    public $limitBulanan = 3910000;

    public $ambangOranye = 75;

    public function mount(): void
    {
        $this->limitBulanan = app(HonorService::class)->limit();
        $this->ambangOranye = (float) Pengaturan::nilai('ambang_oranye', '75');
    }

    public function simpan(): void
    {
        $this->validate([
            'limitBulanan' => 'required|integer|min:1',
            'ambangOranye' => 'required|numeric|min:1|max:100',
        ], [], ['limitBulanan' => 'limit honor bulanan', 'ambangOranye' => 'batas "mendekati limit"']);

        Pengaturan::setNilai('limit_bulanan', (string) (int) $this->limitBulanan);
        Pengaturan::setNilai('ambang_oranye', (string) $this->ambangOranye);
        session()->flash('sukses', 'Pengaturan disimpan. Status semua mitra langsung dihitung ulang.');
    }

    public function render()
    {
        return view('livewire.sibeban.pengaturan-limit');
    }
}
