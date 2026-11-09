@props(['priority'])
@php $p = \App\Models\Task::PRIORITIES[$priority] ?? \App\Models\Task::PRIORITIES['sedang']; @endphp
<span {{ $attributes->merge(['class' => 'chip '.$p['class']]) }}>
    <x-icon name="flag" class="size-3" /> {{ $p['label'] }}
</span>
