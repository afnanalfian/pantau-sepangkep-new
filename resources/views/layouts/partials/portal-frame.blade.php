@php
    $tabs = [
        ['Beranda', 'portal.home', 'portal.home', 'home'],
        ['SICATUR', 'portal.sicatur', 'portal.sicatur*', 'mail'],
        ['SIKU', 'portal.siku', 'portal.siku', 'chart'],
        ['Kepegawaian', 'portal.kepegawaian', 'portal.kepegawaian', 'user'],
        ['SIBEBAN', 'sibeban.monitoring', 'sibeban.*', 'users'],
    ];
@endphp
<div class="-mx-4 -mt-5 mb-6 sm:-mx-6 sm:-mt-7">
    <div class="relative overflow-hidden bg-gradient-to-br from-navy-900 via-navy-800 to-bps-blue-700 px-4 pt-6 pb-4 text-white sm:px-6">
        <div class="pointer-events-none absolute -top-10 right-4 hidden rotate-[-14deg] gap-3 opacity-40 sm:flex" aria-hidden="true">
            <span class="slant h-44 w-10 bg-bps-blue"></span><span class="slant mt-10 h-44 w-10 bg-bps-green"></span><span class="slant -mt-4 h-44 w-10 bg-bps-orange"></span>
        </div>
        <div class="relative mx-auto max-w-[1400px]">
            <a href="{{ route('portal.home') }}" wire:navigate class="inline-flex items-center gap-3">
                <span class="grid size-11 place-items-center rounded-2xl bg-white p-1.5"><img src="/images/logo-bps-256.png" alt="" class="size-full object-contain"></span>
                <span class="leading-tight">
                    <span class="block text-lg font-extrabold tracking-tight">Portal Inovasi</span>
                    <span class="block text-xs text-white/70">BPS Kabupaten Pangkajene dan Kepulauan</span>
                </span>
            </a>
            <nav class="-mx-1 mt-5 flex gap-1 overflow-x-auto px-1 pb-1" aria-label="Menu portal">
                @foreach ($tabs as [$label, $route, $pattern, $icon])
                    <a href="{{ route($route) }}" wire:navigate
                       class="inline-flex shrink-0 items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-semibold transition {{ request()->routeIs($pattern) ? 'bg-white text-navy-900' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                        <x-icon :name="$icon" class="size-4" /> {{ $label }}
                    </a>
                @endforeach
            </nav>
        </div>
    </div>
</div>
{{ $slot }}
