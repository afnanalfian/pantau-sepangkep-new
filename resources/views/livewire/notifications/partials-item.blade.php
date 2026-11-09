@php
    $d = $n->data;
    $k = \App\Notifications\PantauNotification::KINDS[$d['kind'] ?? 'info'] ?? \App\Notifications\PantauNotification::KINDS['info'];
    $tone = ['blue' => 'bg-bps-blue-50 text-bps-blue-700', 'violet' => 'bg-violet-50 text-violet-600', 'amber' => 'bg-amber-50 text-amber-700', 'green' => 'bg-bps-green-50 text-bps-green-700',
             'orange' => 'bg-bps-orange-50 text-bps-orange-700', 'rose' => 'bg-rose-50 text-rose-600', 'slate' => 'bg-slate-100 text-slate-600'][$k['color']];
@endphp
<span class="grid size-9 shrink-0 place-items-center rounded-xl {{ $tone }}"><x-icon :name="$k['icon']" class="size-4.5" /></span>
<span class="min-w-0 flex-1">
    <span class="block text-sm {{ $n->read_at ? 'text-navy-700' : 'font-semibold text-navy-900' }}">{{ $d['title'] ?? '' }}</span>
    <span class="block truncate text-xs text-navy-500">{{ $d['body'] ?? '' }}</span>
    <span class="mt-0.5 block text-[11px] text-navy-300">{{ $n->created_at->diffForHumans() }}</span>
</span>
@unless ($n->read_at)<span class="mt-1.5 size-2 shrink-0 rounded-full bg-bps-orange"></span>@endunless
