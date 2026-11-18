@php
    $u = auth()->user();
    $me = $this->me;
    $c = $this->counts;
    $hour = now()->hour;
    $greet = $hour < 11 ? 'Selamat pagi' : ($hour < 15 ? 'Selamat siang' : ($hour < 18 ? 'Selamat sore' : 'Selamat malam'));
    $w = \App\Services\KinerjaService::weights();
@endphp
<div class="space-y-6">
    {{-- ========== Matriks pribadi bulan ini ========== --}}
    <section class="card overflow-hidden">
        <div class="grid lg:grid-cols-[1fr_auto]">
            <div class="p-5 sm:p-7">
                <p class="text-sm text-navy-500">{{ $greet }}, {{ $u->first_name }} · {{ tgl(now(), 'l, j F Y') }}</p>
                <h1 class="mt-1 text-2xl font-extrabold tracking-tight sm:text-3xl">Kinerja {{ bulan_tahun() }}</h1>

                <div class="mt-6 grid grid-cols-3 gap-3 sm:max-w-lg">
                    @foreach ([
                        ['Penyelesaian tugas', $me['tugas_pct'], 'text-bps-blue', $me['tugas_selesai'].'/'.$me['tugas_total'].' tugas', $w['tugas']],
                        ['Kehadiran apel', $me['apel_pct'], 'text-bps-orange', $me['apel_hadir'].'/'.$me['apel_wajib'].' apel', $w['apel']],
                        ['Kehadiran rapat', $me['rapat_pct'], 'text-bps-green', $me['rapat_hadir'].'/'.$me['rapat_wajib'].' rapat', $w['rapat']],
                    ] as [$label, $pct, $color, $sub, $bobot])
                        <div class="text-center">
                            <x-ring class="mx-auto text-sm sm:text-base" size="size-20 sm:size-24" :pct="$pct" :color="$color" />
                            <p class="mt-2 text-xs font-semibold sm:text-sm">{{ $label }}</p>
                            <p class="text-[11px] text-navy-500">{{ $sub }}</p>
                        </div>
                    @endforeach
                </div>

                @if ($me['tugas_carry'])
                    <p class="mt-5 inline-flex items-center gap-2 rounded-xl bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700">
                        <x-icon name="alert" class="size-4" /> {{ $me['tugas_carry'] }} tugas bulan lalu belum selesai dan ikut dihitung bulan ini.
                    </p>
                @endif
            </div>

            {{-- Nilai akhir --}}
            <div class="relative flex flex-col justify-between gap-6 overflow-hidden bg-navy-900 p-6 text-white sm:p-7 lg:w-80">
                <div class="pointer-events-none absolute -top-8 -right-10 flex rotate-[-14deg] gap-2 opacity-25" aria-hidden="true">
                    <span class="slant h-40 w-8 bg-bps-blue"></span><span class="slant mt-8 h-40 w-8 bg-bps-green"></span><span class="slant -mt-4 h-40 w-8 bg-bps-orange"></span>
                </div>
                <div class="relative">
                    <p class="text-sm text-navy-100/70">Nilai akhir sementara</p>
                    <p class="mt-1 text-6xl leading-none font-extrabold tracking-tight">{{ rtrim(rtrim(number_format($me['nilai'], 2, ',', '.'), '0'), ',') }}<span class="text-3xl">%</span></p>
                    <x-potongan :value="$me['potongan']" class="mt-4" />
                </div>
                <div class="relative space-y-2 text-xs text-navy-100/70">
                    <p>Bobot: tugas {{ $w['tugas'] + 0 }} · apel {{ $w['apel'] + 0 }} · rapat {{ $w['rapat'] + 0 }}. Bulan masih berjalan, nilai bisa berubah.</p>
                    <a href="{{ route('profile') }}" wire:navigate class="inline-flex items-center gap-1 font-semibold text-white hover:underline">Lihat riwayat matriks <x-icon name="chevron-right" class="size-3.5" /></a>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 divide-line border-t border-line sm:grid-cols-4 sm:divide-x">
            @foreach ([
                ['Belum dikerjakan', $c['todo'], 'todo', 'bg-slate-400'],
                ['Sedang dikerjakan', $c['in_progress'], 'in_progress', 'bg-bps-blue'],
                ['Menunggu verifikasi', $c['review'], 'review', 'bg-amber-500'],
                ['Selesai bulan ini', $c['done'], 'selesai', 'bg-bps-green'],
            ] as [$label, $val, $st, $dot])
                <a href="{{ route('tasks.index', ['view' => 'list', 'status' => $st]) }}" wire:navigate class="flex items-center justify-between gap-2 px-5 py-4 transition hover:bg-paper/70">
                    <span class="flex items-center gap-2 text-xs text-navy-500"><span class="size-2 rounded-full {{ $dot }}"></span>{{ $label }}</span>
                    <span class="text-2xl font-extrabold">{{ $val }}</span>
                </a>
            @endforeach
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
                    </a>
                @empty
                    <x-empty icon="sparkles" title="Tidak ada tugas yang menunggu" text="{{ $u->canAssign() ? 'Bagikan tugas ke anggota tim atau ajukan tugas mandiri.' : 'Belum ada arahan? Ajukan tugas mandiri ke ketua tim Anda.' }}">
                        <button class="btn btn-ghost" @click="$dispatch('open-task-form', { mode: 'mandiri' })"><x-icon name="plus" class="size-4" /> Ajukan tugas mandiri</button>
                    </x-empty>
                @endforelse
            </div>
        </section>

        <div class="space-y-6">
            {{-- ========== Presensi saya ========== --}}
            <section class="card">
                <h2 class="px-5 pt-5 font-bold">Kehadiran saya bulan ini</h2>
                <ul class="mt-2 divide-y divide-line">
                    @forelse ($this->myPresensi as $k)
                        @php $st = $k->kehadirans->first()?->status; @endphp
                        <li class="flex items-center gap-3 px-5 py-2.5 text-sm">
                            <span class="size-2.5 shrink-0 rounded-full {{ \App\Models\PresensiKegiatan::JENIS[$k->jenis]['dot'] }}"></span>
                            <span class="min-w-0 flex-1 truncate">{{ $k->nama }}<span class="block text-xs text-navy-500">{{ tgl($k->tanggal, 'D, j M') }}</span></span>
                            <span class="chip {{ $st === 'hadir' ? 'bg-bps-green-50 text-bps-green-700 ring-bps-green/40' : ($st === 'izin' ? 'bg-amber-50 text-amber-700 ring-amber-600/25' : 'bg-rose-50 text-rose-700 ring-rose-600/20') }}">{{ $st === 'hadir' ? 'Hadir' : ($st === 'izin' ? 'Izin' : 'Tidak hadir') }}</span>
                        </li>
                    @empty
                        <li class="px-5 pb-5 text-sm text-navy-500">Belum ada apel atau rapat bulan ini.</li>
                    @endforelse
                </ul>
            </section>

            {{-- ========== Portal Inovasi ========== --}}
            <a href="{{ route('portal.home') }}" wire:navigate class="group relative block overflow-hidden rounded-2xl bg-gradient-to-br from-bps-blue-600 to-navy-800 p-5 text-white">
                <div class="pointer-events-none absolute -right-6 -bottom-8 flex rotate-[-14deg] gap-2 opacity-30" aria-hidden="true">
                    <span class="slant h-28 w-6 bg-white"></span><span class="slant mt-6 h-28 w-6 bg-bps-green"></span><span class="slant -mt-3 h-28 w-6 bg-bps-orange"></span>
                </div>
                <p class="relative text-xs font-semibold text-white/70">Portal Inovasi</p>
                <p class="relative mt-1 text-lg font-extrabold">SICATUR, SIKU, SIBEBAN &amp; layanan kantor</p>
                <span class="relative mt-3 inline-flex items-center gap-1 text-sm font-semibold group-hover:underline">Buka portal <x-icon name="arrow-right" class="size-4" /></span>
            </a>
        </div>
    </div>

    {{-- ========== Pemantauan (pimpinan / ketua tim) ========== --}}
    @if ($u->canMonitor())
        @php $mt = $this->matriksTim; @endphp
        <div class="grid gap-6 xl:grid-cols-3 [&>*]:min-w-0">
            <section class="card xl:col-span-2">
                <div class="flex flex-wrap items-center justify-between gap-2 px-5 pt-5">
                    <div>
                        <h2 class="font-bold">Penyelesaian tugas {{ $u->is_pimpinan || $u->is_admin ? 'pegawai' : 'anggota tim' }}</h2>
                        <p class="text-xs text-navy-500">Diurutkan dari yang paling sedikit tugas aktif · klik "Beri tugas" untuk disposisi langsung</p>
                    </div>
                    <a href="{{ route('matriks') }}" wire:navigate class="text-sm font-semibold text-bps-blue-700 hover:underline">Matriks lengkap</a>
                </div>
                <div class="mt-3 divide-y divide-line">
                    @foreach ($mt->sortBy('aktif')->take(7) as $row)
                        <div class="flex items-center gap-3 px-5 py-3">
                            <x-avatar :user="$row['user']" size="sm" />
                            <div class="w-36 min-w-0 sm:w-44">
                                <p class="truncate text-sm font-semibold">{{ $row['user']->display_name }}</p>
                                <p class="text-xs text-navy-500">{{ $row['aktif'] }} tugas aktif</p>
                            </div>
                            <x-completion-bar class="flex-1" :selesai="$row['tugas_selesai']" :berjalan="$row['tugas_berjalan']" :gagal="$row['tugas_gagal']" />
                            <span class="w-14 text-right text-sm"><x-pct :value="$row['tugas_pct']" /></span>
                            @if ($u->canAssign())
                                <button class="btn btn-ghost btn-sm hidden sm:inline-flex" @click="$dispatch('open-task-form', { mode: 'assign', assignee: {{ $row['user']->id }} })">Beri tugas</button>
                            @endif
                        </div>
                    @endforeach
                    @if ($mt->isEmpty())<p class="px-5 pb-5 text-sm text-navy-500">Belum ada pegawai dalam tim.</p>@endif
                </div>
            </section>

            <section class="space-y-6">
                @if ($this->teamStats->isNotEmpty())
                    <div class="card card-pad">
                        <h2 class="font-bold">Penyelesaian per tim</h2>
                        <div class="mt-4 space-y-3.5">
                            @foreach ($this->teamStats as $ts)
                                <div>
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="flex items-center gap-2 font-medium"><span class="size-2.5 rounded-sm {{ $ts['team']->color() }}"></span>{{ $ts['team']->name }}</span>
                                        <span class="font-semibold">{{ $ts['pct'] !== null ? $ts['pct'].'%' : '–' }}</span>
                                    </div>
                                    <div class="slant-track mt-1.5 h-2"><div class="absolute inset-y-0 left-0 {{ $ts['team']->color() }}" style="width: {{ min($ts['pct'] ?? 0, 100) }}%"></div></div>
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
