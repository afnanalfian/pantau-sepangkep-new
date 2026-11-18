{{-- Bar penyelesaian: hijau selesai · biru masih berjalan · merah lewat deadline --}}
@props(['selesai' => 0, 'berjalan' => 0, 'gagal' => 0, 'height' => 'h-2.5'])
@php $tot = max($selesai + $berjalan + $gagal, 1); @endphp
<div {{ $attributes->merge(['class' => "slant-track $height flex"]) }} title="{{ $selesai }} selesai · {{ $berjalan }} berjalan · {{ $gagal }} lewat deadline">
    @if ($selesai)<div class="h-full bg-bps-green" style="width: {{ $selesai / $tot * 100 }}%"></div>@endif
    @if ($berjalan)<div class="h-full bg-bps-blue/70" style="width: {{ $berjalan / $tot * 100 }}%"></div>@endif
    @if ($gagal)<div class="h-full bg-rose-400" style="width: {{ $gagal / $tot * 100 }}%"></div>@endif
</div>
