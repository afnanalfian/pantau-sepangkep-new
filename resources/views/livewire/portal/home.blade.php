@php
    $tone = ['blue' => 'bg-bps-blue', 'green' => 'bg-bps-green', 'orange' => 'bg-bps-orange', 'navy' => 'bg-navy-800'];
@endphp
<div>
    <div class="mb-6 max-w-2xl">
        <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">Layanan & inovasi kantor</h1>
        <p class="mt-1 text-navy-500">Semua aplikasi pendukung kerja BPS Kabupaten Pangkajene dan Kepulauan di satu tempat. Ikon panah keluar berarti halaman dibuka di tab baru.</p>
    </div>

    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        @foreach ($layanan as $l)
            @php
                $external = isset($l['url']);
                $href = $external ? $l['url'] : route($l['route']);
            @endphp
            <a href="{{ $href }}" @if ($external) target="_blank" rel="noopener" @else wire:navigate @endif
               title="{{ $l['desc'] }}"
               class="group card relative flex flex-col items-center overflow-hidden px-3 pt-5 pb-4 text-center transition hover:-translate-y-0.5 hover:border-navy-300 hover:shadow-lg hover:shadow-navy-900/5 sm:px-5 sm:pt-7 sm:pb-5">
                <span class="absolute inset-x-0 bottom-0 h-1 origin-left scale-x-0 transition-transform duration-300 group-hover:scale-x-100 {{ $tone[$l['tone']] ?? 'bg-bps-blue' }}"></span>
                @if ($external)
                    <x-icon name="external" class="absolute top-3 right-3 size-3.5 text-navy-300" />
                @endif
                <span class="grid size-20 place-items-center sm:size-28">
                    <img src="{{ asset('images/portal/'.$l['logo']) }}" alt="Logo {{ $l['nama'] }}" loading="lazy"
                         class="max-h-full max-w-full object-contain transition duration-300 group-hover:scale-105">
                </span>
                <span class="mt-3 text-base leading-tight font-extrabold tracking-tight text-navy-900 sm:mt-4 sm:text-xl">{{ $l['nama'] }}</span>
                <span class="mt-1 text-[11px] font-semibold tracking-[0.14em] text-navy-500 uppercase sm:text-xs">{{ $l['label'] }}</span>
                <span class="mt-2 hidden text-xs leading-relaxed text-navy-400 sm:block">{{ $l['desc'] }}</span>
            </a>
        @endforeach
    </div>

    {{-- Inovator --}}
    <section class="mt-10">
        <div class="mb-4 flex items-end justify-between gap-3">
            <div>
                <h2 class="text-xl font-extrabold tracking-tight">Inovator</h2>
                <p class="text-sm text-navy-500">Pegawai di balik inovasi-inovasi di portal ini.</p>
            </div>
            @if (auth()->user()->is_admin)
                <button wire:click="create" class="btn btn-ghost btn-sm"><x-icon name="plus" class="size-4" /> Tambah inovator</button>
            @endif
        </div>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            @forelse ($inovators as $i)
                <article class="group card overflow-hidden" wire:key="inv-{{ $i->id }}">
                    <div class="relative aspect-[4/5] bg-gradient-to-br from-navy-100 to-navy-50">
                        @if ($i->fotoUrl())
                            <img src="{{ $i->fotoUrl() }}" alt="{{ $i->nama }}" loading="lazy" class="size-full object-cover">
                        @else
                            <span class="grid size-full place-items-center text-5xl font-extrabold text-navy-300">{{ \Illuminate\Support\Str::of($i->nama)->explode(' ')->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->join('') }}</span>
                        @endif
                        @if (auth()->user()->is_admin)
                            <div class="absolute top-2 right-2 hidden gap-1 group-hover:flex">
                                <button wire:click="edit({{ $i->id }})" class="rounded-lg bg-white/90 p-1.5 text-navy-700" aria-label="Ubah"><x-icon name="pencil" class="size-4" /></button>
                                <button wire:click="delete({{ $i->id }})" wire:confirm="Hapus {{ $i->nama }}?" class="rounded-lg bg-white/90 p-1.5 text-rose-600" aria-label="Hapus"><x-icon name="trash" class="size-4" /></button>
                            </div>
                        @endif
                    </div>
                    <div class="p-3.5">
                        <p class="font-bold leading-snug">{{ $i->nama }}</p>
                        @if ($i->inovasi)<p class="mt-0.5 text-sm font-semibold text-bps-blue-700">{{ $i->inovasi }}</p>@endif
                        @if ($i->keterangan)<p class="mt-1 text-xs text-navy-500">{{ $i->keterangan }}</p>@endif
                    </div>
                </article>
            @empty
                <div class="card col-span-full"><x-empty icon="sparkles" title="Belum ada data inovator" :text="auth()->user()->is_admin ? 'Tambahkan foto dan nama pegawai pembuat inovasi.' : 'Admin akan segera menambahkan.'" /></div>
            @endforelse
        </div>
    </section>

    <x-modal :show="$showForm" close="$set('showForm', false)" :title="$editId ? 'Ubah inovator' : 'Tambah inovator'">
        <form wire:submit="save" id="inv-form" class="space-y-4">
            <div><label class="label">Nama</label><input wire:model="form.nama" class="input @error('form.nama') input-error @enderror">@error('form.nama')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label">Inovasi</label><input wire:model="form.inovasi" class="input" placeholder="mis. SICATUR"></div>
            <div><label class="label">Keterangan singkat</label><input wire:model="form.keterangan" class="input"></div>
            <div><label class="label">Foto</label><input type="file" wire:model="foto" accept="image/*" class="input">@error('foto')<p class="error">{{ $message }}</p>@enderror
                @if ($foto)<img src="{{ $foto->temporaryUrl() }}" class="mt-2 h-32 rounded-xl object-cover" alt="">@endif</div>
        </form>
        <x-slot:footer>
            <button wire:click="$set('showForm', false)" class="btn btn-ghost">Batal</button>
            <button type="submit" form="inv-form" class="btn btn-blue" wire:loading.attr="disabled">Simpan</button>
        </x-slot:footer>
    </x-modal>
</div>
