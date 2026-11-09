@props(['level'])
@php $l = \App\Services\WorkloadService::LEVELS[$level] ?? \App\Services\WorkloadService::LEVELS['normal']; @endphp
<span {{ $attributes->merge(['class' => 'chip '.$l['soft']]) }}>{{ $l['label'] }}</span>
