{{-- Cincin persentase. $pct null = tidak ada data --}}
@props(['pct' => null, 'color' => 'text-bps-blue', 'size' => 'size-20', 'stroke' => 9, 'label' => null])
@php
    $r = 42; $c = 2 * M_PI * $r;
    $v = $pct === null ? 0 : max(0, min(100, $pct));
@endphp
<div {{ $attributes->merge(['class' => "relative $size shrink-0"]) }}>
    <svg viewBox="0 0 100 100" class="size-full -rotate-90" aria-hidden="true">
        <circle cx="50" cy="50" r="{{ $r }}" fill="none" stroke="currentColor" stroke-width="{{ $stroke }}" class="text-navy-50" />
        <circle cx="50" cy="50" r="{{ $r }}" fill="none" stroke="currentColor" stroke-width="{{ $stroke }}" stroke-linecap="round"
                class="{{ $color }} transition-all duration-700" stroke-dasharray="{{ $c }}" stroke-dashoffset="{{ $c * (1 - $v / 100) }}" />
    </svg>
    <div class="absolute inset-0 grid place-items-center text-center leading-none">
        <div>
            <span class="text-[1.05em] font-extrabold">{{ $pct === null ? '–' : rtrim(rtrim(number_format($pct, 1, ',', '.'), '0'), ',').'%' }}</span>
            @if ($label)<span class="mt-0.5 block text-[0.62em] font-medium text-navy-500">{{ $label }}</span>@endif
        </div>
    </div>
</div>
