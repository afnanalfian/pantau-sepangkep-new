<div>
    <x-page-header title="Tim kerja" subtitle="Susunan tim dan subtim, tugas aktif, dan penyelesaian tugas anggota bulan ini." />
    <div class="grid gap-5 md:grid-cols-2 2xl:grid-cols-3">
        @foreach ($teams as $team)
            <section class="card overflow-hidden" wire:key="team-{{ $team->id }}">
                <div class="h-1.5 {{ $team->color() }}"></div>
                <header class="flex items-start gap-2 px-5 pt-4 pb-3">
                    <div class="min-w-0 flex-1">
                        <h2 class="font-bold">{{ $team->name }}</h2>
                        @if ($team->description)<p class="mt-0.5 text-xs text-navy-500">{{ $team->description }}</p>@endif
                    </div>
                    @if ($canSubs[$team->id])
                        <button wire:click="openSub({{ $team->id }})" class="btn btn-ghost btn-sm shrink-0"><x-icon name="plus" class="size-3.5" /> Subtim</button>
                    @endif
                </header>
                <ul class="divide-y divide-line border-t border-line">
                    @forelse ($team->members->sortByDesc(fn ($m) => $m->pivot->role === 'ketua') as $m)
                        @include('livewire.teams.partials.member', ['m' => $m, 's' => $summary[$m->id] ?? null])
                    @empty
                        <li class="px-5 py-4 text-sm text-navy-300">Belum ada anggota.</li>
                    @endforelse
                </ul>

                @foreach ($subs->get($team->id, collect()) as $sub)
                    <div class="border-t-4 border-paper" wire:key="sub-{{ $sub->id }}">
                        <div class="flex items-center gap-2 bg-paper/60 px-5 py-2.5">
                            <span class="size-2.5 rounded-full {{ $sub->color() }}"></span>
                            <p class="min-w-0 flex-1 truncate text-sm font-bold">{{ $sub->name }} <span class="font-normal text-navy-500">· subtim</span></p>
                            @if ($canSubs[$team->id])
                                <button wire:click="openSub({{ $team->id }}, {{ $sub->id }})" class="btn-quiet rounded-lg p-1" title="Ubah subtim"><x-icon name="pencil" class="size-4" /></button>
                                <button wire:click="$set('subDeleteId', {{ $sub->id }})" class="btn-quiet rounded-lg p-1 hover:!text-rose-600" title="Hapus subtim"><x-icon name="trash" class="size-4" /></button>
                            @endif
                        </div>
                        <ul class="divide-y divide-line">
                            @forelse ($sub->members->sortByDesc(fn ($m) => $m->pivot->role === 'ketua') as $m)
                                @include('livewire.teams.partials.member', ['m' => $m, 's' => $summary[$m->id] ?? null])
                            @empty
                                <li class="px-5 py-3 text-sm text-navy-300">Belum ada anggota.</li>
                            @endforelse
                        </ul>
                    </div>
                @endforeach
            </section>
        @endforeach
    </div>

    {{-- Form subtim --}}
    <x-modal :show="(bool) $subParentId" close="closeSub" width="max-w-2xl" :title="$subEditId ? 'Ubah subtim' : 'Subtim baru'"
             subtitle="Subtim tetap bertanggung jawab ke tim utama. Ketua subtim dipilih dari anggota tim utama; anggotanya boleh dari tim mana pun.">
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2"><label class="label">Nama subtim</label>
                <input wire:model="subName" class="input @error('subName') input-error @enderror" placeholder="mis. Tim Statistik Harga">
                @error('subName')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label">Ketua subtim</label>
                <select wire:model.live="subLeader" class="input @error('subLeader') input-error @enderror">
                    <option value="">— pilih anggota tim utama —</option>
                    @foreach ($this->leaderOptions as $o)<option value="{{ $o->id }}">{{ $o->display_name }}</option>@endforeach
                </select>
                @error('subLeader')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label">Warna</label>
                <div class="flex flex-wrap gap-1.5">
                    @foreach (\App\Models\Team::COLORS as $k => $c)
                        <button type="button" wire:click="$set('subColor', '{{ $k }}')" class="size-8 rounded-lg {{ $c['bg'] }} {{ $subColor === $k ? 'ring-2 ring-navy-900 ring-offset-2' : '' }}" aria-label="{{ $k }}"></button>
                    @endforeach
                </div></div>
            <div class="sm:col-span-2">
                <div class="flex items-center justify-between"><label class="label mb-0">Anggota</label><span class="text-xs font-semibold text-bps-blue-700">{{ count($subMembers) }} dipilih</span></div>
                <input wire:model.live.debounce.250ms="subSearch" type="search" placeholder="Cari pegawai" class="input mt-1.5 py-2">
                <div class="mt-2 max-h-60 space-y-0.5 overflow-y-auto rounded-xl border border-line p-1">
                    @foreach ($this->memberOptions as $o)
                        @php $sel = in_array($o->id, $subMembers); @endphp
                        <button type="button" wire:key="so-{{ $o->id }}" wire:click="toggleSubMember({{ $o->id }})" class="flex w-full items-center gap-2.5 rounded-lg px-2 py-1.5 text-left {{ $sel ? 'bg-bps-blue-50' : 'hover:bg-paper' }}">
                            <x-avatar :user="$o" size="xs" />
                            <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium">{{ $o->display_name }}</span><span class="block truncate text-[11px] text-navy-500">{{ $o->teams->pluck('name')->join(', ') ?: $o->jabatan }}</span></span>
                            <span class="grid size-5 place-items-center rounded-md border {{ $sel ? 'border-bps-blue bg-bps-blue text-white' : 'border-navy-100' }}">@if ($sel)<x-icon name="check" class="size-3.5" />@endif</span>
                        </button>
                    @endforeach
                </div>
                @error('subMembers')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2"><label class="label">Deskripsi (opsional)</label><textarea wire:model="subDescription" rows="2" class="input"></textarea></div>
        </div>
        <x-slot:footer>
            <button wire:click="closeSub" class="btn btn-ghost">Batal</button>
            <button wire:click="saveSub" class="btn btn-blue" wire:loading.attr="disabled">Simpan subtim</button>
        </x-slot:footer>
    </x-modal>

    <x-modal :show="(bool) $subDeleteId" close="$set('subDeleteId', null)" title="Hapus subtim?" subtitle="Anggota dikeluarkan dari subtim. Tugas subtim dipindahkan ke tim utamanya.">
        <x-slot:footer>
            <button wire:click="$set('subDeleteId', null)" class="btn btn-ghost">Batal</button>
            <button wire:click="deleteSub" class="btn btn-danger">Hapus</button>
        </x-slot:footer>
    </x-modal>
</div>
