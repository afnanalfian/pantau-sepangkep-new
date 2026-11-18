<?php

namespace App\Livewire\Sibeban;

use App\Models\Sibeban\Kegiatan;
use App\Models\Sibeban\LimitHonor;
use App\Models\Sibeban\Pengaturan;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.portal')]
#[Title('Pengaturan Limit')]
class PengaturanLimit extends Component
{
    /** ['Survei' => ['ppl' => 3910000, 'pml' => 3910000], ...] */
    public array $limit = [];

    public $ambangOranye = 75;

    public $limitDefault = 3910000;

    public function mount(): void
    {
        foreach (Kegiatan::JENIS as $j) {
            foreach (['ppl', 'pml'] as $jab) {
                $this->limit[$j][$jab] = (int) (LimitHonor::where('jenis', $j)->where('jabatan', $jab)->value('limit') ?? 0);
            }
        }
        $this->ambangOranye = (float) Pengaturan::nilai('ambang_oranye', '75');
        $this->limitDefault = (int) Pengaturan::nilai('limit_default', '3910000');
    }

    public function simpan(): void
    {
        $this->validate([
            'limit.*.*' => 'required|integer|min:1',
            'ambangOranye' => 'required|numeric|min:1|max:100',
            'limitDefault' => 'required|integer|min:1',
        ], [], ['limit.*.*' => 'limit', 'ambangOranye' => 'ambang oranye', 'limitDefault' => 'limit default']);

        foreach ($this->limit as $jenis => $per) {
            foreach ($per as $jabatan => $nilai) {
                LimitHonor::updateOrCreate(['jenis' => $jenis, 'jabatan' => $jabatan], ['limit' => (int) $nilai]);
            }
        }
        Pengaturan::setNilai('ambang_oranye', (string) $this->ambangOranye);
        Pengaturan::setNilai('limit_default', (string) (int) $this->limitDefault);

        session()->flash('sukses', 'Pengaturan disimpan. Status seluruh mitra dihitung ulang otomatis.');
    }

    public function render()
    {
        return view('livewire.sibeban.pengaturan-limit');
    }
}
