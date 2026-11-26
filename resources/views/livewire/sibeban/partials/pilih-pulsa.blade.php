{{-- Daftar centang petugas pulsa. Butuh: $kandidat, $jumlahKandidat, $opsi, $pDipilih, $pKecamatan, $pBatas --}}
<div class="mb-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
    <input type="search" wire:model.live.debounce.300ms="pCari" placeholder="Cari nama / Sobat ID…" class="input col-span-2 sm:col-span-1">
    <select wire:model.live="pKecamatan" class="input">
        <option value="">Semua kecamatan</option>
        @foreach ($opsi['kecamatan'] as $k)<option value="{{ $k }}">{{ $k }}</option>@endforeach
    </select>
    <select wire:model.live="pDesa" class="input" @disabled($pKecamatan === '')>
        <option value="">{{ $pKecamatan === '' ? 'Desa/kel. (pilih kec.)' : 'Semua desa/kelurahan' }}</option>
        @foreach ($opsi['desa'] as $d)<option value="{{ $d }}">{{ $d }}</option>@endforeach
    </select>
</div>
<div class="mb-2 flex flex-wrap items-center justify-between gap-2 text-sm">
    <span class="text-navy-500">{{ $jumlahKandidat }} petugas bisa dipilih</span>
    <div class="flex items-center gap-2">
        @if ($jumlahKandidat)<button type="button" wire:click="pilihSemua" class="btn btn-ghost btn-sm">Centang semua ({{ $jumlahKandidat }})</button>@endif
        @if ($pDipilih)<button type="button" wire:click="kosongkanPilihan" class="btn btn-quiet btn-sm">Kosongkan</button>@endif
    </div>
</div>
<div class="max-h-[46vh] divide-y divide-line overflow-y-auto rounded-xl border border-line">
    @forelse ($kandidat as $k)
        @php $m = $k['mitra']; @endphp
        <label wire:key="pp-{{ $m->id }}" class="flex cursor-pointer items-start gap-3 px-3 py-2.5 hover:bg-navy-50/50 has-[:checked]:bg-bps-blue-50/60">
            <input type="checkbox" value="{{ $m->id }}" wire:model.live="pDipilih" class="mt-0.5 size-4 rounded border-line text-bps-blue">
            <span class="min-w-0 flex-1">
                <span class="block truncate font-semibold">{{ $m->nama }}</span>
                <span class="block truncate text-xs text-navy-500">{{ $m->sobat_id }} · {{ $m->wilayah() ?: 'wilayah belum diisi' }}</span>
                <span class="block truncate text-xs text-bps-blue-700" title="{{ implode(', ', $k['kegiatan']) }}">{{ implode(' · ', $k['kegiatan']) }}</span>
            </span>
        </label>
    @empty
        <x-empty icon="users" title="Tidak ada petugas" text="Belum ada petugas yang punya kegiatan di periode ini, atau semuanya sudah menerima pulsa." />
    @endforelse
</div>
@if ($jumlahKandidat > count($kandidat))
    <div class="mt-3 text-center"><button type="button" wire:click="$set('pBatas', {{ $pBatas + 100 }})" class="btn btn-ghost btn-sm">Tampilkan lebih banyak ({{ $jumlahKandidat - count($kandidat) }} lagi)</button></div>
@endif
@error('pDipilih')<p class="error mt-2">{{ $message }}</p>@enderror
