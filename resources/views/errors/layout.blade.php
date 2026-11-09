<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $code }} – PANTAU SEPANGKEP</title>
    <link rel="icon" href="/favicon.ico">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @endif
</head>
<body class="grid min-h-dvh place-items-center bg-paper px-6 text-navy-900">
    <main class="max-w-md text-center">
        <div class="mx-auto mb-8 flex w-fit -rotate-12 gap-2" aria-hidden="true">
            <span class="slant h-16 w-6 bg-bps-blue"></span><span class="slant mt-4 h-16 w-6 bg-bps-green"></span><span class="slant -mt-2 h-16 w-6 bg-bps-orange"></span>
        </div>
        <p class="text-6xl font-extrabold tracking-tight">{{ $code }}</p>
        <h1 class="mt-3 text-xl font-bold">{{ $title }}</h1>
        <p class="mt-2 text-navy-500">{{ $message }}</p>
        <div class="mt-8 flex justify-center gap-2">
            <a href="{{ url('/') }}" class="btn btn-primary">Ke beranda</a>
            <button onclick="history.back()" class="btn btn-ghost">Kembali</button>
        </div>
    </main>
</body>
</html>
