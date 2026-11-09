@props(['icon' => 'inbox', 'title', 'text' => null])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-10 text-center']) }}>
    <div class="mb-3 grid size-12 place-items-center rounded-2xl bg-navy-50 text-navy-300"><x-icon :name="$icon" class="size-6" /></div>
    <p class="text-sm font-semibold text-navy-900">{{ $title }}</p>
    @if ($text)<p class="mt-1 max-w-sm text-sm text-navy-500">{{ $text }}</p>@endif
    @if ($slot->isNotEmpty())<div class="mt-4">{{ $slot }}</div>@endif
</div>
