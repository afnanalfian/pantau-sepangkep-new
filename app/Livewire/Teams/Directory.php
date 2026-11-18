<?php

namespace App\Livewire\Teams;

use App\Models\Team;
use App\Services\KinerjaService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Tim Kerja')]
class Directory extends Component
{
    public function render()
    {
        $teams = Team::with(['members' => fn ($q) => $q->where('is_active', true)])->where('is_active', true)->orderBy('sort_order')->get();
        $summary = app(KinerjaService::class)->matriks($teams->flatMap->members->unique('id'));

        return view('livewire.teams.directory', compact('teams', 'summary'));
    }
}
