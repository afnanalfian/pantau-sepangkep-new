@php use App\Support\Sibeban\Format; @endphp
<div>
    @include('livewire.sibeban.partials.nav')
    @include('livewire.sibeban.partials.flash')

    <x-page-header title="Pulsa petugas" subtitle="Atur pulsa untuk petugas yang punya kegiatan di suatu periode. Satu petugas hanya menerima satu pulsa per periode. Pulsa tidak dihitung sebagai honor.">
        <x-slot:actions>
            <button wire:click="tambah" class="btn btn-blue"><x-icon name="plus" class="size-4" /> Pulsa baru</button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <div class="relative w-full sm:w-72">
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-navy-300" />
            <input type="search" wire:model.live.debounce.400ms="cari" placeholder="Cari keterangan atau pembuat…" class="input !pl-9">
        </div>
        <select wire:model.live="tahun" class="input !w-auto">
            <option value="">Semua tahun</option>
            @foreach (range(now()->year + 1, now()->year - 3) as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
        </select>
    </div>

    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($pulsas as $p)
            <div class="card group relative flex flex-col p-4 transition hover:border-navy-300 hover:shadow-md" wire:key="p-{{ $p->id }}">
                <div class="flex items-start justify-between gap-2">
                    <a href="{{ route('sibeban.pulsa.show', $p) }}" wire:navigate class="min-w-0 after:absolute after:inset-0">
                        <p class="font-bold leading-snug text-navy-900 group-hover:text-bps-blue-700">Pulsa {{ $p->periode(false) }}</p>
                        <p class="mt-0.5 truncate text-xs text-navy-500">{{ $p->nama ?: 'Tanpa keterangan' }}</p>
                    </a>
                    @if ($p->bisaDiubah())
                        <button wire:click="$set('hapusId', {{ $p->id }})" class="btn-quiet relative z-10 rounded-lg p-1.5 hover:!text-rose-600" aria-label="Hapus"><x-icon name="trash" class="size-4" /></button>
                    @endif
                </div>
                <div class="mt-4 grid grid-cols-3 gap-2 border-t border-line pt-3 text-xs">
                    <div><p class="text-navy-500">Petugas</p><p class="mt-0.5 text-base font-bold tabular-nums">{{ $p->mitras_count }}</p></div>
                    <div><p class="text-navy-500">Per petugas</p><p class="mt-0.5 font-bold tabular-nums">{{ Format::rp($p->jumlah) }}</p></div>
                    <div><p class="text-navy-500">Total</p><p class="mt-0.5 font-bold tabular-nums">{{ Format::rp($p->jumlah * $p->mitras_count) }}</p></div>
                </div>
                <p class="mt-3 text-[11px] text-navy-400">Dibuat {{ $p->pembuat ?: '–' }}</p>
            </div>
        @empty
            <div class="card col-span-full"><x-empty icon="phone" title="Belum ada pulsa" text="Klik “Pulsa baru” untuk mulai." /></div>
        @endforelse
    </div>
    <div class="mt-4">{{ $pulsas->links() }}</div>

    <x-modal :show="$formTampil" close="$set('formTampil', false)" width="max-w-3xl" title="Pulsa baru" subtitle="Yang muncul hanya petugas yang punya kegiatan di periode ini dan belum menerima pulsa di periode yang sama.">
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="label">Bulan mulai</label>
                <input type="month" wire:model.live="bulan_mulai" class="input @error('bulan_mulai') input-error @enderror">
                @error('bulan_mulai')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Bulan selesai</label>
                <input type="month" wire:model.live="bulan_selesai" min="{{ $bulan_mulai }}" class="input @error('bulan_selesai') input-error @enderror">
                @error('bulan_selesai')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Jumlah pulsa per petugas (Rp)</label>
                <input wire:model.blur="jumlah" inputmode="numeric" class="input @error('jumlah') input-error @enderror" placeholder="mis. 50000">
                @error('jumlah')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Keterangan <span class="font-normal text-navy-400">(opsional)</span></label>
                <input wire:model="nama" maxlength="150" class="input" placeholder="mis. Pulsa Susenas Maret">
            </div>
        </div>

        <div class="mt-5 border-t border-line pt-4">
            <p class="label">Petugas</p>
            @if ($opsi && \App\Services\Sibeban\PulsaService::bulanValid($bulan_mulai) && \App\Services\Sibeban\PulsaService::bulanValid($bulan_selesai) && $bulan_selesai >= $bulan_mulai)
                @include('livewire.sibeban.partials.pilih-pulsa')
            @else
                <p class="text-sm text-navy-500">Pilih bulan mulai & selesai dulu.</p>
            @endif
        </div>
        <x-slot:footer>
            @php $nominal = (int) preg_replace('/\D/', '', $jumlah); @endphp
            <span class="mr-auto text-sm text-navy-700"><b>{{ count($pDipilih) }}</b> petugas dicentang{{ $nominal && $pDipilih ? ' · total '.Format::rp($nominal * count($pDipilih)) : '' }}</span>
            <button wire:click="$set('formTampil', false)" class="btn btn-ghost">Batal</button>
            <button wire:click="simpan" class="btn btn-blue" @disabled(! $pDipilih) wire:loading.attr="disabled">Simpan pulsa</button>
        </x-slot:footer>
    </x-modal>

    <x-modal :show="(bool) $hapusId" close="$set('hapusId', null)" title="Hapus pengaturan pulsa?">
        <p class="text-sm text-navy-700">Daftar penerima pulsa ini ikut terhapus, dan petugasnya bisa diberi pulsa lagi di periode tersebut.</p>
        <x-slot:footer>
            <button wire:click="$set('hapusId', null)" class="btn btn-ghost">Batal</button>
            <button wire:click="hapus" class="btn btn-danger">Hapus</button>
        </x-slot:footer>
    </x-modal>
</div>
