@props(['beban'])
@php $l = \App\Services\KinerjaService::BEBAN[$beban] ?? \App\Services\KinerjaService::BEBAN['normal']; @endphp
<span {{ $attributes->merge(['class' => 'chip '.$l['soft']]) }}>{{ $l['label'] }}</span>
