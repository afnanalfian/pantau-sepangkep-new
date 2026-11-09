<!DOCTYPE html>
<html lang="id">
<head>
    @include('layouts.partials-head')
</head>
@php
    $me = auth()->user();
    $approvalCount = \App\Models\Task::where('verifier_id', $me->id)->whereIn('status', ['review', 'pengajuan'])->count();
    $nav = [
        ['Beranda', 'dashboard', 'home', true, null],
        ['Tugas', 'tasks.index', 'board', true, null],
        ['Persetujuan', 'approvals', 'check-circle', true, $approvalCount ?: null],
        ['Kalender', 'calendar', 'calendar', true, null],
        ['Tim Kerja', 'teams.index', 'users', true, null],
        ['Cuti & Dinas Luar', 'leaves', 'plane', true, null],
    ];
    $monitorNav = [
        ['Beban Kerja', 'workload', 'gauge'],
        ['Laporan Kinerja', 'reports', 'chart'],
    ];
    $adminNav = [
        ['Pengguna', 'admin.users', 'user'],
        ['Kelola Tim', 'admin.teams', 'swap'],
        ['Katalog Kegiatan', 'admin.catalog', 'book'],
        ['Pengaturan', 'admin.settings', 'cog'],
    ];
@endphp
<body class="min-h-dvh" data-auth="1"
      x-data="{ sidebar: false }" x-on:keydown.window.ctrl.k.prevent="$refs.search?.focus()">

    {{-- ============ Sidebar ============ --}}
    <div x-show="sidebar" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-navy-950/50 lg:hidden" @click="sidebar = false"></div>
    <aside class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col bg-navy-900 text-white transition-transform duration-200 lg:w-64 lg:translate-x-0"
           :class="sidebar && 'translate-x-0'">
        <div class="flex items-center gap-3 px-5 pt-5 pb-6">
            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-white p-1"><img src="/images/logo-bps-256.png" alt="BPS" class="h-full w-full object-contain"></span>
            <div class="min-w-0 leading-tight">
                <p class="text-[15px] font-extrabold tracking-tight">PANTAU SEPANGKEP</p>
                <p class="truncate text-xs text-navy-100/60">BPS Kab. Pangkep</p>
            </div>
            <button class="ml-auto rounded-lg p-1 text-navy-100/70 lg:hidden" @click="sidebar = false" aria-label="Tutup menu"><x-icon name="x" /></button>
        </div>

        <nav class="flex-1 space-y-6 overflow-y-auto px-3 pb-6">
            <div class="space-y-0.5">
                @foreach ($nav as [$label, $route, $icon, $show, $badge])
                    <a href="{{ route($route) }}" wire:navigate class="nav-link {{ request()->routeIs($route.'*') ? 'active' : '' }}">
                        <x-icon :name="$icon" class="size-5 shrink-0" />
                        <span class="flex-1">{{ $label }}</span>
                        @if ($badge)<span class="rounded-full bg-bps-orange px-2 py-0.5 text-[11px] font-bold text-white">{{ $badge }}</span>@endif
                    </a>
                @endforeach
            </div>

            @if ($me->canMonitor())
                <div>
                    <p class="px-3 pb-2 text-xs font-semibold text-navy-100/45">Pemantauan</p>
                    <div class="space-y-0.5">
                        @foreach ($monitorNav as [$label, $route, $icon])
                            <a href="{{ route($route) }}" wire:navigate class="nav-link {{ request()->routeIs($route) ? 'active' : '' }}"><x-icon :name="$icon" class="size-5" />{{ $label }}</a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($me->is_admin)
                <div>
                    <p class="px-3 pb-2 text-xs font-semibold text-navy-100/45">Administrator</p>
                    <div class="space-y-0.5">
                        @foreach ($adminNav as [$label, $route, $icon])
                            <a href="{{ route($route) }}" wire:navigate class="nav-link {{ request()->routeIs($route) ? 'active' : '' }}"><x-icon :name="$icon" class="size-5" />{{ $label }}</a>
                        @endforeach
                    </div>
                </div>
            @endif
        </nav>

        <div class="border-t border-white/10 p-3">
            <a href="{{ route('profile') }}" wire:navigate class="flex items-center gap-3 rounded-xl p-2 hover:bg-white/5">
                <x-avatar :user="$me" size="sm" />
                <div class="min-w-0 flex-1 leading-tight">
                    <p class="truncate text-sm font-semibold">{{ $me->display_name }}</p>
                    <p class="truncate text-xs text-navy-100/55">{{ implode(', ', $me->roleLabels()) }}</p>
                </div>
            </a>
        </div>
        <div class="flex h-1.5" aria-hidden="true"><span class="flex-1 bg-bps-blue"></span><span class="flex-1 bg-bps-green"></span><span class="flex-1 bg-bps-orange"></span></div>
    </aside>

    {{-- ============ Konten ============ --}}
    <div class="lg:pl-64">
        <header class="sticky top-0 z-30 border-b border-line bg-paper/85 backdrop-blur-md">
            <div class="mx-auto flex h-16 max-w-[1400px] items-center gap-3 px-4 sm:px-6">
                <button class="-ml-1 rounded-lg p-2 text-navy-700 hover:bg-white lg:hidden" @click="sidebar = true" aria-label="Buka menu"><x-icon name="menu" /></button>
                <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2 lg:hidden">
                    <img src="/images/logo-bps-256.png" class="h-7" alt=""><span class="text-sm font-extrabold">PANTAU</span>
                </a>

                <form action="{{ route('tasks.index') }}" method="get" class="relative hidden max-w-md flex-1 md:block">
                    <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-navy-300" />
                    <input x-ref="search" name="search" type="search" placeholder="Cari tugas atau kode… (Ctrl+K)" class="input bg-white/80 py-2 pl-10">
                    <input type="hidden" name="view" value="list">
                    <input type="hidden" name="scope" value="{{ $me->is_pimpinan || $me->is_admin ? 'all' : ($me->isLeader() ? 'team' : 'mine') }}">
                </form>

                <div class="ml-auto flex items-center gap-1.5 sm:gap-2">
                    <button type="button" class="btn btn-blue hidden sm:inline-flex"
                            @click="$dispatch('open-task-form', { mode: '{{ $me->canAssign() ? 'assign' : 'mandiri' }}' })">
                        <x-icon name="plus" class="size-4" /> {{ $me->canAssign() ? 'Beri tugas' : 'Ajukan tugas' }}
                    </button>
                    <livewire:notifications.bell />
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="flex items-center gap-2 rounded-xl p-1 hover:bg-white" aria-label="Menu akun">
                            <x-avatar :user="$me" size="sm" />
                        </button>
                        <div x-show="open" x-cloak @click.outside="open = false" x-transition.origin.top.right
                             class="absolute right-0 mt-2 w-60 rounded-2xl border border-line bg-white p-1.5 shadow-xl">
                            <div class="px-3 py-2.5">
                                <p class="truncate text-sm font-semibold">{{ $me->display_name }}</p>
                                <p class="truncate text-xs text-navy-500">{{ '@'.$me->username }}{{ $me->jabatan ? ' – '.$me->jabatan : '' }}</p>
                            </div>
                            <a href="{{ route('profile') }}" wire:navigate class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm hover:bg-navy-50"><x-icon name="user" class="size-4" /> Profil & password</a>
                            <a href="{{ route('notifications') }}" wire:navigate class="flex items-center gap-2 rounded-xl px-3 py-2 text-sm hover:bg-navy-50"><x-icon name="phone" class="size-4" /> Notifikasi di HP</a>
                            <form method="POST" action="{{ route('logout') }}" data-logout>
                                @csrf
                                <button class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-sm text-rose-600 hover:bg-rose-50"><x-icon name="logout" class="size-4" /> Keluar</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        {{-- Ajakan mengaktifkan notifikasi HP --}}
        <div x-data="{ state: null, busy: false, msg: '' }"
             x-init="if (window.PantauPush && !localStorage.getItem('pantau-push-dismiss')) PantauPush.status().then(s => state = s).catch(() => {})"
             x-show="state === 'default' || state === 'ios-install'" x-cloak
             class="mx-auto max-w-[1400px] px-4 pt-4 sm:px-6">
            <div class="flex flex-col gap-3 rounded-2xl border border-bps-blue/25 bg-bps-blue-50 p-4 sm:flex-row sm:items-center">
                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-bps-blue text-white"><x-icon name="bell" /></span>
                <div class="flex-1 text-sm">
                    <template x-if="state === 'default'"><p><b>Aktifkan notifikasi di perangkat ini</b> supaya tugas baru, permintaan verifikasi, dan pengingat deadline langsung muncul di HP Anda.</p></template>
                    <template x-if="state === 'ios-install'"><p><b>Pengguna iPhone:</b> ketuk tombol Bagikan lalu “Tambah ke Layar Utama”, buka PANTAU dari ikon tersebut, lalu aktifkan notifikasi.</p></template>
                    <p x-show="msg" x-text="msg" class="mt-1 text-rose-600"></p>
                </div>
                <div class="flex gap-2">
                    <button x-show="state === 'default'" class="btn btn-blue" :disabled="busy"
                            @click="busy = true; PantauPush.subscribe().then(() => { state = 'subscribed'; $dispatch('toast', { message: 'Notifikasi HP aktif.', type: 'success' }) }).catch(e => msg = e.message).finally(() => busy = false)">
                        Aktifkan
                    </button>
                    <button class="btn btn-ghost" @click="localStorage.setItem('pantau-push-dismiss', 1); state = null">Nanti</button>
                </div>
            </div>
        </div>

        <main class="mx-auto max-w-[1400px] px-4 pt-5 pb-28 sm:px-6 sm:pt-7 lg:pb-12">
            {{ $slot }}
        </main>
    </div>

    {{-- ============ Navigasi bawah (HP) ============ --}}
    <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden">
        <div class="grid grid-cols-5 items-end text-[11px] font-medium text-navy-500">
            @foreach ([['Beranda', 'dashboard', 'home'], ['Tugas', 'tasks.index', 'board']] as [$l, $r, $i])
                <a href="{{ route($r) }}" wire:navigate class="flex flex-col items-center gap-1 py-2.5 {{ request()->routeIs($r) ? 'text-navy-900' : '' }}"><x-icon :name="$i" class="size-6" />{{ $l }}</a>
            @endforeach
            <div class="flex justify-center">
                <button class="-mt-5 grid size-14 place-items-center rounded-2xl bg-bps-blue text-white shadow-lg shadow-bps-blue/30" aria-label="Tugas baru"
                        @click="$dispatch('open-task-form', { mode: '{{ $me->canAssign() ? 'assign' : 'mandiri' }}' })"><x-icon name="plus" class="size-7" /></button>
            </div>
            <a href="{{ route('approvals') }}" wire:navigate class="relative flex flex-col items-center gap-1 py-2.5 {{ request()->routeIs('approvals') ? 'text-navy-900' : '' }}">
                <x-icon name="check-circle" class="size-6" />Verifikasi
                @if ($approvalCount)<span class="absolute top-1.5 right-[22%] grid min-w-4 place-items-center rounded-full bg-bps-orange px-1 text-[10px] font-bold text-white">{{ $approvalCount }}</span>@endif
            </a>
            <button @click="sidebar = true" class="flex flex-col items-center gap-1 py-2.5"><x-icon name="menu" class="size-6" />Menu</button>
        </div>
    </nav>

    {{-- ============ Toast ============ --}}
    <div x-data="{ items: [], add(e) { const id = Date.now() + Math.random(); this.items.push({ id, ...e }); setTimeout(() => this.items = this.items.filter(i => i.id !== id), 4500) } }"
         x-on:toast.window="add(Array.isArray($event.detail) ? $event.detail[0] : $event.detail)"
         class="pointer-events-none fixed inset-x-0 bottom-24 z-[60] flex flex-col items-center gap-2 px-4 sm:top-5 sm:right-5 sm:bottom-auto sm:left-auto sm:items-end">
        <template x-for="t in items" :key="t.id">
            <div x-transition class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-2xl bg-navy-900 px-4 py-3 text-sm text-white shadow-2xl animate-slide-up">
                <span class="mt-0.5 size-2.5 shrink-0 rounded-full"
                      :class="{ 'bg-bps-green': t.type === 'success' || !t.type, 'bg-rose-400': t.type === 'error', 'bg-bps-blue': t.type === 'info', 'bg-bps-orange': t.type === 'warning' }"></span>
                <p class="flex-1" x-text="t.message"></p>
                <button @click="items = items.filter(i => i.id !== t.id)" class="text-white/60 hover:text-white" aria-label="Tutup"><x-icon name="x" class="size-4" /></button>
            </div>
        </template>
    </div>

    <livewire:tasks.task-form />

    @if (session('toast'))
        <script>document.addEventListener('livewire:init', () => setTimeout(() => window.dispatchEvent(new CustomEvent('toast', { detail: { message: @js(session('toast')), type: 'success' } })), 300))</script>
    @endif
    @livewireScripts
</body>
</html>
