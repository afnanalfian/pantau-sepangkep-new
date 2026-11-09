<div>
    <x-page-header title="Kelola tim" subtitle="Seret pegawai dari daftar kiri ke kolom tim. Klik bintang untuk menetapkan ketua tim.">
        <x-slot:actions>
            <div class="flex rounded-xl bg-navy-50 p-1" title="Perilaku saat menyeret antar tim">
                <button wire:click="$set('mode', 'move')" class="tab {{ $mode === 'move' ? 'active' : '' }}">Pindahkan</button>
                <button wire:click="$set('mode', 'copy')" class="tab {{ $mode === 'copy' ? 'active' : '' }}">Salin (multi-tim)</button>
            </div>
            <button wire:click="create" class="btn btn-blue"><x-icon name="plus" class="size-4" /> Tim baru</button>
        </x-slot:actions>
    </x-page-header>

    <div class="flex flex-col gap-5 lg:flex-row">
        {{-- Daftar pegawai --}}
        <aside class="card flex shrink-0 flex-col lg:sticky lg:top-24 lg:max-h-[calc(100dvh-8rem)] lg:w-72">
            <div class="border-b border-line p-3">
                <p class="mb-2 px-1 text-sm font-bold">Pegawai ({{ $this->pool->count() }})</p>
                <input wire:model.live.debounce.250ms="poolSearch" type="search" placeholder="Cari…" class="input py-2">
                <p class="mt-2 px-1 text-xs text-navy-500">Belum punya tim ditandai titik oranye. Seret kartu tim ke sini untuk mengeluarkan.</p>
            </div>
            <div class="max-h-80 min-h-24 flex-1 space-y-1 overflow-y-auto p-2 lg:max-h-none"
                 wire:sort="moveMember" wire:sort:group="members" wire:sort:group-id="pool">
                @foreach ($this->pool as $p)
                    <div wire:key="pool-{{ $p->id }}" wire:sort:item="{{ $p->id }}"
                         class="flex cursor-grab items-center gap-2.5 rounded-xl border border-transparent bg-white px-2 py-1.5 hover:border-line active:cursor-grabbing">
                        <x-icon name="grip" class="size-4 text-navy-300" />
                        <x-avatar :user="$p" size="xs" />
                        <span class="min-w-0 flex-1 truncate text-sm">{{ $p->display_name }}</span>
                        @if ($p->teams_count === 0)<span class="size-2 rounded-full bg-bps-orange" title="Belum punya tim"></span>@else<span class="text-[11px] text-navy-300">{{ $p->teams_count }} tim</span>@endif
                    </div>
                @endforeach
            </div>
        </aside>

        {{-- Kolom tim --}}
        <div class="grid flex-1 content-start gap-4 sm:grid-cols-2 2xl:grid-cols-3">
            @foreach ($this->teams as $team)
                <section wire:key="team-col-{{ $team->id }}" class="card flex flex-col overflow-hidden {{ $team->is_active ? '' : 'opacity-60' }}">
                    <div class="h-1.5 {{ $team->color() }}"></div>
                    <header class="flex items-start gap-2 px-4 pt-3 pb-2">
                        <div class="min-w-0 flex-1">
                            <h2 class="flex items-center gap-2 font-bold">{{ $team->name }}
                                @if ($team->is_core)<span class="chip bg-navy-50 text-navy-500 ring-line">Utama</span>@endif
                                @unless ($team->is_active)<span class="chip bg-slate-100 text-slate-500 ring-slate-300">Nonaktif</span>@endunless
                            </h2>
                            <p class="text-xs text-navy-500">{{ $team->members->count() }} anggota · ketua: {{ $team->members->where('pivot.role', 'ketua')->pluck('display_name')->join(', ') ?: 'belum ada' }}</p>
                        </div>
                        <div x-data="{ m: false }" class="relative">
                            <button @click="m = !m" class="btn btn-quiet p-1" aria-label="Opsi tim"><x-icon name="dots" class="size-5" /></button>
                            <div x-show="m" x-cloak @click.outside="m = false" class="absolute right-0 z-20 mt-1 w-48 rounded-xl border border-line bg-white p-1 text-sm shadow-xl">
                                <button wire:click="edit({{ $team->id }})" class="w-full rounded-lg px-3 py-2 text-left hover:bg-navy-50">Ubah nama & warna</button>
                                <button wire:click="shiftTeam({{ $team->id }}, -1)" class="w-full rounded-lg px-3 py-2 text-left hover:bg-navy-50">Geser ke depan</button>
                                <button wire:click="shiftTeam({{ $team->id }}, 1)" class="w-full rounded-lg px-3 py-2 text-left hover:bg-navy-50">Geser ke belakang</button>
                                <button wire:click="toggleActive({{ $team->id }})" class="w-full rounded-lg px-3 py-2 text-left hover:bg-navy-50">{{ $team->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                @unless ($team->is_core)<button wire:click="$set('confirmDeleteId', {{ $team->id }})" class="w-full rounded-lg px-3 py-2 text-left text-rose-600 hover:bg-rose-50">Hapus tim</button>@endunless
                            </div>
                        </div>
                    </header>
                    <div class="min-h-28 flex-1 space-y-1 bg-paper/50 p-2" wire:sort="moveMember" wire:sort:group="members" wire:sort:group-id="{{ $team->id }}">
                        @forelse ($team->members as $m)
                            @php $leader = $m->pivot->role === 'ketua'; @endphp
                            <div wire:key="tm-{{ $team->id }}-{{ $m->id }}" wire:sort:item="{{ $team->id * \App\Livewire\Admin\Teams::KEY + $m->id }}"
                                 class="group flex cursor-grab items-center gap-2.5 rounded-xl border bg-white px-2 py-1.5 active:cursor-grabbing {{ $leader ? 'border-bps-orange/40' : 'border-line' }}">
                                <x-icon name="grip" class="size-4 text-navy-300" />
                                <x-avatar :user="$m" size="xs" />
                                <span class="min-w-0 flex-1 truncate text-sm {{ $leader ? 'font-semibold' : '' }}">{{ $m->display_name }}</span>
                                <button wire:sort:ignore wire:click="toggleLeader({{ $team->id }}, {{ $m->id }})" title="{{ $leader ? 'Cabut ketua' : 'Jadikan ketua' }}"
                                        class="rounded-md p-0.5 {{ $leader ? 'text-bps-orange' : 'text-navy-100 group-hover:text-navy-300' }} hover:text-bps-orange">
                                    <svg viewBox="0 0 24 24" class="size-4" fill="{{ $leader ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.6"><path stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z"/></svg>
                                </button>
                                <button wire:sort:ignore wire:click="removeMember({{ $team->id }}, {{ $m->id }})" class="hidden rounded-md p-0.5 text-navy-300 group-hover:block hover:text-rose-600" title="Keluarkan"><x-icon name="x" class="size-4" /></button>
                            </div>
                        @empty
                            <div wire:sort:ignore class="grid h-24 place-items-center rounded-xl border-2 border-dashed border-navy-100 text-xs text-navy-300">Seret pegawai ke sini</div>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>
    </div>

    <x-modal :show="$showForm" close="$set('showForm', false)" :title="$editId ? 'Ubah tim' : 'Tim baru'">
        <form wire:submit="save" id="team-form" class="space-y-4">
            <div><label class="label">Nama tim</label><input wire:model="name" class="input @error('name') input-error @enderror" placeholder="mis. Tim Sensus Ekonomi 2026">@error('name')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label">Warna</label>
                <div class="flex flex-wrap gap-2">
                    @foreach (\App\Models\Team::COLORS as $k => $c)
                        <button type="button" wire:click="$set('color', '{{ $k }}')" class="size-9 rounded-xl {{ $c['bg'] }} {{ $color === $k ? 'ring-2 ring-navy-900 ring-offset-2' : '' }}" aria-label="{{ $k }}"></button>
                    @endforeach
                </div></div>
            <div><label class="label">Deskripsi</label><textarea wire:model="description" rows="2" class="input"></textarea></div>
        </form>
        <x-slot:footer>
            <button wire:click="$set('showForm', false)" class="btn btn-ghost">Batal</button>
            <button type="submit" form="team-form" class="btn btn-blue">Simpan</button>
        </x-slot:footer>
    </x-modal>

    <x-modal :show="(bool) $confirmDeleteId" close="$set('confirmDeleteId', null)" title="Hapus tim?" subtitle="Anggota dikeluarkan dari tim ini. Tugas lama tetap tersimpan.">
        <x-slot:footer>
            <button wire:click="$set('confirmDeleteId', null)" class="btn btn-ghost">Batal</button>
            <button wire:click="delete" class="btn btn-danger">Hapus</button>
        </x-slot:footer>
    </x-modal>
</div>
