@php $u = auth()->user(); $dist = $this->distribution; $levels = \App\Services\KinerjaService::BEBAN; @endphp
<div>
    <x-page-header title="Beban kerja pegawai" subtitle="Jumlah tugas yang sedang dipegang tiap pegawai saat ini, dan penyelesaian tugas bulan terpilih.">
        <x-slot:actions>
            <input type="month" wire:model.live="month" class="input w-auto py-2" aria-label="Bulan">
        </x-slot:actions>
    </x-page-header>

    <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach (['kosong' => 'tidak ada tugas aktif', 'ringan' => '< '.\App\Support\Settings::get('beban_ringan', 2).' tugas aktif', 'normal' => \App\Support\Settings::get('beban_ringan', 2).'–'.\App\Support\Settings::get('beban_padat', 8).' tugas aktif', 'padat' => '> '.\App\Support\Settings::get('beban_padat', 8).' tugas aktif'] as $k => $range)
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
            <option value="aktif_asc">Tugas aktif paling sedikit</option>
            <option value="aktif_desc">Tugas aktif paling banyak</option>
            <option value="pct_asc">Penyelesaian terendah</option>
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
                            <div class="mt-1 flex items-center gap-2"><x-activity-dot :activity="$r['activity']" /><x-beban :beban="$r['beban']" /></div>
                        </div>
                        <span class="text-right leading-none"><span class="text-3xl font-extrabold">{{ $r['aktif'] }}</span><span class="block text-[11px] text-navy-500">tugas aktif</span></span>
                    </div>
                    <div class="mt-4 flex items-center gap-3">
                        <x-completion-bar class="flex-1" :selesai="$r['tugas_selesai']" :berjalan="$r['tugas_berjalan']" :gagal="$r['tugas_gagal']" />
                        <x-pct :value="$r['tugas_pct']" />
                    </div>
                    <p class="mt-2 text-xs text-navy-500">
                        {{ $r['tugas_selesai'] }}/{{ $r['tugas_total'] }} tugas bulan ini selesai
                        @if ($r['terlambat'])<span class="font-semibold text-rose-600"> · {{ $r['terlambat'] }} terlambat</span>@endif
                    </p>
                    @if ($u->canAssign() && $r['user']->id !== $u->id)
                        <button class="btn {{ in_array($r['beban'], ['kosong', 'ringan']) ? 'btn-blue' : 'btn-ghost' }} btn-sm mt-4"
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
                <table class="w-full min-w-[820px] text-sm">
                    <thead class="table-head"><tr>
                        <th class="px-5 py-3">Pegawai</th><th class="px-3 py-3">Status</th>
                        <th class="px-3 py-3 text-right">Tugas aktif</th><th class="px-3 py-3 text-right">Terlambat</th>
                        <th class="px-3 py-3 w-56">Penyelesaian bulan ini</th><th class="px-5 py-3"></th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($this->rows as $r)
                            <tr wire:key="wlt-{{ $r['user']->id }}">
                                <td class="px-5 py-2.5"><span class="flex items-center gap-2"><x-avatar :user="$r['user']" size="xs" /><span class="truncate font-medium">{{ $r['user']->display_name }}</span></span></td>
                                <td class="px-3 py-2.5"><div class="flex items-center gap-2"><x-activity-dot :activity="$r['activity']" /><x-beban :beban="$r['beban']" /></div></td>
                                <td class="px-3 py-2.5 text-right font-bold">{{ $r['aktif'] }}</td>
                                <td class="px-3 py-2.5 text-right {{ $r['terlambat'] ? 'font-semibold text-rose-600' : 'text-navy-300' }}">{{ $r['terlambat'] }}</td>
                                <td class="px-3 py-2.5"><div class="flex items-center gap-2"><x-completion-bar class="flex-1" height="h-2" :selesai="$r['tugas_selesai']" :berjalan="$r['tugas_berjalan']" :gagal="$r['tugas_gagal']" /><x-pct :value="$r['tugas_pct']" /></div></td>
                                <td class="px-5 py-2.5 text-right">@if ($u->canAssign())<button class="btn btn-ghost btn-sm" @click="$dispatch('open-task-form', { mode: 'assign', assignee: {{ $r['user']->id }} })">Beri tugas</button>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
