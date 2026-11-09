@props(['activity'])
@php $a = \App\Services\WorkloadService::ACTIVITY[$activity] ?? \App\Services\WorkloadService::ACTIVITY['dorman']; @endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 text-xs text-navy-500']) }} title="Status keaktifan">
    <span class="relative flex size-2">
        @if ($activity === 'aktif')<span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-60"></span>@endif
        <span class="relative inline-flex size-2 rounded-full {{ $a['dot'] }}"></span>
    </span>{{ $a['label'] }}
</span>
