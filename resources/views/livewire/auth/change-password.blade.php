<div>
    <span class="grid size-12 place-items-center rounded-2xl bg-bps-orange-50 text-bps-orange-600"><x-icon name="key" class="size-6" /></span>
    <h2 class="mt-5 text-2xl font-extrabold tracking-tight">{{ $forced ? 'Buat password baru' : 'Ganti password' }}</h2>
    <p class="mt-1 text-sm text-navy-500">
        @if ($forced)
            Halo <b>{{ auth()->user()->display_name }}</b>, ini login pertama Anda. Demi keamanan, ganti password awal dari admin sebelum melanjutkan.
        @else
            Masukkan password lama dan password baru Anda.
        @endif
    </p>

    <form wire:submit="save" class="mt-7 space-y-4">
        @unless ($forced)
            <div>
                <label class="label" for="current_password">Password lama</label>
                <input wire:model="current_password" id="current_password" type="password" class="input @error('current_password') input-error @enderror" autocomplete="current-password">
                @error('current_password') <p class="error">{{ $message }}</p> @enderror
            </div>
        @endunless
        <div>
            <label class="label" for="password">Password baru</label>
            <input wire:model="password" id="password" type="password" class="input @error('password') input-error @enderror" autocomplete="new-password">
            <p class="hint">Minimal 8 karakter, berisi huruf dan angka.</p>
            @error('password') <p class="error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="label" for="password_confirmation">Ulangi password baru</label>
            <input wire:model="password_confirmation" id="password_confirmation" type="password" class="input" autocomplete="new-password">
        </div>
        <button class="btn btn-primary w-full py-3" wire:loading.attr="disabled">Simpan password</button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center" data-logout>
        @csrf
        <button class="text-sm font-medium text-navy-500 hover:text-navy-900">Keluar</button>
    </form>
</div>
