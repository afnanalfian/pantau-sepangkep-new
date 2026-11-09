{{-- Bar poin bertingkat: hijau = terverifikasi, biru = berjalan; skala 0–max(target, total) --}}
@props(['verified' => 0, 'committed' => 0, 'target' => 100, 'height' => 'h-3', 'legend' => false])
@php
    $total = $verified + $committed;
    $scale = max($target, $total, 0.0001);
    $vw = min(100, $verified / $scale * 100);
    $cw = min(100 - $vw, $committed / $scale * 100);
    $tw = $target / $scale * 100;
@endphp
<div {{ $attributes }}>
    <div class="slant-track {{ $height }}">
        <div class="absolute inset-y-0 left-0 bg-bps-green" style="width: {{ $vw }}%"></div>
        <div class="absolute inset-y-0 bg-bps-blue/80" style="left: {{ $vw }}%; width: {{ $cw }}%; background-image: repeating-linear-gradient(-55deg, transparent 0 4px, rgba(255,255,255,.25) 4px 7px)"></div>
        @if ($tw < 99.5)
            <div class="absolute inset-y-0 w-0.5 bg-bps-orange" style="left: {{ $tw }}%" title="Target"></div>
        @endif
    </div>
    @if ($legend)
        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-navy-500">
            <span class="inline-flex items-center gap-1.5"><span class="slant h-2 w-4 bg-bps-green"></span>Terverifikasi {{ poin($verified) }}</span>
            <span class="inline-flex items-center gap-1.5"><span class="slant h-2 w-4 bg-bps-blue/80"></span>Berjalan {{ poin($committed) }}</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-3 w-0.5 bg-bps-orange"></span>Target {{ poin($target) }}</span>
        </div>
    @endif
</div>
