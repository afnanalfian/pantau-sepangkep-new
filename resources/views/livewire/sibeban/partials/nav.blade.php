@php
    $menu = [
        ['sibeban.monitoring', 'Ketersediaan mitra', 'sibeban.monitoring', 'chart'],
        ['sibeban.kegiatan.index', 'Kegiatan', 'sibeban.kegiatan.*', 'calendar'],
        ['sibeban.mitra.index', 'Database mitra', 'sibeban.mitra.*', 'users'],
    ];
    if (auth()->user()->is_admin) {
        $menu[] = ['sibeban.pengaturan', 'Limit honor', 'sibeban.pengaturan', 'cog'];
    }
@endphp
<div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <p class="text-sm font-bold text-bps-blue-700">SIBEBAN</p>
        <p class="text-xs text-navy-500">Monitoring honor & beban tugas mitra terhadap limit honor bulanan</p>
    </div>
    <nav class="-mx-1 flex gap-1 overflow-x-auto px-1">
        @foreach ($menu as [$rute, $label, $pola, $icon])
            <a href="{{ route($rute) }}" wire:navigate class="tab inline-flex shrink-0 items-center gap-1.5 {{ request()->routeIs($pola) ? 'active' : '' }}"><x-icon :name="$icon" class="size-4" /> {{ $label }}</a>
        @endforeach
    </nav>
</div>
