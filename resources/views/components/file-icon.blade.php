@props(['type'])
@php
$m = [
    'pdf' => ['bg-rose-50 text-rose-600', 'PDF'], 'word' => ['bg-blue-50 text-blue-700', 'DOC'], 'excel' => ['bg-emerald-50 text-emerald-700', 'XLS'],
    'ppt' => ['bg-orange-50 text-orange-600', 'PPT'], 'zip' => ['bg-amber-50 text-amber-700', 'ZIP'], 'image' => ['bg-violet-50 text-violet-600', 'IMG'],
    'link' => ['bg-bps-blue-50 text-bps-blue-700', null], 'file' => ['bg-slate-100 text-slate-600', 'FILE'],
];
[$cls, $lbl] = $m[$type] ?? $m['file'];
@endphp
<span {{ $attributes->merge(['class' => "grid size-10 shrink-0 place-items-center rounded-xl text-[10px] font-extrabold $cls"]) }}>
    @if ($lbl) {{ $lbl }} @else <x-icon name="link" class="size-5" /> @endif
</span>
