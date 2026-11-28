<?php

namespace App\Livewire\Teams;

use App\Livewire\Concerns\Toasts;
use App\Models\Team;
use App\Models\User;
use App\Services\KinerjaService;
use App\Services\TeamService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Tim Kerja')]
class Directory extends Component
{
    use Toasts;

    // Form subtim (ketua tim utama / admin)
    public ?int $subParentId = null;

    public ?int $subEditId = null;

    public string $subName = '';

    public string $subColor = 'blue';

    public string $subDescription = '';

    public ?int $subLeader = null;

    public array $subMembers = [];

    public string $subSearch = '';

    public ?int $subDeleteId = null;

    public function openSub(int $parentId, ?int $subId = null): void
    {
        $parent = Team::findOrFail($parentId);
        abort_unless(app(TeamService::class)->canManageSubs(Auth::user(), $parent), 403);
        $this->resetValidation();
        $this->subParentId = $parent->id;
        $this->subEditId = $subId;
        $this->subSearch = '';
        if ($subId) {
            $sub = Team::with('members')->where('parent_id', $parent->id)->findOrFail($subId);
            $this->subName = $sub->name;
            $this->subColor = $sub->color;
            $this->subDescription = (string) $sub->description;
            $this->subLeader = $sub->members->firstWhere('pivot.role', 'ketua')?->id;
            $this->subMembers = $sub->members->where('pivot.role', '!=', 'ketua')->pluck('id')->all();
        } else {
            $this->subName = '';
            $this->subColor = $parent->color;
            $this->subDescription = '';
            $this->subLeader = null;
            $this->subMembers = [];
        }
    }

    public function closeSub(): void
    {
        $this->subParentId = null;
    }

    public function toggleSubMember(int $id): void
    {
        $this->subMembers = in_array($id, $this->subMembers)
            ? array_values(array_diff($this->subMembers, [$id]))
            : array_merge($this->subMembers, [$id]);
    }

    public function saveSub(TeamService $svc): void
    {
        $this->validate(['subName' => 'required|string|max:100', 'subLeader' => 'required|integer'], [],
            ['subName' => 'nama subtim', 'subLeader' => 'ketua subtim']);
        $parent = Team::findOrFail($this->subParentId);
        $ok = $this->attempt(fn () => $svc->saveSub($parent, [
            'name' => $this->subName, 'color' => $this->subColor, 'description' => $this->subDescription ?: null,
            'leader_id' => $this->subLeader, 'member_ids' => $this->subMembers,
        ], Auth::user(), $this->subEditId ? Team::find($this->subEditId) : null), 'Subtim disimpan.');
        if ($ok) {
            $this->subParentId = null;
        }
    }

    public function deleteSub(TeamService $svc): void
    {
        $sub = Team::findOrFail($this->subDeleteId);
        $svc->deleteSub($sub, Auth::user());
        $this->subDeleteId = null;
        $this->toast('Subtim dihapus. Tugasnya dipindahkan ke '.$sub->parent?->name.'.');
    }

    /** Calon ketua subtim: anggota tim utama selain ketuanya. */
    #[Computed]
    public function leaderOptions()
    {
        if (! $this->subParentId) {
            return collect();
        }

        return Team::find($this->subParentId)->members()->wherePivot('role', '!=', 'ketua')->where('is_active', true)->get();
    }

    /** Calon anggota subtim: seluruh pegawai aktif kecuali ketua tim utama. */
    #[Computed]
    public function memberOptions()
    {
        if (! $this->subParentId) {
            return collect();
        }
        $exclude = Team::find($this->subParentId)->leaderIds()->push($this->subLeader)->filter()->all();
        $q = User::pegawai()->with('teams')->whereNotIn('id', $exclude)->orderByRaw('COALESCE(name, username)');
        if ($this->subSearch !== '') {
            $w = '%'.$this->subSearch.'%';
            $q->where(fn ($x) => $x->where('name', 'like', $w)->orWhere('username', 'like', $w));
        }

        return $q->get();
    }

    public function render()
    {
        $all = Team::with(['members' => fn ($q) => $q->where('is_active', true)])->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        $teams = $all->whereNull('parent_id')->values();
        $subs = $all->whereNotNull('parent_id')->groupBy('parent_id');
        $summary = app(KinerjaService::class)->matriks($all->flatMap->members->unique('id'));
        $svc = app(TeamService::class);
        $canSubs = $teams->mapWithKeys(fn ($t) => [$t->id => $svc->canManageSubs(Auth::user(), $t)]);

        return view('livewire.teams.directory', compact('teams', 'subs', 'summary', 'canSubs'));
    }
}
