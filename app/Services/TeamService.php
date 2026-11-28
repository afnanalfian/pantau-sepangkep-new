<?php

namespace App\Services;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Aturan tim & subtim:
 *  - Subtim bernaung di bawah satu tim utama (satu tingkat saja).
 *  - Ketua subtim wajib anggota tim utamanya.
 *  - Ketua tim utama tidak boleh menjadi anggota/ketua subtim dari tim utamanya
 *    (ia tetap bisa memberi tugas ke subtim & mengintervensi tugasnya).
 *  - Anggota subtim bebas: dari tim utama maupun dari luar tim utama.
 */
class TeamService
{
    /** Pesan kesalahan bila user tidak boleh masuk tim ini dengan peran tsb, null bila boleh. */
    public function membershipError(Team $team, User $user, string $role = 'anggota'): ?string
    {
        if ($team->parent_id) {
            $parent = $team->parent;
            if ($parent && $parent->leaderIds()->contains($user->id)) {
                return $user->display_name.' adalah ketua '.$parent->name.', sehingga tidak bisa masuk subtimnya.';
            }
            if ($role === 'ketua' && $parent && ! $parent->members()->where('users.id', $user->id)->exists()) {
                return 'Ketua subtim harus anggota '.$parent->name.'.';
            }
        } elseif ($role === 'ketua') {
            $sub = Team::where('parent_id', $team->id)->whereHas('members', fn ($q) => $q->where('users.id', $user->id))->value('name');
            if ($sub) {
                return $user->display_name.' masih anggota subtim '.$sub.'. Keluarkan dulu dari subtim sebelum dijadikan ketua '.$team->name.'.';
            }
        }

        return null;
    }

    /** Validasi sebelum mengeluarkan anggota tim utama yang menjadi ketua subtim. */
    public function removalError(Team $team, User $user): ?string
    {
        if (! $team->parent_id) {
            $sub = Team::where('parent_id', $team->id)->whereHas('leaders', fn ($q) => $q->where('users.id', $user->id))->value('name');
            if ($sub) {
                return $user->display_name.' adalah ketua subtim '.$sub.'. Ganti dulu ketua subtim tersebut.';
            }
        }

        return null;
    }

    public function canManageSubs(User $actor, Team $parent): bool
    {
        return ! $parent->parent_id && ($actor->is_admin || $actor->ledMainTeamIds()->contains($parent->id));
    }

    /**
     * Simpan (buat / ubah) subtim beserta ketua dan anggotanya.
     *
     * @param  array{name:string,color?:string,description?:string,leader_id:int,member_ids:array}  $data
     */
    public function saveSub(Team $parent, array $data, User $actor, ?Team $sub = null): Team
    {
        if (! $this->canManageSubs($actor, $parent)) {
            abort(403, 'Hanya ketua '.$parent->name.' atau admin yang dapat mengelola subtimnya.');
        }
        if ($sub && $sub->parent_id !== $parent->id) {
            abort(404);
        }
        $leader = User::active()->find($data['leader_id'] ?? 0);
        if (! $leader) {
            throw ValidationException::withMessages(['subLeader' => 'Pilih ketua subtim.']);
        }
        $parentLeaders = $parent->leaderIds();
        if (! $parent->members()->where('users.id', $leader->id)->exists() || $parentLeaders->contains($leader->id)) {
            throw ValidationException::withMessages(['subLeader' => 'Ketua subtim harus anggota '.$parent->name.' (bukan ketuanya).']);
        }
        $memberIds = collect($data['member_ids'] ?? [])->map(fn ($i) => (int) $i)->reject(fn ($i) => $i === $leader->id)->unique();
        $blocked = $memberIds->intersect($parentLeaders);
        if ($blocked->isNotEmpty()) {
            throw ValidationException::withMessages(['subMembers' => 'Ketua '.$parent->name.' tidak bisa menjadi anggota subtimnya.']);
        }

        return DB::transaction(function () use ($parent, $data, $sub, $leader, $memberIds) {
            $attrs = [
                'name' => trim($data['name']),
                'color' => $data['color'] ?? $parent->color,
                'description' => $data['description'] ?? null,
                'parent_id' => $parent->id,
            ];
            if ($sub) {
                $sub->update($attrs);
            } else {
                $slug = Str::slug($parent->name.' '.$attrs['name']);
                if (Team::where('slug', $slug)->exists()) {
                    $slug .= '-'.Str::lower(Str::random(4));
                }
                $sub = Team::create($attrs + ['slug' => $slug, 'is_core' => false, 'is_active' => true,
                    'sort_order' => (int) Team::where('parent_id', $parent->id)->max('sort_order') + 1]);
            }
            $sync = [$leader->id => ['role' => 'ketua', 'sort_order' => 0]];
            $i = 1;
            foreach ($memberIds as $id) {
                $sync[$id] = ['role' => 'anggota', 'sort_order' => $i++];
            }
            $sub->members()->sync($sync);

            return $sub;
        });
    }

    public function deleteSub(Team $sub, User $actor): void
    {
        if (! $sub->parent_id || ! $this->canManageSubs($actor, $sub->parent)) {
            abort(403);
        }
        DB::transaction(function () use ($sub) {
            // Tugas subtim dipindahkan ke tim utamanya agar tetap tercatat
            \App\Models\Task::withTrashed()->where('team_id', $sub->id)->update(['team_id' => $sub->parent_id]);
            $sub->members()->detach();
            $sub->delete();
        });
    }
}
