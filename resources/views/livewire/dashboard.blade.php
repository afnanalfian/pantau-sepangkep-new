@php
    $u = auth()->user();
    $me = $this->me;
    $c = $this->counts;
    $hour = now()->hour;
    $greet = $hour < 11 ? 'Selamat pagi' : ($hour < 15 ? 'Selamat siang' : ($hour < 18 ? 'Selamat sore' : 'Selamat malam'));
    $trend = $this->trend;
    $maxTrend = max(max($trend), 1);
@endphp
<div class="space-y-6">
    {{-- ========== Kepala: capaian pribadi ========== --}}
    <section class="card overflow-hidden">
        <div class="grid gap-6 p-5 sm:p-7 lg:grid-cols-[1.25fr_1fr]">
            <div>
                <p class="text-sm text-navy-500">{{ $greet }}, {{ $u->first_name }} · {{ tgl(now(), 'l, j F Y') }}</p>
                <h1 class="mt-1 text-2xl font-extrabold tracking-tight sm:text-3xl">Capaian {{ bulan_tahun() }}</h1>

                <div class="mt-6 flex items-end gap-3">
                    <p class="text-5xl leading-none font-extrabold tracking-tight sm:text-6xl">{{ poin($me['verified']) }}</p>
                    <p class="pb-1 text-sm text-navy-500">dari target <b class="text-navy-900">{{ poin($me['target']) }}</b> poin terverifikasi
                        @if ($me['leave_days'])<br><span class="text-xs">(prorata {{ $me['leave_days'] }} hari cuti/DL)</span>@endif</p>
                </div>
                <x-points-bar class="mt-5" height="h-4" :verified="$me['verified']" :committed="$me['committed']" :target="$me['target']" legend />

                <div class="mt-5 flex flex-wrap gap-2 text-xs">
                    <span class="chip bg-navy-50 text-navy-700 ring-line">Proyeksi akhir bulan {{ $me['projection_pct'] }}%</span>
                    <x-level :level="$me['level']" />
                    @if ($me['frozen'] > 0)
                        <span class="chip bg-rose-50 text-rose-700 ring-rose-600/20">{{ poin($me['frozen']) }} poin dibekukan (terlambat)</span>
                    @endif
                    @if ($me['pending'] > 0)
                        <span class="chip bg-violet-50 text-violet-700 ring-violet-600/20">{{ poin($me['pending']) }} poin menunggu persetujuan</span>
                    @endif
                </div>
            </div>

            {{-- Ringkasan status tugas --}}
            <div class="grid grid-cols-2 gap-3 self-start">
                @foreach ([
                    ['Belum dikerjakan', $c['todo'], 'todo', 'bg-slate-400'],
                    ['Sedang dikerjakan', $c['in_progress'], 'in_progress', 'bg-bps-blue'],
                    ['Menunggu verifikasi', $c['review'], 'review', 'bg-amber-500'],
                    ['Selesai bulan ini', $c['done'], 'selesai', 'bg-bps-green'],
                ] as [$label, $val, $st, $dot])
                    <a href="{{ route('tasks.index', ['view' => 'list', 'status' => $st]) }}" wire:navigate class="rounded-2xl border border-line p-4 transition hover:border-navy-300">
                        <span class="flex items-center gap-2 text-xs text-navy-500"><span class="size-2 rounded-full {{ $dot }}"></span>{{ $label }}</span>
                        <span class="mt-2 block text-3xl font-extrabold">{{ $val }}</span>
                    </a>
                @endforeach
            </div>
        </div>

        @if ($c['overdue'] || $c['to_verify'])
            <div class="flex flex-col divide-y divide-line border-t border-line bg-paper/60 text-sm sm:flex-row sm:divide-x sm:divide-y-0">
                @if ($c['overdue'])
                    <a href="{{ route('tasks.index', ['view' => 'list']) }}" wire:navigate class="flex flex-1 items-center gap-3 px-5 py-3.5 text-rose-700 hover:bg-rose-50">
                        <x-icon name="alert" class="size-5" /> {{ $c['overdue'] }} tugas Anda melewati deadline — selesaikan atau ajukan perpanjangan.
                    </a>
                @endif
                @if ($c['to_verify'])
                    <a href="{{ route('approvals') }}" wire:navigate class="flex flex-1 items-center gap-3 px-5 py-3.5 text-amber-800 hover:bg-amber-50">
                        <x-icon name="check-circle" class="size-5" /> {{ $c['to_verify'] }} tugas menunggu verifikasi Anda.
                        <x-icon name="chevron-right" class="ml-auto size-4" />
                    </a>
                @endif
            </div>
        @endif
    </section>

    <div class="grid gap-6 xl:grid-cols-3 [&>*]:min-w-0">
        {{-- ========== Tugas mendatang ========== --}}
        <section class="card xl:col-span-2">
            <div class="flex items-center justify-between px-5 pt-5">
                <h2 class="font-bold">Tugas yang perlu dikerjakan</h2>
                <a href="{{ route('tasks.index') }}" wire:navigate class="text-sm font-semibold text-bps-blue-700 hover:underline">Buka papan tugas</a>
            </div>
            <div class="mt-3 divide-y divide-line">
                @forelse ($this->upcoming as $t)
                    <a href="{{ route('tasks.show', $t) }}" wire:navigate class="flex items-center gap-4 px-5 py-3.5 hover:bg-paper/70">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold">{{ $t->title }}</p>
                            <p class="mt-0.5 truncate text-xs text-navy-500">{{ $t->code }}{{ $t->team ? ' · '.$t->team->name : '' }} · dari {{ $t->creator->display_name }}</p>
                        </div>
                        <x-status :status="$t->status" class="hidden sm:inline-flex" />
                        <div class="w-24 text-right text-xs">
                            @if ($t->effectiveDueDate())
                                <span class="{{ $t->isOverdue() ? 'font-semibold text-rose-600' : ($t->isDueSoon() ? 'font-semibold text-bps-orange-700' : 'text-navy-500') }}">{{ tgl($t->effectiveDueDate(), 'j M') }}</span>
                                <span class="block text-navy-300">{{ $t->effectiveDueDate()->endOfDay()->diffForHumans(['parts' => 1]) }}</span>
                            @else
                                <span class="text-navy-300">Tanpa deadline</span>
                            @endif
                        </div>
                        <span class="w-12 text-right text-sm font-bold">{{ poin($t->points) }}<span class="block text-[10px] font-medium text-navy-500">poin</span></span>
                    </a>
                @empty
                    <x-empty icon="sparkles" title="Tidak ada tugas yang menunggu" text="{{ $u->canAssign() ? 'Bagikan tugas ke anggota tim atau ajukan tugas mandiri.' : 'Belum ada arahan? Ajukan tugas mandiri ke ketua tim Anda.' }}">
                        <button class="btn btn-ghost" @click="$dispatch('open-task-form', { mode: 'mandiri' })"><x-icon name="plus" class="size-4" /> Ajukan tugas mandiri</button>
                    </x-empty>
                @endforelse
            </div>
        </section>

        {{-- ========== Tren poin harian ========== --}}
        <section class="card card-pad">
            <h2 class="font-bold">Poin terverifikasi per hari</h2>
            <p class="text-xs text-navy-500">{{ bulan_tahun() }} · batas wajar 7–8 poin per hari kerja</p>
            <div class="mt-5 flex h-36 items-end gap-[3px]" role="img" aria-label="Grafik poin harian">
                @foreach ($trend as $day => $val)
                    @php $d = now()->startOfMonth()->addDays($day - 1); @endphp
                    <div class="group relative flex h-full flex-1 items-end">
                        <div class="w-full rounded-t-sm {{ $d->isWeekend() ? 'bg-navy-100' : ($val > 0 ? 'bg-bps-green' : 'bg-navy-50') }} {{ $d->isToday() ? 'ring-2 ring-bps-orange ring-offset-1' : '' }}"
                             style="height: {{ max($val / $maxTrend * 100, 3) }}%"></div>
                        <span class="pointer-events-none absolute -top-7 left-1/2 hidden -translate-x-1/2 rounded bg-navy-900 px-1.5 py-0.5 text-[10px] whitespace-nowrap text-white group-hover:block">{{ $day }}: {{ poin($val) }}</span>
                    </div>
                @endforeach
            </div>
            <div class="mt-1 flex justify-between text-[10px] text-navy-300"><span>1</span><span>{{ (int) (count($trend) / 2) }}</span><span>{{ count($trend) }}</span></div>
            <a href="{{ route('portfolio.export', ['periode' => 'triwulan']) }}" class="btn btn-ghost mt-5 w-full"><x-icon name="download" class="size-4" /> Unduh portofolio triwulan (KIPPAPP)</a>
        </section>
    </div>

    {{-- ========== Pemantauan (pimpinan / ketua tim) ========== --}}
    @if ($u->canMonitor())
        @php $wl = $this->workload; @endphp
        <div class="grid gap-6 xl:grid-cols-3 [&>*]:min-w-0">
            <section class="card xl:col-span-2">
                <div class="flex flex-wrap items-center justify-between gap-2 px-5 pt-5">
                    <div>
                        <h2 class="font-bold">Beban kerja {{ $u->is_pimpinan || $u->is_admin ? 'pegawai' : 'anggota tim' }}</h2>
                        <p class="text-xs text-navy-500">Diurutkan dari yang paling ringan · klik "Beri tugas" untuk disposisi langsung</p>
                    </div>
                    <a href="{{ route('workload') }}" wire:navigate class="text-sm font-semibold text-bps-blue-700 hover:underline">Lihat semua</a>
                </div>
                <div class="mt-3 divide-y divide-line">
                    @foreach ($wl->sortBy('load_pct')->take(7) as $row)
                        <div class="flex items-center gap-3 px-5 py-3">
                            <x-avatar :user="$row['user']" size="sm" />
                            <div class="w-36 min-w-0 sm:w-44">
                                <p class="truncate text-sm font-semibold">{{ $row['user']->display_name }}</p>
                                <x-activity-dot :activity="$row['activity']" />
                            </div>
                            <x-points-bar class="flex-1" :verified="$row['verified']" :committed="$row['committed']" :target="$row['target']" />
                            <span class="w-12 text-right text-sm font-bold">{{ round($row['load_pct']) }}%</span>
                            @if ($u->canAssign())
                                <button class="btn btn-ghost btn-sm hidden sm:inline-flex" @click="$dispatch('open-task-form', { mode: 'assign', assignee: {{ $row['user']->id }} })">Beri tugas</button>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="space-y-6">
                @if ($this->teamStats->isNotEmpty())
                    <div class="card card-pad">
                        <h2 class="font-bold">Utilisasi per tim</h2>
                        <div class="mt-4 space-y-3.5">
                            @foreach ($this->teamStats as $ts)
                                <div>
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="flex items-center gap-2 font-medium"><span class="size-2.5 rounded-sm {{ $ts['team']->color() }}"></span>{{ $ts['team']->name }}</span>
                                        <span class="font-semibold">{{ $ts['avg_load'] }}%</span>
                                    </div>
                                    <div class="slant-track mt-1.5 h-2"><div class="absolute inset-y-0 left-0 {{ $ts['team']->color() }}" style="width: {{ min($ts['avg_load'], 100) }}%"></div></div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="card">
                    <h2 class="px-5 pt-5 font-bold">Tugas terlambat</h2>
                    <div class="mt-2 divide-y divide-line">
                        @forelse ($this->overdueTasks as $t)
                            <a href="{{ route('tasks.show', $t) }}" wire:navigate class="flex items-center gap-3 px-5 py-3 hover:bg-paper/70">
                                <x-avatar :user="$t->assignee" size="xs" />
                                <p class="min-w-0 flex-1 truncate text-sm">{{ $t->title }}</p>
                                <span class="text-xs font-semibold whitespace-nowrap text-rose-600">{{ tgl($t->effectiveDueDate(), 'j M') }}</span>
                            </a>
                        @empty
                            <p class="px-5 pb-5 text-sm text-navy-500">Tidak ada tugas terlambat.</p>
                        @endforelse
                    </div>
                </div>
            </section>
        </div>
    @endif

    {{-- ========== Aktivitas terbaru ========== --}}
    <section class="card">
        <h2 class="px-5 pt-5 font-bold">Aktivitas terbaru</h2>
        <ol class="mt-2 divide-y divide-line">
            @forelse ($this->recentActivity as $a)
                <li class="flex items-start gap-3 px-5 py-3 text-sm">
                    @if ($a->user)<x-avatar :user="$a->user" size="xs" class="mt-0.5" />@else<span class="mt-0.5 grid size-6 place-items-center rounded-full bg-navy-100 text-navy-500"><x-icon name="cog" class="size-3.5" /></span>@endif
                    <p class="min-w-0 flex-1"><b>{{ $a->user?->display_name ?? 'Sistem' }}</b> {{ $a->description }} —
                        <a href="{{ route('tasks.show', $a->task_id) }}" wire:navigate class="text-bps-blue-700 hover:underline">{{ \Illuminate\Support\Str::limit($a->task?->title, 60) }}</a></p>
                    <time class="shrink-0 text-xs text-navy-300">{{ $a->created_at->diffForHumans() }}</time>
                </li>
            @empty
                <li><x-empty icon="clock" title="Belum ada aktivitas" /></li>
            @endforelse
        </ol>
    </section>
</div>
