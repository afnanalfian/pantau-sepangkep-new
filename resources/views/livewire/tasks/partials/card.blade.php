@php $due = $t->effectiveDueDate(); $showAssignee = $scope !== 'mine'; @endphp
<div wire:key="card-{{ $t->id }}" wire:sort:item="{{ $t->id }}"
     class="group cursor-grab rounded-xl border border-line bg-white p-3.5 transition hover:border-navy-300 active:cursor-grabbing {{ $t->isOverdue() ? 'border-l-4 border-l-rose-500' : '' }}">
    <div class="flex items-start gap-2">
        <a href="{{ route('tasks.show', $t) }}" wire:navigate wire:sort:ignore class="min-w-0 flex-1 text-sm leading-snug font-semibold text-navy-900 hover:text-bps-blue-700">{{ $t->title }}</a>
        <span class="shrink-0 rounded-md bg-navy-50 px-1.5 py-0.5 text-[11px] font-bold text-navy-700" title="Poin">{{ poin($t->points) }}</span>
    </div>
    <p class="mt-1 text-[11px] text-navy-500">{{ $t->code }}{{ $t->team ? ' · '.$t->team->name : '' }}{{ $t->type === 'disposisi' ? ' · Disposisi' : '' }}</p>

    @if ($t->progress > 0 && $t->status !== 'selesai')
        <div class="slant-track mt-2.5 h-1.5"><div class="absolute inset-y-0 left-0 bg-bps-blue" style="width: {{ $t->progress }}%"></div></div>
    @endif

    <div class="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1.5">
        <x-priority :priority="$t->priority" />
        @if ($due)
            <span class="inline-flex items-center gap-1 text-[11px] font-medium whitespace-nowrap {{ $t->isOverdue() ? 'text-rose-600' : ($t->isDueSoon() ? 'text-bps-orange-700' : 'text-navy-500') }}">
                <x-icon name="clock" class="size-3.5" />{{ tgl($due, 'j M') }}
            </span>
        @endif
        <span class="ml-auto flex shrink-0 items-center gap-2 text-[11px] text-navy-300">
            @if ($t->attachments_count)<span class="inline-flex items-center gap-0.5"><x-icon name="clip" class="size-3.5" />{{ $t->attachments_count }}</span>@endif
            @if ($t->comments_count)<span class="inline-flex items-center gap-0.5"><x-icon name="chat" class="size-3.5" />{{ $t->comments_count }}</span>@endif
            @if ($t->children_count)<span class="inline-flex items-center gap-0.5" title="Sub-tugas"><x-icon name="forward" class="size-3.5" />{{ $t->children_count }}</span>@endif
            @if ($showAssignee)<x-avatar :user="$t->assignee" size="xs" />@endif
        </span>
    </div>
    @if ($t->status === 'todo' && $t->assignee_id === auth()->id())
        <button wire:sort:ignore wire:click="quickStart({{ $t->id }})" class="mt-3 hidden w-full items-center justify-center gap-1.5 rounded-lg bg-bps-blue-50 py-1.5 text-xs font-semibold text-bps-blue-700 group-hover:flex">
            <x-icon name="play" class="size-3.5" /> Mulai kerjakan
        </button>
    @endif
</div>
