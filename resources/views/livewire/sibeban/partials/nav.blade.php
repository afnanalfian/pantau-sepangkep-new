@php
    $menu = [
        ['sibeban.monitoring', 'Monitoring honor', 'sibeban.monitoring', 'chart'],
        ['sibeban.kegiatan.index', 'Kegiatan', 'sibeban.kegiatan.*', 'calendar'],
        ['sibeban.mitra.index', 'Mitra', 'sibeban.mitra.*', 'users'],
    ];
    if (auth()->user()->is_admin) {
        $menu[] = ['sibeban.pengaturan', 'Pengaturan limit', 'sibeban.pengaturan', 'cog'];
    }
@endphp
<div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <p class="text-sm font-semibold text-bps-blue-700">SIBEBAN</p>
        <p class="text-xs text-navy-500">Monitoring honor & beban tugas mitra terhadap limit SBKS</p>
    </div>
    <nav class="-mx-1 flex gap-1 overflow-x-auto px-1">
        @foreach ($menu as [$rute, $label, $pola, $icon])
            <a href="{{ route($rute) }}" wire:navigate class="tab inline-flex shrink-0 items-center gap-1.5 {{ request()->routeIs($pola) ? 'active' : '' }}"><x-icon :name="$icon" class="size-4" /> {{ $label }}</a>
        @endforeach
    </nav>
</div>
