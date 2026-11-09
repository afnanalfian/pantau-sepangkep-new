@props(['user', 'size' => 'md', 'ring' => false])
@php
$s = ['xs' => 'size-6 text-[10px]', 'sm' => 'size-8 text-xs', 'md' => 'size-10 text-sm', 'lg' => 'size-14 text-lg', 'xl' => 'size-20 text-2xl'][$size] ?? 'size-10 text-sm';
@endphp
@if ($user?->avatar_url)
    <img src="{{ $user->avatar_url }}" alt="{{ $user->display_name }}" title="{{ $user->display_name }}" {{ $attributes->merge(['class' => "$s shrink-0 rounded-full object-cover".($ring ? ' ring-2 ring-white' : '')]) }}>
@else
    <span title="{{ $user?->display_name }}" {{ $attributes->merge(['class' => "$s ".($user?->avatar_color ?? 'bg-navy-300')." inline-flex shrink-0 items-center justify-center rounded-full font-bold text-white".($ring ? ' ring-2 ring-white' : '')]) }}>{{ $user?->initials ?? '?' }}</span>
@endif
