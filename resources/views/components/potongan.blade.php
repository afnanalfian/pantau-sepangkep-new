@props(['value'])
@php
    $cls = $value <= 0 ? 'bg-bps-green-50 text-bps-green-700 ring-bps-green/40' : ($value < 2 ? 'bg-amber-50 text-amber-700 ring-amber-600/25' : 'bg-rose-50 text-rose-700 ring-rose-600/20');
@endphp
<span {{ $attributes->merge(['class' => 'chip '.$cls]) }}>{{ $value <= 0 ? 'Tidak dipotong' : 'Potong '.rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',').'%' }}</span>
