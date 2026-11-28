<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\Toasts;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Kelola Tim')]
class Teams extends Component
{
    use Toasts;

    public string $mode = 'move'; // move | copy

    public string $poolSearch = '';

    public bool $showForm = false;

    public ?int $editId = null;

    public string $name = '';

    public string $color = 'blue';

    public string $description = '';

    public ?int $parent_id = null;

    public ?int $confirmDeleteId = null;

    #[Computed]
    public function teams()
    {
        // Tim utama diikuti subtimnya
        return Team::with(['members', 'parent'])->orderByRaw('COALESCE(parent_id, id)')->orderByRaw('parent_id IS NOT NULL')
            ->orderBy('sort_order')->orderBy('id')->get();
    }

    #[Computed]
    public function mainTeams()
    {
        return Team::whereNull('parent_id')->orderBy('sort_order')->get();
    }

    #[Computed]
    public function pool()
    {
        return User::active()->withCount('teams')
            ->when($this->poolSearch, fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$this->poolSearch}%")->orWhere('username', 'like', "%{$this->poolSearch}%")))
            ->orderByRaw('teams_count > 0')->orderByRaw('COALESCE(name, username)')->get();
    }

    /** Kunci item drag & drop: teamId × 100000 + userId (teamId 0 = daftar pegawai). */
    public const KEY = 100000;

    /**
     * Handler drag & drop. $group = "pool" atau id tim tujuan.
     */
    public function moveMember($item, $position, $group = null): void
    {
        $fromTeam = intdiv((int) $item, self::KEY);
        $from = $fromTeam === 0 ? 'pool' : (string) $fromTeam;
        $userId = (int) $item % self::KEY;
        $user = User::find($userId);
        if (! $user) {
            return;
        }
        $to = (string) $group;

        // Dijatuhkan ke daftar pegawai → keluarkan dari tim asal
        if ($to === 'pool') {
            if ($from !== 'pool') {
                $team = Team::find($from);
                if ($team && ($err = app(\App\Services\TeamService::class)->removalError($team, $user))) {
                    $this->toast($err, 'error');
                    $this->refreshData();

                    return;
                }
                $team?->members()->detach($user->id);
                $this->toast($user->display_name.' dikeluarkan dari '.$team?->name.'.');
            }
            $this->refreshData();

            return;
        }

        $target = Team::findOrFail((int) $to);

        if ($from === (string) $target->id) {
            // Urutkan ulang di tim yang sama
            $ids = $target->members->pluck('id')->reject(fn ($id) => $id == $user->id)->values()->all();
            array_splice($ids, max(0, (int) $position), 0, [$user->id]);
            foreach ($ids as $i => $id) {
                $target->members()->updateExistingPivot($id, ['sort_order' => $i]);
            }
            $this->refreshData();

            return;
        }

        if ($err = app(\App\Services\TeamService::class)->membershipError($target, $user)) {
            $this->toast($err, 'error');
            $this->refreshData();

            return;
        }
        if ($from !== 'pool' && $this->mode === 'move' && ($err = app(\App\Services\TeamService::class)->removalError(Team::find($from), $user))) {
            $this->toast($err, 'error');
            $this->refreshData();

            return;
        }
        if ($from !== 'pool' && $this->mode === 'move') {
            Team::find($from)?->members()->detach($user->id);
        }
        if (! $target->members()->where('users.id', $user->id)->exists()) {
            $target->members()->attach($user->id, ['role' => 'anggota', 'sort_order' => (int) $position]);
        }
        $this->toast($user->display_name.' → '.$target->name);
        $this->refreshData();
    }

    public function toggleLeader(int $teamId, int $userId): void
    {
        $team = Team::findOrFail($teamId);
        $member = $team->members()->where('users.id', $userId)->first();
        if (! $member) {
            return;
        }
        $isLeader = $member->pivot->role === 'ketua';
        if (! $isLeader && ($err = app(\App\Services\TeamService::class)->membershipError($team, $member, 'ketua'))) {
            $this->toast($err, 'error');

            return;
        }
        $team->members()->updateExistingPivot($userId, ['role' => $isLeader ? 'anggota' : 'ketua']);
        $this->toast($member->display_name.($isLeader ? ' bukan lagi ketua ' : ' ditetapkan sebagai Ketua ').$team->name.'.');
        $this->refreshData();
    }

