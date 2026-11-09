<!DOCTYPE html>
<html lang="id">
<head>
    @include('layouts.partials-head')
</head>
<body class="min-h-dvh" data-auth="{{ auth()->check() ? 1 : 0 }}">
    <div class="grid min-h-dvh lg:grid-cols-[1.05fr_1fr]">
        {{-- Panel identitas --}}
        <aside class="relative hidden overflow-hidden bg-navy-900 p-12 text-white lg:flex lg:flex-col lg:justify-between">
            <div class="flex items-center gap-3">
                <span class="grid size-12 place-items-center rounded-2xl bg-white p-1.5"><img src="/images/logo-bps-256.png" alt="BPS" class="h-full w-full object-contain"></span>
                <div class="leading-tight">
                    <p class="text-sm font-semibold text-navy-100/80">Badan Pusat Statistik</p>
                    <p class="text-sm font-semibold">Kabupaten Pangkajene dan Kepulauan</p>
                </div>
            </div>

            <div class="relative z-10 max-w-md">
                <h1 class="text-[2.6rem] leading-[1.05] font-extrabold tracking-tight">PANTAU<br>SEPANGKEP</h1>
                <p class="mt-5 text-lg leading-relaxed text-navy-100/80">Satu tempat untuk membagi tugas, melaporkan hasil kerja, dan melihat beban kerja seluruh tim secara adil dan terukur.</p>
                <dl class="mt-10 grid grid-cols-3 gap-4 text-sm">
                    <div><dt class="text-navy-100/60">Beri tugas</dt><dd class="mt-1 font-semibold">Pimpinan → Ketua tim → Anggota</dd></div>
                    <div><dt class="text-navy-100/60">Bukti kerja</dt><dd class="mt-1 font-semibold">Tersimpan di Google Drive</dd></div>
                    <div><dt class="text-navy-100/60">Notifikasi</dt><dd class="mt-1 font-semibold">Langsung ke HP</dd></div>
                </dl>
            </div>

            {{-- Tiga bilah miring: gema logo BPS --}}
            <div class="pointer-events-none absolute -right-16 bottom-10 flex rotate-[-12deg] gap-4 opacity-90" aria-hidden="true">
                <span class="slant h-72 w-24 bg-bps-blue"></span>
                <span class="slant mt-20 h-72 w-24 bg-bps-green"></span>
                <span class="slant -mt-10 h-72 w-24 bg-bps-orange"></span>
            </div>
            <p class="relative z-10 text-xs text-navy-100/50">© {{ date('Y') }} BPS Kabupaten Pangkajene dan Kepulauan</p>
        </aside>

        <main class="flex items-center justify-center px-5 py-10 sm:px-10">
            <div class="w-full max-w-sm">
                {{ $slot }}
            </div>
        </main>
    </div>
    @livewireScripts
</body>
</html>
