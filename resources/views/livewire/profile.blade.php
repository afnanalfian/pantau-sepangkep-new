<div>
    <x-page-header title="Profil saya" />
    <div class="grid gap-6 lg:grid-cols-[22rem_1fr] [&>*]:min-w-0">
        <section class="card overflow-hidden">
            <div class="flex flex-col items-center px-6 pt-8 pb-6 text-center">
                <label class="group relative cursor-pointer">
                    <x-avatar :user="$user" size="xl" />
                    <span class="absolute inset-0 grid place-items-center rounded-full bg-navy-900/60 text-white opacity-0 transition group-hover:opacity-100"><x-icon name="photo" /></span>
                    <input type="file" wire:model="avatar" accept="image/*" class="sr-only">
                </label>
                <div wire:loading wire:target="avatar" class="mt-2 text-xs text-bps-blue-700">Mengunggah…</div>
                @error('avatar') <p class="error">{{ $message }}</p> @enderror
                <h2 class="mt-4 text-lg font-extrabold">{{ $user->display_name }}</h2>
                <p class="text-sm text-navy-500">{{ '@'.$user->username }}</p>
                <div class="mt-3 flex flex-wrap justify-center gap-1.5">
                    @foreach ($user->roleLabels() as $r)<span class="chip bg-navy-50 text-navy-700 ring-line">{{ $r }}</span>@endforeach
                </div>
            </div>
            <dl class="divide-y divide-line border-t border-line text-sm">
                <div class="flex px-5 py-3"><dt class="w-24 text-navy-500">NIP</dt><dd class="font-medium">{{ $user->nip ?: '–' }}</dd></div>
                <div class="flex px-5 py-3"><dt class="w-24 shrink-0 text-navy-500">Jabatan</dt><dd class="font-medium">{{ $user->jabatan ?: '–' }}</dd></div>
                <div class="flex px-5 py-3"><dt class="w-24 shrink-0 text-navy-500">Tim</dt>
                    <dd class="font-medium">@forelse ($user->teams as $t){{ $t->name }}{{ $t->pivot->role === 'ketua' ? ' (Ketua)' : '' }}@if(!$loop->last), @endif @empty – @endforelse</dd></div>
                <div class="flex px-5 py-3"><dt class="w-24 text-navy-500">Target</dt><dd class="font-medium">{{ poin($stats['target']) }} poin / bulan</dd></div>
                @if ($leave)<div class="flex px-5 py-3"><dt class="w-24 text-navy-500">Status</dt><dd class="font-medium text-amber-700">{{ $leave->type_label }} s.d. {{ tgl($leave->end_date) }}</dd></div>@endif
            </dl>
            <p class="border-t border-line px-5 py-3 text-xs text-navy-500">Nama, NIP, jabatan, dan tim diatur oleh admin.</p>
        </section>

        <div class="space-y-6">
            <section class="card card-pad">
                <h2 class="font-bold">Capaian bulan ini</h2>
                <x-points-bar class="mt-4" height="h-4" :verified="$stats['verified']" :committed="$stats['committed']" :target="$stats['target']" legend />
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('portfolio.export') }}" class="btn btn-ghost btn-sm"><x-icon name="download" class="size-4" /> Portofolio bulan ini</a>
                    <a href="{{ route('portfolio.export', ['periode' => 'triwulan']) }}" class="btn btn-ghost btn-sm"><x-icon name="download" class="size-4" /> Portofolio triwulan (KIPPAPP)</a>
                </div>
            </section>

            <section class="card card-pad">
                <h2 class="font-bold">Kontak</h2>
                <form wire:submit="saveContact" class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div><label class="label">Email</label><input wire:model="email" type="email" class="input @error('email') input-error @enderror">@error('email')<p class="error">{{ $message }}</p>@enderror</div>
                    <div><label class="label">No. HP / WhatsApp</label><input wire:model="phone" class="input"></div>
                    <div class="sm:col-span-2"><button class="btn btn-primary">Simpan kontak</button></div>
                </form>
            </section>

            <section class="card card-pad">
                <h2 class="font-bold">Ganti password</h2>
                <form wire:submit="changePassword" class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div><label class="label">Password lama</label><input wire:model="current_password" type="password" class="input @error('current_password') input-error @enderror">@error('current_password')<p class="error">{{ $message }}</p>@enderror</div>
                    <div><label class="label">Password baru</label><input wire:model="password" type="password" class="input @error('password') input-error @enderror">@error('password')<p class="error">{{ $message }}</p>@enderror</div>
                    <div><label class="label">Ulangi password baru</label><input wire:model="password_confirmation" type="password" class="input"></div>
                    <div class="sm:col-span-3"><button class="btn btn-primary">Ganti password</button></div>
                </form>
            </section>
        </div>
    </div>
</div>
