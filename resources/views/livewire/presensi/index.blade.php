<div>
    <x-page-header title="Presensi apel & rapat" subtitle="Catat kehadiran pegawai. Rekap persentase masuk ke matriks kinerja bulanan.">
        <x-slot:actions>
            <button wire:click="create('apel')" class="btn btn-orange"><x-icon name="flag" class="size-4" /> Apel baru</button>
            <button wire:click="create('rapat')" class="btn btn-blue"><x-icon name="users" class="size-4" /> Rapat baru</button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="flex gap-1 overflow-x-auto rounded-xl bg-navy-50 p-1">
            <button wire:click="$set('tab', 'kegiatan')" class="tab shrink-0 {{ $tab === 'kegiatan' ? 'active' : '' }}">Daftar kegiatan</button>
            <button wire:click="$set('tab', 'rekap')" class="tab shrink-0 {{ $tab === 'rekap' ? 'active' : '' }}">Rekap per pegawai</button>
            @if ($this->canManagePetugas())
                <button wire:click="$set('tab', 'petugas')" class="tab shrink-0 {{ $tab === 'petugas' ? 'active' : '' }}">Petugas presensi</button>
            @endif
        </div>
        @if ($tab !== 'petugas')
            <div class="flex items-center gap-1 sm:ml-auto">
                <button wire:click="shift(-1)" class="btn btn-ghost px-2.5" aria-label="Bulan sebelumnya"><x-icon name="chevron-left" class="size-4" /></button>
                <span class="min-w-36 text-center text-sm font-bold">{{ $monthLabel }}</span>
                <button wire:click="shift(1)" class="btn btn-ghost px-2.5" aria-label="Bulan berikutnya"><x-icon name="chevron-right" class="size-4" /></button>
            </div>
        @endif
    </div>

    @if ($tab === 'kegiatan')
        <div class="mb-4 flex gap-2">
            @foreach (['' => 'Semua', 'apel' => 'Apel', 'rapat' => 'Rapat'] as $k => $l)
                <button wire:click="$set('jenisFilter', '{{ $k }}')" class="chip px-3 py-1 text-xs {{ $jenisFilter === $k ? 'bg-navy-900 text-white ring-navy-900' : 'bg-white text-navy-700 ring-line' }}">{{ $l }}</button>
            @endforeach
        </div>
        <div class="grid gap-3 md:grid-cols-2 2xl:grid-cols-3">
            @forelse ($this->kegiatans as $k)
                @php $j = \App\Models\PresensiKegiatan::JENIS[$k->jenis]; $tot = max($k->total_pegawai - $k->izin_count, 0); @endphp
                <article class="card flex flex-col p-4" wire:key="pk-{{ $k->id }}">
                    <div class="flex items-start gap-3">
                        <div class="grid w-14 shrink-0 place-items-center rounded-xl bg-paper py-2 text-center leading-tight">
                            <span class="text-xl font-extrabold">{{ $k->tanggal->format('d') }}</span>
                            <span class="text-[11px] font-semibold text-navy-500">{{ tgl($k->tanggal, 'M') }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <span class="chip {{ $j['class'] }}">{{ $j['label'] }}</span>
                            <a href="{{ route('presensi.show', $k) }}" wire:navigate class="mt-1 block truncate font-semibold hover:text-bps-blue-700">{{ $k->nama }}</a>
                            <p class="truncate text-xs text-navy-500">{{ tgl($k->tanggal, 'l') }}{{ $k->jam ? ', '.substr($k->jam, 0, 5) : '' }}{{ $k->tempat ? ' · '.$k->tempat : '' }}</p>
                        </div>
                        <div x-data="{ m: false }" class="relative">
                            <button @click="m = !m" class="btn btn-quiet p-1" aria-label="Opsi"><x-icon name="dots" class="size-5" /></button>
                            <div x-show="m" x-cloak @click.outside="m = false" class="absolute right-0 z-20 mt-1 w-40 rounded-xl border border-line bg-white p-1 text-sm shadow-xl">
                                <button wire:click="edit({{ $k->id }})" @click="m = false" class="w-full rounded-lg px-3 py-2 text-left hover:bg-navy-50">Ubah</button>
                                <button wire:click="$set('confirmDeleteId', {{ $k->id }})" @click="m = false" class="w-full rounded-lg px-3 py-2 text-left text-rose-600 hover:bg-rose-50">Hapus</button>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-3">
                        <div class="slant-track h-2.5 flex-1"><div class="absolute inset-y-0 left-0 {{ $j['dot'] }}" style="width: {{ $tot ? $k->hadir_count / $tot * 100 : 0 }}%"></div></div>
                        <span class="text-sm font-bold">{{ $k->hadir_count }}<span class="font-medium text-navy-500">/{{ $tot }}</span></span>
                    </div>
                    <a href="{{ route('presensi.show', $k) }}" wire:navigate class="btn btn-ghost btn-sm mt-3">Buka daftar hadir</a>
                </article>
            @empty
                <div class="card md:col-span-2 2xl:col-span-3">
                    <x-empty icon="calendar" title="Belum ada apel atau rapat di bulan ini" text="Buat kegiatan baru, lalu centang pegawai yang hadir.">
                        <div class="flex gap-2"><button wire:click="create('apel')" class="btn btn-orange btn-sm">Apel baru</button><button wire:click="create('rapat')" class="btn btn-blue btn-sm">Rapat baru</button></div>
                    </x-empty>
                </div>
            @endforelse
        </div>
    @elseif ($tab === 'rekap')
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-sm">
                    <thead class="table-head"><tr>
                        <th class="px-5 py-3">Pegawai</th>
                        <th class="px-3 py-3 text-right">Apel</th><th class="px-3 py-3 text-right">Izin apel</th>
                        <th class="px-3 py-3 text-right">Rapat</th><th class="px-5 py-3 text-right">Izin rapat</th>
                    </tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($this->rekap as $r)
                            <tr wire:key="rk-{{ $r['user']->id }}">
                                <td class="px-5 py-2.5"><span class="flex items-center gap-2"><x-avatar :user="$r['user']" size="xs" /><span class="truncate font-medium">{{ $r['user']->display_name }}</span></span></td>
                                <td class="px-3 py-2.5 text-right"><x-pct :value="$r['apel_pct']" :num="$r['apel_hadir']" :den="$r['apel_wajib']" /></td>
                                <td class="px-3 py-2.5 text-right text-navy-500">{{ $r['apel_izin'] ?: '–' }}</td>
                                <td class="px-3 py-2.5 text-right"><x-pct :value="$r['rapat_pct']" :num="$r['rapat_hadir']" :den="$r['rapat_wajib']" /></td>
                                <td class="px-5 py-2.5 text-right text-navy-500">{{ $r['rapat_izin'] ?: '–' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="border-t border-line px-5 py-3 text-xs text-navy-500">Izin, cuti, dan dinas luar tidak dihitung sebagai ketidakhadiran. Kegiatan yang tanggalnya belum tiba belum dihitung.</p>
        </div>
    @else
        <div class="grid gap-6 lg:grid-cols-2">
            <section class="card">
                <h2 class="px-5 pt-5 font-bold">Petugas presensi saat ini</h2>
                <p class="px-5 text-sm text-navy-500">Hanya mereka (selain admin & pimpinan) yang melihat menu Presensi.</p>
                <ul class="mt-3 divide-y divide-line">
                    @forelse ($this->petugas as $p)
                        <li class="flex items-center gap-3 px-5 py-3" wire:key="pt-{{ $p->id }}">
                            <x-avatar :user="$p" size="sm" />
                            <span class="min-w-0 flex-1"><span class="block truncate font-medium">{{ $p->display_name }}</span><span class="text-xs text-navy-500">{{ $p->jabatan ?: '@'.$p->username }}</span></span>
                            <button wire:click="setPetugas({{ $p->id }}, false)" class="btn btn-ghost btn-sm">Cabut</button>
                        </li>
                    @empty
                        <li><x-empty icon="user" title="Belum ada petugas presensi" text="Tunjuk pegawai dari daftar di samping." /></li>
                    @endforelse
                </ul>
            </section>
            <section class="card">
                <h2 class="px-5 pt-5 font-bold">Tunjuk petugas</h2>
                <div class="px-5 pt-3"><input wire:model.live.debounce.250ms="petugasSearch" type="search" placeholder="Cari nama…" class="input py-2"></div>
                <ul class="mt-2 max-h-[28rem] divide-y divide-line overflow-y-auto">
                    @foreach ($this->kandidat as $p)
                        <li class="flex items-center gap-3 px-5 py-2.5" wire:key="kd-{{ $p->id }}">
                            <x-avatar :user="$p" size="xs" />
                            <span class="min-w-0 flex-1 truncate text-sm">{{ $p->display_name }}</span>
                            <button wire:click="setPetugas({{ $p->id }}, true)" class="btn btn-blue btn-sm">Tunjuk</button>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>
    @endif

    <x-modal :show="$showForm" close="$set('showForm', false)" :title="$editId ? 'Ubah kegiatan' : 'Kegiatan presensi baru'">
        <form wire:submit="save" id="presensi-form" class="space-y-4">
            <div>
                <span class="label">Jenis</span>
                <div class="grid grid-cols-2 gap-2">
                    @foreach (\App\Models\PresensiKegiatan::JENIS as $k => $j)
                        <button type="button" wire:click="$set('form.jenis', '{{ $k }}')"
                                class="rounded-xl border-2 px-4 py-3 text-left transition {{ ($form['jenis'] ?? '') === $k ? 'border-navy-900 bg-paper' : 'border-line hover:border-navy-300' }}">
                            <span class="flex items-center gap-2 font-bold"><span class="size-2.5 rounded-full {{ $j['dot'] }}"></span>{{ $j['label'] }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
            <div><label class="label">Nama / keterangan kegiatan</label><input wire:model="form.nama" class="input @error('form.nama') input-error @enderror" placeholder="mis. Rapat evaluasi kinerja triwulan III">@error('form.nama')<p class="error">{{ $message }}</p>@enderror</div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label">Tanggal</label><input wire:model="form.tanggal" type="date" class="input"></div>
                <div><label class="label">Jam</label><input wire:model="form.jam" type="time" class="input">@error('form.jam')<p class="error">{{ $message }}</p>@enderror</div>
            </div>
            <div><label class="label">Tempat</label><input wire:model="form.tempat" class="input"></div>
            <div><label class="label">Catatan</label><textarea wire:model="form.keterangan" rows="2" class="input"></textarea></div>
        </form>
        <x-slot:footer>
            <button wire:click="$set('showForm', false)" class="btn btn-ghost">Batal</button>
            <button type="submit" form="presensi-form" class="btn btn-blue">{{ $editId ? 'Simpan' : 'Buat & isi daftar hadir' }}</button>
        </x-slot:footer>
    </x-modal>

    <x-modal :show="(bool) $confirmDeleteId" close="$set('confirmDeleteId', null)" title="Hapus kegiatan presensi?" subtitle="Seluruh catatan kehadirannya ikut terhapus dan rekap bulan ini dihitung ulang.">
        <x-slot:footer>
            <button wire:click="$set('confirmDeleteId', null)" class="btn btn-ghost">Batal</button>
            <button wire:click="delete" class="btn btn-danger">Hapus</button>
        </x-slot:footer>
    </x-modal>
</div>
