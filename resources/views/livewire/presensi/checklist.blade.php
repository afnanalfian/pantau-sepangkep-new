@php
    $j = \App\Models\PresensiKegiatan::JENIS[$kegiatan->jenis];
    $s = $this->stats;
    $total = array_sum($s);
@endphp
<div>
    <nav class="mb-4 flex items-center gap-1.5 text-sm text-navy-500">
        <a href="{{ route('presensi.index') }}" wire:navigate class="hover:text-navy-900">Presensi</a>
        <x-icon name="chevron-right" class="size-3.5" />
        <span class="truncate font-medium text-navy-900">{{ $kegiatan->nama }}</span>
    </nav>

    <section class="card mb-4 overflow-hidden">
        <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center">
            <div class="min-w-0 flex-1">
                <span class="chip {{ $j['class'] }}">{{ $j['label'] }}</span>
                <h1 class="mt-2 text-xl font-extrabold tracking-tight">{{ $kegiatan->nama }}</h1>
                <p class="text-sm text-navy-500">{{ tgl($kegiatan->tanggal, 'l, j F Y') }}{{ $kegiatan->jam ? ' · '.substr($kegiatan->jam, 0, 5) : '' }}{{ $kegiatan->tempat ? ' · '.$kegiatan->tempat : '' }}</p>
            </div>
            <div class="grid grid-cols-4 gap-2 text-center">
                @foreach ([['Hadir', 'hadir', 'text-bps-green-700'], ['Izin', 'izin', 'text-amber-700'], ['Tidak', 'absen', 'text-rose-600'], ['Belum', 'belum', 'text-navy-500']] as [$l, $k, $c])
                    <button wire:click="$set('filter', '{{ $filter === $k ? 'semua' : $k }}')" class="rounded-xl px-3 py-2 {{ $filter === $k ? 'bg-navy-900 text-white' : 'bg-paper' }}">
                        <span class="block text-2xl font-extrabold {{ $filter === $k ? '' : $c }}">{{ $s[$k] }}</span>
                        <span class="text-[11px] font-medium">{{ $l }}</span>
                    </button>
                @endforeach
            </div>
        </div>
        <div class="flex h-1.5">
            <span class="bg-bps-green" style="width: {{ $total ? $s['hadir'] / $total * 100 : 0 }}%"></span>
            <span class="bg-amber-400" style="width: {{ $total ? $s['izin'] / $total * 100 : 0 }}%"></span>
            <span class="bg-rose-400" style="width: {{ $total ? $s['absen'] / $total * 100 : 0 }}%"></span>
        </div>
    </section>

    <div class="sticky top-16 z-20 -mx-4 mb-3 flex flex-wrap gap-2 bg-paper/90 px-4 py-2 backdrop-blur sm:mx-0 sm:rounded-xl sm:px-0">
        <div class="relative min-w-48 flex-1">
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-navy-300" />
            <input wire:model.live.debounce.200ms="search" type="search" placeholder="Cari nama pegawai…" class="input py-2.5 pl-9">
        </div>
        @if ($s['belum'])
            <button wire:click="markAll('hadir')" wire:confirm="Tandai {{ $s['belum'] }} pegawai yang belum dicatat sebagai HADIR?" class="btn btn-green">Sisanya hadir</button>
            <button wire:click="markAll('absen')" wire:confirm="Tandai {{ $s['belum'] }} pegawai yang belum dicatat sebagai TIDAK HADIR?" class="btn btn-ghost">Sisanya tidak hadir</button>
        @endif
    </div>

    <ul class="card divide-y divide-line">
        @forelse ($list as $p)
            @php $st = $this->statusOf($p->id); $auto = ! $this->marks->has($p->id) && $st === 'izin'; @endphp
            <li class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center" wire:key="ck-{{ $p->id }}">
                <button type="button" wire:click="mark({{ $p->id }}, '{{ $st === 'hadir' ? 'reset' : 'hadir' }}')" class="flex min-w-0 flex-1 items-center gap-3 text-left">
                    <span class="grid size-7 shrink-0 place-items-center rounded-lg border-2 {{ $st === 'hadir' ? 'border-bps-green bg-bps-green text-white' : 'border-navy-100' }}">
                        @if ($st === 'hadir')<x-icon name="check" class="size-4" />@endif
                    </span>
                    <x-avatar :user="$p" size="sm" />
                    <span class="min-w-0">
                        <span class="block truncate font-medium">{{ $p->display_name }}</span>
                        <span class="block truncate text-xs text-navy-500">
                            {{ $p->jabatan ?: $p->teams->pluck('name')->join(', ') }}
                            @if ($auto) · <span class="font-semibold text-amber-700">{{ $this->onLeave[$p->id]->type_label }}</span>@endif
                        </span>
                    </span>
                </button>
                <div class="grid grid-cols-3 gap-1 rounded-xl bg-paper p-1 text-xs font-semibold sm:w-72">
                    @foreach (['hadir' => ['Hadir', 'bg-bps-green text-white'], 'izin' => ['Izin/DL', 'bg-amber-400 text-white'], 'absen' => ['Tidak', 'bg-rose-500 text-white']] as $k => [$l, $on])
                        <button type="button" wire:click="mark({{ $p->id }}, '{{ $st === $k && ! $auto ? 'reset' : $k }}')"
                                class="rounded-lg px-2 py-2 transition {{ $st === $k ? $on : 'text-navy-500 hover:bg-white' }}">{{ $l }}</button>
                    @endforeach
                </div>
            </li>
        @empty
            <li><x-empty icon="users" title="Tidak ada pegawai yang cocok" /></li>
        @endforelse
    </ul>
    <p class="mt-3 text-xs text-navy-500">Ketuk nama untuk mencentang hadir. Ketuk lagi untuk membatalkan. Pegawai yang sedang cuti/DL otomatis tercatat izin. Setiap perubahan langsung tersimpan.</p>
</div>
