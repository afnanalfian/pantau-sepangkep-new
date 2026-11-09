@props(['show' => false, 'close' => null, 'title' => null, 'width' => 'max-w-lg', 'subtitle' => null])
@if ($show)
<div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4" role="dialog" aria-modal="true"
     x-data
     x-on:keydown.escape.window="$el.querySelector('[data-close]')?.click()">
    <div class="absolute inset-0 bg-navy-950/50 backdrop-blur-[2px]" @if($close) data-close wire:click="{{ $close }}" @endif></div>
    <div {{ $attributes->merge(['class' => "relative flex max-h-[92vh] w-full $width flex-col rounded-t-3xl bg-white shadow-2xl animate-slide-up sm:rounded-3xl sm:animate-pop"]) }}>
        @if ($title)
            <div class="flex items-start justify-between gap-4 border-b border-line px-5 py-4 sm:px-6">
                <div>
                    <h2 class="text-base font-bold text-navy-900">{{ $title }}</h2>
                    @if ($subtitle)<p class="mt-0.5 text-sm text-navy-500">{{ $subtitle }}</p>@endif
                </div>
                @if ($close)
                    <button type="button" wire:click="{{ $close }}" class="btn-quiet -mr-2 rounded-lg p-1.5" aria-label="Tutup"><x-icon name="x" /></button>
                @endif
            </div>
        @endif
        <div class="overflow-y-auto px-5 py-5 sm:px-6">{{ $slot }}</div>
        @isset($footer)
            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-line bg-paper/60 px-5 py-3.5 sm:rounded-b-3xl sm:px-6">{{ $footer }}</div>
        @endisset
    </div>
</div>
@endif
