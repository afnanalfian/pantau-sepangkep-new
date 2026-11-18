<div>
    <div class="mb-6 max-w-2xl">
        <p class="text-sm font-semibold text-bps-green-700">SIKU</p>
        <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">Sistem Informasi Keuangan</h1>
        <p class="mt-1 text-navy-500">Arsip dan pemantauan keuangan BPS Kabupaten Pangkajene dan Kepulauan. Semua tautan dibuka di Google Drive / Spreadsheet.</p>
    </div>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($links as $l)
            <a href="{{ $l['url'] }}" target="_blank" rel="noopener" class="group card flex items-center gap-4 p-4 transition hover:-translate-y-0.5 hover:border-navy-300 sm:p-5">
                <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-bps-green-50 text-2xl">{{ $l['emoji'] }}</span>
                <span class="min-w-0 flex-1 font-bold">{{ $l['label'] }}</span>
                <x-icon name="external" class="size-4 text-navy-300 group-hover:text-navy-700" />
            </a>
        @endforeach
    </div>
</div>
