<div>
    <div class="mb-8 lg:hidden">
        <img src="/images/logo-bps-256.png" alt="BPS" class="h-14">
        <p class="mt-4 text-2xl font-extrabold tracking-tight">PANTAU SEPANGKEP</p>
        <p class="text-sm text-navy-500">BPS Kabupaten Pangkajene dan Kepulauan</p>
    </div>

    <h2 class="text-2xl font-extrabold tracking-tight text-navy-900">Masuk</h2>
    <p class="mt-1 text-sm text-navy-500">Gunakan username dan password yang diberikan admin.</p>

    @if (session('error'))
        <div class="mt-5 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ session('error') }}</div>
    @endif

    <form wire:submit="login" class="mt-7 space-y-4">
        <div>
            <label for="username" class="label">Username</label>
            <input wire:model="username" id="username" type="text" autocomplete="username" autocapitalize="none" autofocus
                   class="input @error('username') input-error @enderror" placeholder="mis. afnanalfian">
            @error('username') <p class="error">{{ $message }}</p> @enderror
        </div>
        <div x-data="{ show: false }">
            <label for="password" class="label">Password</label>
            <div class="relative">
                <input wire:model="password" id="password" :type="show ? 'text' : 'password'" autocomplete="current-password"
                       class="input pr-11 @error('password') input-error @enderror">
                <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 px-3 text-navy-300 hover:text-navy-700" :aria-label="show ? 'Sembunyikan password' : 'Tampilkan password'">
                    <x-icon name="eye" class="size-5" />
                </button>
            </div>
            @error('password') <p class="error">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="btn btn-primary w-full py-3" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="login">Masuk</span>
            <span wire:loading wire:target="login">Memeriksa…</span>
        </button>
        <p class="text-center text-xs text-navy-500">Anda akan tetap masuk di perangkat ini sampai memilih Keluar.</p>
    </form>
</div>
