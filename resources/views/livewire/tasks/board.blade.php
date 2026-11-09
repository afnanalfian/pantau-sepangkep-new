@php
    $u = auth()->user();
    $scopeLabels = ['mine' => 'Tugas saya', 'created' => 'Yang saya berikan', 'team' => 'Tim saya', 'all' => 'Semua'];
    $colStyle = [
        'todo' => ['Belum dikerjakan', 'bg-slate-400'],
        'in_progress' => ['Sedang dikerjakan', 'bg-bps-blue'],
        'revisi' => ['Perlu revisi', 'bg-bps-orange'],
        'review' => ['Menunggu verifikasi', 'bg-amber-500'],
        'selesai' => ['Selesai', 'bg-bps-green'],
    ];
@endphp
<div>
    <x-page-header title="Tugas" subtitle="Seret kartu antar kolom untuk mengubah status. Kolom “Menunggu verifikasi” mewajibkan output terlampir.">
        <x-slot:actions>
            <div class="flex rounded-xl bg-navy-50 p-1">
                <button wire:click="$set('view', 'board')" class="tab {{ $view === 'board' ? 'active' : '' }} inline-flex items-center gap-1.5"><x-icon name="board" class="size-4" /> Papan</button>
                <button wire:click="$set('view', 'list')" class="tab {{ $view === 'list' ? 'active' : '' }} inline-flex items-center gap-1.5"><x-icon name="list" class="size-4" /> Daftar</button>
            </div>
            <button class="btn btn-blue" @click="$dispatch('open-task-form', { mode: '{{ $u->canAssign() ? 'assign' : 'mandiri' }}' })">
                <x-icon name="plus" class="size-4" /> {{ $u->canAssign() ? 'Beri tugas' : 'Ajukan tugas' }}
            </button>
        </x-slot:actions>
    </x-page-header>

    {{-- Filter --}}
    <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-center">
        <div class="-mx-1 flex gap-1 overflow-x-auto px-1">
            @foreach ($this->scopes() as $s)
                <button wire:click="$set('scope', '{{ $s }}')" class="tab shrink-0 {{ $scope === $s ? 'active' : '' }}">{{ $scopeLabels[$s] }}</button>
            @endforeach
        </div>
        <div class="flex flex-1 flex-wrap gap-2 lg:justify-end">
            <div class="relative min-w-48 flex-1 lg:max-w-xs">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-navy-300" />
                <input wire:model.live.debounce.350ms="search" type="search" placeholder="Cari judul / kode" class="input py-2 pl-9">
            </div>
            <select wire:model.live="priority" class="input w-auto py-2">
                <option value="">Semua prioritas</option>
                @foreach (\App\Models\Task::PRIORITIES as $k => $p)<option value="{{ $k }}">{{ $p['label'] }}</option>@endforeach
            </select>
            <select wire:model.live="team" class="input w-auto py-2">
                <option value="">Semua tim</option>
                @foreach ($this->teams as $tm)<option value="{{ $tm->id }}">{{ $tm->name }}</option>@endforeach
            </select>
            @if ($view === 'list')
                <select wire:model.live="status" class="input w-auto py-2">
                    <option value="">Semua status</option>
                    @foreach (\App\Models\Task::STATUSES as $k => $s)<option value="{{ $k }}">{{ $s['label'] }}</option>@endforeach
                </select>
            @endif
        </div>
    </div>

    @if ($view === 'board')
        {{-- Pengajuan menunggu persetujuan --}}
        @if ($this->pendingSubmissions->isNotEmpty())
            <div class="mb-5 rounded-2xl border border-violet-200 bg-violet-50/60 p-4">
                <p class="text-sm font-semibold text-violet-900">Pengajuan mandiri menunggu persetujuan ({{ $this->pendingSubmissions->count() }})</p>
                <div class="mt-3 flex gap-3 overflow-x-auto pb-1">
                    @foreach ($this->pendingSubmissions as $t)
                        <a href="{{ route('tasks.show', $t) }}" wire:navigate class="w-64 shrink-0 rounded-xl border border-violet-200 bg-white p-3 hover:border-violet-400">
                            <p class="truncate text-sm font-semibold">{{ $t->title }}</p>
                            <p class="mt-1 text-xs text-navy-500">{{ $t->assignee->display_name }} · {{ poin($t->points) }} poin</p>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="-mx-4 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-4 sm:-mx-6 sm:px-6 xl:mx-0 xl:grid xl:grid-cols-5 xl:overflow-visible xl:px-0">
            @foreach ($this->columns as $status => $tasks)
                <section class="flex w-[82vw] max-w-xs shrink-0 snap-start flex-col rounded-2xl bg-navy-50/70 sm:w-72 xl:w-auto xl:max-w-none" wire:key="col-{{ $status }}">
                    <header class="px-3.5 pt-3.5 pb-2.5">
                        <div class="flex items-center gap-2">
                            <span class="slant h-3 w-5 shrink-0 {{ $colStyle[$status][1] }}"></span>
                            <h2 class="truncate text-sm font-bold">{{ $colStyle[$status][0] }}</h2>
                            <span class="ml-auto rounded-full bg-white px-2 text-xs font-semibold text-navy-500">{{ $tasks->count() }}</span>
                        </div>
                        <p class="mt-0.5 pl-7 text-[11px] text-navy-500">{{ poin($tasks->sum('points')) }} poin</p>
                    </header>
                    <div class="flex min-h-40 flex-1 flex-col gap-2.5 px-2.5 pb-3"
                         wire:sort="handleSort" wire:sort:group="tasks" wire:sort:group-id="{{ $status }}">
                        @foreach ($tasks as $t)
                            @include('livewire.tasks.partials.card', ['t' => $t, 'scope' => $scope])
                        @endforeach
                        @if ($tasks->isEmpty())
                            <div wire:sort:ignore class="grid flex-1 place-items-center rounded-xl border-2 border-dashed border-navy-100 p-6 text-center text-xs text-navy-300">Seret kartu ke sini</div>
                        @endif
                    </div>
                </section>
            @endforeach
        </div>
    @else
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-sm">
                    <thead class="table-head">
                        <tr>
                            <th class="px-5 py-3">Tugas</th>
                            <th class="px-3 py-3">Pelaksana</th>
                            <th class="px-3 py-3">Status</th>
                            <th class="px-3 py-3">Prioritas</th>
                            <th class="px-3 py-3">Deadline</th>
                            <th class="px-5 py-3 text-right">Poin</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($list as $t)
                            <tr class="hover:bg-paper/70" wire:key="row-{{ $t->id }}">
                                <td class="px-5 py-3">
                                    <a href="{{ route('tasks.show', $t) }}" wire:navigate class="font-semibold hover:text-bps-blue-700">{{ $t->title }}</a>
                                    <p class="text-xs text-navy-500">{{ $t->code }}{{ $t->team ? ' · '.$t->team->name : '' }}</p>
                                </td>
                                <td class="px-3 py-3"><span class="flex items-center gap-2"><x-avatar :user="$t->assignee" size="xs" /><span class="truncate">{{ $t->assignee->display_name }}</span></span></td>
                                <td class="px-3 py-3"><x-status :status="$t->status" /></td>
                                <td class="px-3 py-3"><x-priority :priority="$t->priority" /></td>
                                <td class="px-3 py-3 text-xs {{ $t->isOverdue() ? 'font-semibold text-rose-600' : 'text-navy-500' }}">{{ $t->effectiveDueDate() ? tgl($t->effectiveDueDate()) : '–' }}</td>
                                <td class="px-5 py-3 text-right font-bold">{{ poin($t->points) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-empty icon="clipboard" title="Tidak ada tugas" text="Coba ubah filter atau kata kunci pencarian." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($list->hasPages())<div class="border-t border-line px-5 py-3">{{ $list->links() }}</div>@endif
        </div>
    @endif
</div>
