@php use App\Support\Sibeban\Format; use App\Support\Sibeban\Periode; @endphp
<div>
    @include('livewire.sibeban.partials.nav')
    @include('livewire.sibeban.partials.flash')

    <x-page-header title="Kegiatan" subtitle="Buat kegiatan, pilih petugas dari database mitra, lalu impor jabatan & honor masing-masing petugas lewat template Excel.">
        <x-slot:actions>
            <button wire:click="tambah" class="btn btn-blue"><x-icon name="plus" class="size-4" /> Kegiatan baru</button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <div class="relative w-full sm:w-72">
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-navy-300" />
            <input type="search" wire:model.live.debounce.400ms="cari" placeholder="Cari kegiatan atau tim…" class="input !pl-9">
        </div>
        <select wire:model.live="tahun" class="input !w-auto">
            <option value="">Semua tahun</option>
            @foreach (range(now()->year + 1, now()->year - 3) as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
        </select>
        <label class="inline-flex items-center gap-2 text-sm text-navy-700"><input type="checkbox" wire:model.live="milikSaya" class="rounded border-line"> Buatan saya</label>
    </div>

    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($kegiatans as $k)
            @php $t = $totalHonor[$k->id] ?? null; @endphp
            <div class="card group relative flex flex-col p-4 transition hover:border-navy-300 hover:shadow-md" wire:key="k-{{ $k->id }}">
                <div class="flex items-start justify-between gap-2">
                    <a href="{{ route('sibeban.kegiatan.show', $k) }}" wire:navigate class="min-w-0 after:absolute after:inset-0">
                        <p class="font-bold leading-snug text-navy-900 group-hover:text-bps-blue-700">{{ $k->nama }}</p>
                        <p class="mt-0.5 text-xs text-navy-500">{{ $k->tim ? 'Tim '.$k->tim.' · ' : '' }}{{ $k->tanggal_mulai->translatedFormat('j M Y') }} – {{ $k->tanggal_selesai->translatedFormat('j M Y') }}</p>
                    </a>
                    @if ($k->bisaDiubah())
                        <div class="relative z-10 flex shrink-0 gap-0.5">
                            <button wire:click="edit({{ $k->id }})" class="btn-quiet rounded-lg p-1.5" aria-label="Ubah"><x-icon name="pencil" class="size-4" /></button>
                            <button wire:click="$set('hapusId', {{ $k->id }})" class="btn-quiet rounded-lg p-1.5 hover:!text-rose-600" aria-label="Hapus"><x-icon name="trash" class="size-4" /></button>
                        </div>
                    @endif
                </div>
                <div class="mt-4 grid grid-cols-3 gap-2 border-t border-line pt-3 text-xs">
                    <div><p class="text-navy-500">Petugas</p><p class="mt-0.5 text-base font-bold tabular-nums">{{ $k->penugasans_count }}</p></div>
                    <div><p class="text-navy-500">Honor terisi</p><p class="mt-0.5 text-base font-bold tabular-nums">{{ (int) ($t->terisi ?? 0) }}</p></div>
                    <div><p class="text-navy-500">Total honor</p><p class="mt-0.5 font-bold tabular-nums">{{ Format::rp($t->total ?? 0) }}</p></div>
                </div>
                <p class="mt-3 text-[11px] text-navy-400">{{ $k->sumber === 'import-matriks' ? 'Impor matriks honor' : 'Dibuat '.($k->pembuat ?: '–') }}</p>
            </div>
        @empty
            <div class="card col-span-full"><x-empty icon="calendar" title="Belum ada kegiatan" text="Klik “Kegiatan baru” untuk mulai." /></div>
        @endforelse
    </div>
    <div class="mt-4">{{ $kegiatans->links() }}</div>

    <x-modal :show="$formTampil" close="$set('formTampil', false)" :title="$editId ? 'Ubah kegiatan' : 'Kegiatan baru'" subtitle="Honor tidak diisi di sini — honor tiap petugas diisi di template Excel.">
        <form wire:submit="simpan" id="form-kegiatan" class="space-y-4">
            <div>
                <label class="label">Nama kegiatan</label>
                <input wire:model="nama" class="input @error('nama') input-error @enderror" placeholder="mis. Survei Sosial Ekonomi Nasional (Susenas) Maret">
                @error('nama')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Tim</label>
                <input wire:model="tim" list="daftar-tim" class="input" placeholder="Pilih atau ketik">
                <datalist id="daftar-tim">@foreach (\App\Livewire\Sibeban\Kegiatan\Index::TIM as $t)<option value="{{ $t }}">@endforeach</datalist>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label">Tanggal mulai</label>
                    <input type="date" wire:model.live="tanggal_mulai" class="input @error('tanggal_mulai') input-error @enderror">
                    @error('tanggal_mulai')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label">Tanggal selesai</label>
                    <input type="date" wire:model.live="tanggal_selesai" class="input @error('tanggal_selesai') input-error @enderror">
                    @error('tanggal_selesai')<p class="error">{{ $message }}</p>@enderror
                </div>
            </div>
            @php $bl = Periode::bulanAntara($tanggal_mulai ?: null, $tanggal_selesai ?: null); @endphp
            @if ($bl)
                <p class="hint -mt-2">Template akan berisi kolom honor untuk: <b>{{ implode(', ', array_map(fn ($b) => Periode::label($b, false), $bl)) }}</b>.</p>
            @endif
            <div>
                <label class="label">Keterangan <span class="font-normal text-navy-400">(opsional)</span></label>
                <textarea wire:model="keterangan" rows="2" class="input"></textarea>
            </div>
        </form>
        <x-slot:footer>
            <button wire:click="$set('formTampil', false)" class="btn btn-ghost">Batal</button>
            <button type="submit" form="form-kegiatan" class="btn btn-blue" wire:loading.attr="disabled">{{ $editId ? 'Simpan' : 'Buat & pilih petugas' }}</button>
        </x-slot:footer>
    </x-modal>

    <x-modal :show="(bool) $hapusId" close="$set('hapusId', null)" title="Hapus kegiatan?">
        <p class="text-sm text-navy-700">Semua petugas dan honor pada kegiatan ini ikut terhapus, sehingga sisa limit mitra yang bersangkutan bertambah kembali.</p>
        <x-slot:footer>
            <button wire:click="$set('hapusId', null)" class="btn btn-ghost">Batal</button>
            <button wire:click="hapus" class="btn btn-danger">Hapus</button>
        </x-slot:footer>
    </x-modal>
</div>