    public function removeMember(int $teamId, int $userId): void
    {
        $team = Team::findOrFail($teamId);
        if ($err = app(\App\Services\TeamService::class)->removalError($team, User::findOrFail($userId))) {
            $this->toast($err, 'error');

            return;
        }
        $team->members()->detach($userId);
        $this->refreshData();
    }

    public function shiftTeam(int $teamId, int $dir): void
    {
        $teams = $this->teams->values();
        $idx = $teams->search(fn ($t) => $t->id === $teamId);
        $swap = $idx + $dir;
        if ($idx === false || $swap < 0 || $swap >= $teams->count()) {
            return;
        }
        $ordered = $teams->pluck('id')->all();
        [$ordered[$idx], $ordered[$swap]] = [$ordered[$swap], $ordered[$idx]];
        foreach ($ordered as $i => $id) {
            Team::whereKey($id)->update(['sort_order' => $i + 1]);
        }
        $this->refreshData();
    }

    public function create(): void
    {
        $this->reset('editId', 'name', 'description', 'parent_id');
        $this->color = collect(array_keys(Team::COLORS))->random();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $t = Team::findOrFail($id);
        $this->editId = $t->id;
        $this->name = $t->name;
        $this->color = $t->color;
        $this->description = (string) $t->description;
        $this->parent_id = $t->parent_id;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:100',
            'color' => 'required|in:'.implode(',', array_keys(Team::COLORS)),
            'description' => 'nullable|string|max:500',
            'parent_id' => 'nullable|exists:teams,id',
        ], [], ['name' => 'nama tim', 'parent_id' => 'tim induk']);
        $parent = $this->parent_id ? Team::find($this->parent_id) : null;
        if ($parent && ($parent->parent_id || $parent->id === $this->editId)) {
            $this->addError('parent_id', 'Tim induk harus tim utama.');

            return;
        }
        if ($parent && $this->editId && Team::where('parent_id', $this->editId)->exists()) {
            $this->addError('parent_id', 'Tim ini punya subtim, sehingga tidak bisa dijadikan subtim.');

            return;
        }
        if ($parent && $this->editId) {
            $t = Team::with('members')->findOrFail($this->editId);
            $leaders = $parent->leaderIds();
            if ($t->members->pluck('id')->intersect($leaders)->isNotEmpty()) {
                $this->addError('parent_id', 'Ketua '.$parent->name.' masih menjadi anggota tim ini.');

                return;
            }
        }

        if ($this->editId) {
            Team::findOrFail($this->editId)->update(['name' => $this->name, 'color' => $this->color, 'description' => $this->description, 'parent_id' => $this->parent_id ?: null]);
        } else {
            $slug = Str::slug($this->name);
            if (Team::where('slug', $slug)->exists()) {
                $slug .= '-'.Str::lower(Str::random(4));
            }
            Team::create([
                'name' => $this->name, 'slug' => $slug, 'color' => $this->color, 'description' => $this->description, 'parent_id' => $this->parent_id ?: null,
                'sort_order' => (int) Team::max('sort_order') + 1,
            ]);
        }
        $this->showForm = false;
        $this->toast('Tim disimpan.');
        $this->refreshData();
    }

    public function toggleActive(int $id): void
    {
        $t = Team::findOrFail($id);
        $t->update(['is_active' => ! $t->is_active]);
        $this->refreshData();
    }

    public function delete(): void
    {
        $t = Team::findOrFail($this->confirmDeleteId);
        if ($t->is_core) {
            $this->toast('Tim utama tidak dapat dihapus (bisa dinonaktifkan).', 'error');
        } elseif (Team::where('parent_id', $t->id)->exists()) {
            $this->toast('Hapus atau pindahkan dulu subtim di bawah '.$t->name.'.', 'error');
        } else {
            if ($t->parent_id) {
                \App\Models\Task::withTrashed()->where('team_id', $t->id)->update(['team_id' => $t->parent_id]);
            }
            $t->delete();
            $this->toast('Tim dihapus.');
        }
        $this->confirmDeleteId = null;
        $this->refreshData();
    }

    private function refreshData(): void
    {
        unset($this->teams, $this->pool, $this->mainTeams);
    }

    public function render()
    {
        return view('livewire.admin.teams');
    }
}
