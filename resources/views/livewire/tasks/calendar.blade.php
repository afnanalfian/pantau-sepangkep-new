<div>
    <x-page-header title="Kalender tugas" subtitle="Deadline tugas per tanggal.">
        <x-slot:actions>
            @if (auth()->user()->canMonitor())
                <div class="flex rounded-xl bg-navy-50 p-1">
                    <button wire:click="$set('scope', 'mine')" class="tab {{ $scope === 'mine' ? 'active' : '' }}">Saya</button>
                    <button wire:click="$set('scope', 'all')" class="tab {{ $scope === 'all' ? 'active' : '' }}">Tim / kantor</button>
                </div>
            @endif
            <div class="flex items-center gap-1">
                <button wire:click="shift(-1)" class="btn btn-ghost px-2.5" aria-label="Bulan sebelumnya"><x-icon name="chevron-left" class="size-4" /></button>
                <span class="min-w-36 text-center text-sm font-bold">{{ bulan_tahun($start) }}</span>
                <button wire:click="shift(1)" class="btn btn-ghost px-2.5" aria-label="Bulan berikutnya"><x-icon name="chevron-right" class="size-4" /></button>
            </div>
        </x-slot:actions>
    </x-page-header>

    <div class="card overflow-hidden">
        <div class="grid grid-cols-7 border-b border-line bg-navy-50/60 text-center text-xs font-semibold text-navy-500">
            @foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $d)<div class="py-2.5">{{ $d }}</div>@endforeach
        </div>
        <div class="grid grid-cols-7">
            @foreach ($days as $day)
                @php $d = $day['date']; $in = $d->month === $start->month; @endphp
                <div class="min-h-20 border-r border-b border-line p-1.5 sm:min-h-28 sm:p-2 {{ $in ? '' : 'bg-paper/70' }} {{ $d->isWeekend() ? 'bg-navy-50/30' : '' }}">
                    <span class="inline-grid size-6 place-items-center rounded-full text-xs font-semibold {{ $d->isToday() ? 'bg-bps-orange text-white' : ($in ? 'text-navy-700' : 'text-navy-300') }}">{{ $d->day }}</span>
                    <div class="mt-1 space-y-1">
                        @foreach ($day['tasks']->take(3) as $t)
                            <a href="{{ route('tasks.show', $t) }}" wire:navigate title="{{ $t->title }} – {{ $t->assignee->display_name }}"
                               class="block truncate rounded-md px-1.5 py-0.5 text-[11px] font-medium
                               {{ $t->status === 'selesai' ? 'bg-bps-green-50 text-bps-green-700 line-through decoration-bps-green/50' : ($t->isOverdue() ? 'bg-rose-50 text-rose-700' : 'bg-bps-blue-50 text-bps-blue-700') }}">
                                <span class="hidden sm:inline">{{ $t->title }}</span><span class="sm:hidden">•</span>
                            </a>
                        @endforeach
                        @if ($day['tasks']->count() > 3)<p class="px-1 text-[10px] text-navy-500">+{{ $day['tasks']->count() - 3 }} lagi</p>@endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
