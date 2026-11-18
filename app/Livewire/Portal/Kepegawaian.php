<?php

namespace App\Livewire\Portal;

use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Kepegawaian')]
#[Layout('layouts.portal')]
class Kepegawaian extends Component
{
    #[Url(except: '')]
    public string $search = '';

    public function render()
    {
        $pegawai = User::pegawai()->with('teams')
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$this->search}%")
                ->orWhere('nip', 'like', "%{$this->search}%")->orWhere('jabatan', 'like', "%{$this->search}%")))
            ->orderByRaw('name IS NULL, name')->get();

        return view('livewire.portal.kepegawaian', ['pegawai' => $pegawai, 'biodata' => config('portal.kepegawaian.biodata')]);
    }
}
