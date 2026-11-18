@props(['value', 'num' => null, 'den' => null])
@if ($value === null)
    <span {{ $attributes->merge(['class' => 'text-navy-300']) }} title="Tidak ada data bulan ini">–</span>
@else
    <span {{ $attributes->merge(['class' => 'font-semibold '.($value >= 100 ? 'text-bps-green-700' : ($value <= 80 ? 'text-rose-600' : 'text-amber-700'))]) }}>{{ rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',') }}%</span>
    @if ($den !== null)<span class="block text-[11px] font-normal text-navy-500">{{ $num }}/{{ $den }}</span>@endif
@endif
