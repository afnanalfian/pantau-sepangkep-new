<div>
    <x-page-header title="Tim kerja" subtitle="Susunan tim dan beban kerja anggota bulan ini." />
    <div class="grid gap-5 md:grid-cols-2 2xl:grid-cols-3">
        @foreach ($teams as $team)
            <section class="card overflow-hidden" wire:key="team-{{ $team->id }}">
                <div class="h-1.5 {{ $team->color() }}"></div>
                <header class="px-5 pt-4 pb-3">
                    <h2 class="font-bold">{{ $team->name }}</h2>
                    @if ($team->description)<p class="mt-0.5 text-xs text-navy-500">{{ $team->description }}</p>@endif
                </header>
                <ul class="divide-y divide-line border-t border-line">
                    @forelse ($team->members->sortByDesc(fn ($m) => $m->pivot->role === 'ketua') as $m)
                        @php $s = $summary[$m->id] ?? null; @endphp
                        <li class="flex items-center gap-3 px-5 py-2.5">
                            <x-avatar :user="$m" size="sm" />
                            <div class="min-w-0 flex-1">
                                <p class="flex items-center gap-1.5 truncate text-sm font-medium">{{ $m->display_name }}
                                    @if ($m->pivot->role === 'ketua')<span class="chip bg-bps-orange-50 text-bps-orange-700 ring-bps-orange/30">Ketua</span>@endif</p>
                                @if ($s)<x-points-bar class="mt-1.5" height="h-1.5" :verified="$s['verified']" :committed="$s['committed']" :target="$s['target']" />@endif
                            </div>
                            @if ($s)<span class="w-10 text-right text-xs font-semibold text-navy-700">{{ round($s['load_pct']) }}%</span>@endif
                        </li>
                    @empty
                        <li class="px-5 py-4 text-sm text-navy-300">Belum ada anggota.</li>
                    @endforelse
                </ul>
            </section>
        @endforeach
    </div>
</div>
