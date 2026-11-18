{{-- @include('livewire.sibeban.partials.badge', ['status' => 'merah', 'persen' => 102.3]) --}}
<span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ \App\Services\Sibeban\HonorService::warna($status) }}">
    <span class="h-1.5 w-1.5 rounded-full {{ \App\Services\Sibeban\HonorService::titik($status) }}"></span>
    {{ \App\Services\Sibeban\HonorService::LABEL[$status] ?? $status }}@isset($persen) · {{ \App\Support\Sibeban\Format::persen($persen) }}@endisset
</span>
