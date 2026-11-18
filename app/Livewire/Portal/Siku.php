<?php

namespace App\Livewire\Portal;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('SIKU · Sistem Informasi Keuangan')]
#[Layout('layouts.portal')]
class Siku extends Component
{
    public function render()
    {
        return view('livewire.portal.siku', ['links' => config('portal.siku')]);
    }
}
