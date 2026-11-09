@php $u = auth()->user(); $dist = $this->distribution; $levels = \App\Services\WorkloadService::LEVELS; @endphp
<div>
    <x-page-header title="Beban kerja pegawai" subtitle="Beban = (poin terverifikasi + poin berjalan) ÷ target. Tugas terlambat tidak dihitung sampai diperpanjang.">
        <x-slot:actions>
            <input type="month" wire:model.live="month" class="input w-auto py-2">
        </x-slot:actions>
    </x-page-header>

    {{-- Distribusi level --}}
    <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach (['underloaded' => '< 50% target', 'normal' => '50–80%', 'optimal' => '80–110%', 'overloaded' => '> 110%'] as $k => $range)
            <button wire:click="$set('level', '{{ $level === $k ? '' : $k }}')"
                    class="card flex items-center gap-3 p-4 text-left transition {{ $level === $k ? 'ring-2 ring-navy-900' : 'hover:border-navy-300' }}">
                <span class="slant h-10 w-3 {{ $levels[$k]['class'] }}"></span>
                <span><span class="block text-2xl font-extrabold">{{ $dist[$k] }}</span><span class="text-xs text-navy-500">{{ $levels[$k]['label'] }} · {{ $range }}</span></span>
            </button>
        @endforeach
    </div>

    <div class="mb-5 flex flex-wrap items-center gap-2">
        <select wire:model.live="team" class="input w-auto py-2">
            @if ($u->is_pimpinan || $u->is_admin)<option value="">Semua tim</option>@endif
            @foreach ($this->teams as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
        </select>
        <select wire:model.live="sort" class="input w-auto py-2">
            <option value="load_asc">Beban terendah dulu</option>
            <option value="load_desc">Beban tertinggi dulu</option>
            <option value="overdue">Terlambat terbanyak</option>
            <option value="name">Nama</option>
        </select>
        <div class="ml-auto flex rounded-xl bg-navy-50 p-1">
            <button wire:click="$set('display', 'grid')" class="tab {{ $display === 'grid' ? 'active' : '' }}">Kartu</button>
            <button wire:click="$set('display', 'table')" class="tab {{ $display === 'table' ? 'active' : '' }}">Tabel</button>
        </div>
    </div>

    @if ($display === 'grid')
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
            @forelse ($this->rows as $r)
                <article class="card flex flex-col p-4" wire:key="wl-{{ $r['user']->id }}">
                    <div class="flex items-start gap-3">
                        <x-avatar :user="$r['user']" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold">{{ $r['user']->display_name }}</p>
                            <p class="truncate text-xs text-navy-500">{{ $r['user']->jabatan ?: $r['user']->teams->pluck('name')->join(', ') }}</p>
                            <div class="mt-1 flex items-center gap-2"><x-activity-dot :activity="$r['activity']" /><x-level :level="$r['level']" /></div>
                        </div>
                        <span class="text-2xl font-extrabold {{ $r['level'] === 'overloaded' ? 'text-rose-600' : ($r['level'] === 'underloaded' ? 'text-amber-600' : '') }}">{{ round($r['load_pct']) }}<span class="text-sm">%</span></span>
                    </div>
                    <x-points-bar class="mt-4" :verified="$r['verified']" :committed="$r['committed']" :target="$r['target']" />
                    <dl class="mt-3 grid grid-cols-3 gap-2 text-center text-xs">
                        <div class="rounded-lg bg-paper py-1.5"><dt class="text-navy-500">Terverifikasi</dt><dd class="font-bold">{{ poin($r['verified']) }}</dd></div>
                        <div class="rounded-lg bg-paper py-1.5"><dt class="text-navy-500">Berjalan</dt><dd class="font-bold">{{ poin($r['committed']) }}</dd></div>
                        <div class="rounded-lg bg-paper py-1.5"><dt class="text-navy-500">Target</dt><dd class="font-bold">{{ poin($r['target']) }}</dd></div>
                    </dl>
                    <div class="mt-3 flex items-center gap-2 text-xs text-navy-500">
                        <span>{{ $r['active_count'] }} tugas aktif</span>
                        @if ($r['overdue_count'])<span class="font-semibold text-rose-600">· {{ $r['overdue_count'] }} terlambat</span>@endif
                        @if ($r['leave_days'])<span>· {{ $r['leave_days'] }} hr cuti/DL</span>@endif
                    </div>
                    @if ($u->canAssign() && $r['user']->id !== $u->id)
                        <button class="btn {{ in_array($r['level'], ['underloaded', 'normal']) ? 'btn-blue' : 'btn-ghost' }} btn-sm mt-4"
                                @click="$dispatch('open-task-form', { mode: 'assign', assignee: {{ $r['user']->id }} })">
                            <x-icon name="plus" class="size-3.5" /> Beri tugas langsung
                        </button>
                    @endif
                </article>
            @empty
                <div class="card sm:col-span-2 xl:col-span-3"><x-empty icon="users" title="Tidak ada pegawai" text="Atur anggota tim di menu Kelola Tim." /></div>
            @endforelse
        </div>
    @else
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-sm">
                    <thead class="table-head"><tr>
                        <th class="px-5 py-3">Pegawai</th><th class="px-3 py-3">Status</th><th class="px-3 py-3 w-64">Capaian</th>
                        <th class="px-3 py-3 text-right">Terverifikasi</th><th class="px-3 py-3 text-right">Berjalan</th><th class="px-3 py-3 text-right">Target</th>
                        <th class="px-3 py-3 text-right">Beban</th><th class="px-3 py-3 text-right">Terlambat</th><th class="px-5 py-3"></th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($this->rows as $r)
                            <tr wire:key="wlt-{{ $r['user']->id }}">
                                <td class="px-5 py-2.5"><span class="flex items-center gap-2"><x-avatar :user="$r['user']" size="xs" /><span class="truncate font-medium">{{ $r['user']->display_name }}</span></span></td>
                                <td class="px-3 py-2.5"><div class="flex items-center gap-2"><x-activity-dot :activity="$r['activity']" /><x-level :level="$r['level']" /></div></td>
                                <td class="px-3 py-2.5"><x-points-bar height="h-2" :verified="$r['verified']" :committed="$r['committed']" :target="$r['target']" /></td>
                                <td class="px-3 py-2.5 text-right font-semibold">{{ poin($r['verified']) }}</td>
                                <td class="px-3 py-2.5 text-right">{{ poin($r['committed']) }}</td>
                                <td class="px-3 py-2.5 text-right">{{ poin($r['target']) }}</td>
                                <td class="px-3 py-2.5 text-right font-bold">{{ round($r['load_pct']) }}%</td>
                                <td class="px-3 py-2.5 text-right {{ $r['overdue_count'] ? 'font-semibold text-rose-600' : 'text-navy-300' }}">{{ $r['overdue_count'] }}</td>
                                <td class="px-5 py-2.5 text-right">@if ($u->canAssign())<button class="btn btn-ghost btn-sm" @click="$dispatch('open-task-form', { mode: 'assign', assignee: {{ $r['user']->id }} })">Beri tugas</button>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
