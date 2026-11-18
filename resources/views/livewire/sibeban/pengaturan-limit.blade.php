@php use App\Support\Sibeban\Format; @endphp
<div>
    @include('livewire.sibeban.partials.nav')
    @include('livewire.sibeban.partials.flash')

    <div class="max-w-2xl">
        <x-page-header title="Limit honor bulanan" subtitle="Batas total honor seorang mitra dalam satu bulan, dari semua kegiatan. Mitra dengan total di bawah limit masih bisa dipakai." />

        <form wire:submit="simpan" class="card space-y-5 p-5">
            <div>
                <label class="label">Limit honor per mitra per bulan</label>
                <div class="relative max-w-xs"><span class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-sm text-navy-400">Rp</span>
                    <input type="number" min="1" step="1" wire:model.live.debounce.500ms="limitBulanan" class="input !pl-10 text-lg font-bold tabular-nums"></div>
                <p class="hint">Tertulis: <b>{{ Format::rp((int) $limitBulanan) }}</b> per bulan.</p>
                @error('limitBulanan')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Tandai “mendekati limit” mulai</label>
                <div class="flex items-center gap-2"><input type="number" min="1" max="100" step="1" wire:model.live.debounce.500ms="ambangOranye" class="input !w-24 tabular-nums"><span class="text-sm text-navy-500">% dari limit</span></div>
                <p class="hint">Saat ini: honor di atas {{ Format::rp((int) round((int) $limitBulanan * (float) $ambangOranye / 100)) }} ditandai oranye.</p>
                @error('ambangOranye')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div class="flex justify-end border-t border-line pt-4"><button class="btn btn-blue" wire:loading.attr="disabled">Simpan</button></div>
        </form>
    </div>
</div>
