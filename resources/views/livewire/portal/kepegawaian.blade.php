<div>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="max-w-2xl">
            <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">Kepegawaian</h1>
            <p class="mt-1 text-navy-500">Biodata pegawai BPS Kabupaten Pangkajene dan Kepulauan.</p>
        </div>
        <a href="{{ $biodata }}" target="_blank" rel="noopener" class="btn btn-green"><x-icon name="external" class="size-4" /> Buka spreadsheet biodata pegawai</a>
    </div>

    <div class="mb-4 max-w-sm">
        <div class="relative">
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-navy-300" />
            <input wire:model.live.debounce.250ms="search" type="search" placeholder="Cari nama, NIP, jabatan" class="input py-2 pl-9">
        </div>
    </div>
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($pegawai as $p)
            <div class="card flex items-center gap-3 p-4" wire:key="kp-{{ $p->id }}">
                <x-avatar :user="$p" />
                <div class="min-w-0">
                    <p class="truncate font-semibold">{{ $p->display_name }}</p>
                    <p class="truncate text-xs text-navy-500">{{ $p->jabatan ?: '–' }}</p>
                    <p class="truncate text-xs text-navy-300">{{ $p->nip ? 'NIP '.$p->nip : '@'.$p->username }}{{ $p->teams->isNotEmpty() ? ' · '.$p->teams->pluck('name')->join(', ') : '' }}</p>
                </div>
            </div>
        @endforeach
    </div>
</div>
