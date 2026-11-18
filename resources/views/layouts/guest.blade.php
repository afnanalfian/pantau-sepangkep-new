<!DOCTYPE html>
<html lang="id">
<head>
    @include('layouts.partials-head')
</head>
<body class="min-h-dvh bg-white" data-auth="{{ auth()->check() ? 1 : 0 }}">
    <div class="grid min-h-dvh lg:grid-cols-[1.15fr_1fr]">
        {{-- Foto kantor --}}
        <aside class="relative isolate overflow-hidden bg-navy-900 text-white">
            <img src="/images/kantor-bps-pangkep.jpg" alt="Kantor BPS Kabupaten Pangkajene dan Kepulauan"
                 class="absolute inset-0 -z-10 size-full object-cover object-[50%_35%]">
            <div class="absolute inset-0 -z-10 bg-gradient-to-t from-navy-950 via-navy-900/70 to-navy-900/10 lg:bg-gradient-to-r lg:from-navy-950/95 lg:via-navy-900/70 lg:to-navy-900/10"></div>

            <div class="flex h-full min-h-56 flex-col justify-between gap-8 p-6 sm:p-10 lg:min-h-dvh lg:p-12">
                <div class="flex items-center gap-3">
                    <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-white p-1.5 shadow-lg"><img src="/images/logo-bps-256.png" alt="BPS" class="size-full object-contain"></span>
                    <div class="leading-tight">
                        <p class="text-sm font-semibold text-white/80">Badan Pusat Statistik</p>
                        <p class="text-sm font-semibold">Kabupaten Pangkajene dan Kepulauan</p>
                    </div>
                </div>

                <div class="max-w-lg">
                    <h1 class="text-4xl leading-[1.02] font-extrabold tracking-tight sm:text-5xl">PANTAU<br>SEPANGKEP</h1>
                    <p class="mt-4 hidden text-lg leading-relaxed text-white/80 sm:block">Penugasan, presensi, dan matriks kinerja pegawai — terukur, transparan, dan bisa dipantau dari mana saja.</p>
                    <div class="mt-6 hidden h-1.5 w-40 overflow-hidden rounded-full lg:flex" aria-hidden="true">
                        <span class="flex-1 bg-bps-blue"></span><span class="flex-1 bg-bps-green"></span><span class="flex-1 bg-bps-orange"></span>
                    </div>
                </div>

                <p class="hidden text-xs text-white/50 lg:block">© {{ date('Y') }} BPS Kabupaten Pangkajene dan Kepulauan</p>
            </div>
        </aside>

        <main class="flex items-start justify-center px-5 py-10 sm:px-10 lg:items-center">
            <div class="w-full max-w-sm">
                {{ $slot }}
            </div>
        </main>
    </div>
    @livewireScripts
</body>
</html>
