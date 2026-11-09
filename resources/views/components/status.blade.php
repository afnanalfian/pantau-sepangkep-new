@props(['status'])
@php
$map = [
    'pengajuan' => 'bg-violet-50 text-violet-700 ring-violet-600/20',
    'todo' => 'bg-slate-100 text-slate-700 ring-slate-500/20',
    'in_progress' => 'bg-bps-blue-50 text-bps-blue-700 ring-bps-blue/30',
    'revisi' => 'bg-bps-orange-50 text-bps-orange-700 ring-bps-orange/30',
    'review' => 'bg-amber-50 text-amber-700 ring-amber-600/25',
    'selesai' => 'bg-bps-green-50 text-bps-green-700 ring-bps-green/40',
    'ditolak' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
    'batal' => 'bg-slate-50 text-slate-500 ring-slate-400/20',
];
$dot = [
    'pengajuan' => 'bg-violet-500', 'todo' => 'bg-slate-400', 'in_progress' => 'bg-bps-blue', 'revisi' => 'bg-bps-orange',
    'review' => 'bg-amber-500', 'selesai' => 'bg-bps-green', 'ditolak' => 'bg-rose-500', 'batal' => 'bg-slate-300',
];
@endphp
<span {{ $attributes->merge(['class' => 'chip '.($map[$status] ?? $map['todo'])]) }}>
    <span class="size-1.5 rounded-full {{ $dot[$status] ?? 'bg-slate-400' }}"></span>
    {{ \App\Models\Task::STATUSES[$status]['label'] ?? $status }}
</span>
