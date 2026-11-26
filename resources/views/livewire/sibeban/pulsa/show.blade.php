@php use App\Support\Sibeban\Format; @endphp
<div>
    @include('livewire.sibeban.partials.nav')
    @include('livewire.sibeban.partials.flash')

    <a href="{{ route('sibeban.pulsa.index') }}" wire:navigate class="mb-3 inline-flex items-center gap-1 text-sm font-semibold text-bps-blue-700"><x-icon name="chevron-left" class="size-4" /> Semua pulsa</a>

    <x-page-header :title="'Pulsa '.$pulsa->periode(false)" :subtitle="$pulsa->nama ?: 'Tanpa keterangan'">
        <x-slot:actions>
            <a href="{{ route('sibeban.pulsa.excel', $pulsa) }}" class="btn btn-ghost"><x-icon name="download" class="size-4" /> Unduh Excel</a>
            @if ($pulsa->bisaDiubah())
                <button wire:click="bukaUbah" class="btn btn-ghost"><x-icon name="pencil" class="size-4" /> Ubah</button>
                <button wire:click="bukaPilih" class="btn btn-blue"><x-icon name="plus" class="size-4" /> Tambah petugas</button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach ([['Periode', $pulsa->periode(false)], ['Per petugas', Format::rp($pulsa->jumlah)], ['Petugas', $totalPetugas], ['Total pulsa', Format::rp($pulsa->jumlah * $totalPetugas)]] as [$l, $v])
            <div class="card p-4"><p class="text-xs text-navy-500">{{ $l }}</p><p class="mt-1 font-bold tabular-nums">{{ $v }}</p></div>
        @endforeach
    </div>

    <div class="card overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-3">
            <p class="font-bold">Daftar penerima</p>
            <input type="search" wire:model.live.debounce.300ms="cari" placeholder="Cari nama / Sobat ID…" class="input !w-64">
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="table-head"><tr>
                    <th class="px-4 py-2.5">Petugas</th><th class="px-3 py-2.5">Wilayah</th><th class="px-3 py-2.5">Kegiatan di periode ini</th><th class="px-3 py-2.5 text-right">Pulsa</th>@if ($pulsa->bisaDiubah())<th class="px-4 py-2.5"></th>@endif
                </tr></thead>
                <tbody class="divide-y divide-line">
                    @forelse ($mitras as $m)
                        <tr wire:key="pm-{{ $m->id }}">
                            <td class="px-4 py-2.5"><a href="{{ route('sibeban.mitra.show', $m) }}" wire:navigate class="font-semibold hover:text-bps-blue-700">{{ $m->nama }}</a><span class="block text-xs text-navy-500">{{ $m->sobat_id }}</span></td>
                            <td class="px-3 py-2.5 text-navy-600">{{ $m->wilayah() ?: '–' }}</td>
                            <td class="px-3 py-2.5 text-xs text-navy-600">{{ implode(' · ', $kegiatan[$m->id] ?? []) ?: '–' }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ Format::rp($pulsa->jumlah) }}</td>
                            @if ($pulsa->bisaDiubah())
                                <td class="px-4 py-2.5 text-right"><button wire:click="$set('lepasId', {{ $m->id }})" class="btn-quiet rounded-lg p-1.5 hover:!text-rose-600" aria-label="Keluarkan"><x-icon name="x" class="size-4" /></button></td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty icon="users" title="Belum ada penerima" text="Klik “Tambah petugas”." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <p class="mt-3 text-xs text-navy-400">Dibuat {{ $pulsa->pembuat ?: '–' }} · {{ $pulsa->created_at->translatedFormat('j M Y') }}. Pulsa tidak menambah total honor dan tidak mengurangi sisa limit petugas.</p>

    <x-modal :show="$pilihTampil" close="$set('pilihTampil', false)" width="max-w-3xl" title="Tambah petugas" :subtitle="'Petugas yang punya kegiatan di '.$pulsa->periode(false).' dan belum menerima pulsa di periode itu.'">
        @if ($opsi)@include('livewire.sibeban.partials.pilih-pulsa')@endif
        <x-slot:footer>
            <span class="mr-auto text-sm text-navy-700"><b>{{ count($pDipilih) }}</b> petugas dicentang</span>
            <button wire:click="$set('pilihTampil', false)" class="btn btn-ghost">Batal</button>
            <button wire:click="simpanPilihan" class="btn btn-blue" @disabled(! $pDipilih) wire:loading.attr="disabled">Tambahkan</button>
        </x-slot:footer>
    </x-modal>

    <x-modal :show="$ubahTampil" close="$set('ubahTampil', false)" title="Ubah pulsa" subtitle="Periode tidak bisa diubah. Bila periodenya salah, hapus lalu buat pulsa baru.">
        <div class="space-y-3">
            <div><label class="label">Jumlah pulsa per petugas (Rp)</label><input wire:model="jumlah" inputmode="numeric" class="input @error('jumlah') input-error @enderror">@error('jumlah')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label">Keterangan</label><input wire:model="nama" maxlength="150" class="input"></div>
        </div>
        <x-slot:footer>
            <button wire:click="$set('ubahTampil', false)" class="btn btn-ghost">Batal</button>
            <button wire:click="simpanUbah" class="btn btn-blue">Simpan</button>
        </x-slot:footer>
    </x-modal>

    <x-modal :show="(bool) $lepasId" close="$set('lepasId', null)" title="Keluarkan petugas?">
        <p class="text-sm text-navy-700">Petugas ini tidak lagi menerima pulsa ini, dan bisa diberi pulsa lain di periode yang sama.</p>
        <x-slot:footer>
            <button wire:click="$set('lepasId', null)" class="btn btn-ghost">Batal</button>
            <button wire:click="lepas" class="btn btn-danger">Keluarkan</button>
        </x-slot:footer>
    </x-modal>
</div>
