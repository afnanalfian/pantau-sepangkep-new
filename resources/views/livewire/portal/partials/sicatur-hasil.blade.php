{{-- Satu hasil pencarian Riwayat ($s) --}}
<li class="grid gap-x-4 gap-y-1 border-t border-line py-3.5 sm:grid-cols-[minmax(0,1fr)_auto]" wire:key="h-{{ $s->id }}" x-data="{ t: 'Salin' }">
    <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1">
        <span class="text-base font-medium tabular-nums break-all">{{ $s->nomorTampil() }}</span>
        <span class="rounded-md bg-navy-50 px-2 py-0.5 text-xs font-medium text-navy-500">{{ \App\Models\Surat::labelJenis($s->jenis) }}</span>
    </div>
    <div class="flex flex-wrap justify-start gap-2 sm:row-span-3 sm:justify-end sm:self-start">
        @if ($s->lengkap && $s->nomor_surat)
            <button class="btn btn-sm bg-bps-blue-50 text-bps-blue-700 hover:bg-bps-blue-100" @click="navigator.clipboard.writeText(@js($s->nomor_surat)); t = 'Tersalin'; setTimeout(() => t = 'Salin', 1800)"><x-icon name="clip" class="size-3.5" /> <span x-text="t"></span></button>
        @endif
        @if (\App\Models\Surat::cfg($s->jenis) && $bolehHapus($s))
            <button wire:click="bukaHapus({{ $s->id }})" class="btn btn-sm bg-white text-rose-700 ring-[1.5px] ring-rose-300 ring-inset hover:bg-rose-50"><x-icon name="trash" class="size-3.5" /> Hapus</button>
        @endif
    </div>
    <div class="text-sm text-navy-500">{{ collect([$s->tanggal?->translatedFormat('j M Y'), $s->pembuat])->filter()->implode(' · ') }}</div>
    @include('livewire.portal.partials.sicatur-detail', ['s' => $s])
</li>
