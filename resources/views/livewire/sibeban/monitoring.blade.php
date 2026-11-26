@php
    use App\Services\Sibeban\HonorService;
    use App\Support\Sibeban\Format;
    $kartu = [
        'semua' => ['Semua mitra', 'users', 'text-navy-900', 'bg-navy-50'],
        'tersedia' => ['Masih bisa dipakai', 'check-circle', 'text-bps-green-700', 'bg-bps-green-50'],
        'mendekati' => ['Mendekati limit', 'alert', 'text-bps-orange-700', 'bg-bps-orange-50'],
        'penuh' => ['Sudah penuh', 'lock', 'text-rose-700', 'bg-rose-50'],
    ];
@endphp
<div>
    @include('livewire.sibeban.partials.nav')

    <div class="mb-5 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="text-xl font-extrabold tracking-tight sm:text-2xl">Mitra yang masih bisa dipakai</h1>
            <p class="mt-1 text-sm text-navy-500">Honor seluruh kegiatan di bulan terpilih dibandingkan limit honor bulanan <b class="text-navy-900">{{ Format::rp($limit) }}</b> per mitra.</p>
        </div>
        <div class="flex items-center gap-1.5">
            <button wire:click="geserBulan(-1)" class="btn btn-ghost btn-sm !px-2" aria-label="Bulan sebelumnya"><x-icon name="chevron-left" class="size-4" /></button>
            <input type="month" wire:model.live="bulan" class="input !w-44 !py-1.5 text-center font-semibold">
            <button wire:click="geserBulan(1)" class="btn btn-ghost btn-sm !px-2" aria-label="Bulan berikutnya"><x-icon name="chevron-right" class="size-4" /></button>
            <a href="{{ route('sibeban.monitoring.excel', ['bulan' => $bulan] + array_filter($filter)) }}" class="btn btn-ghost btn-sm ml-1"><x-icon name="download" class="size-4" /> Excel</a>
        </div>
    </div>

    <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ($kartu as $k => [$label, $icon, $warna, $bg])
            <button wire:click="setKetersediaan('{{ $k }}')"
                    class="card flex items-center gap-3 p-3.5 text-left transition hover:border-navy-300 sm:p-4 {{ $ketersediaan === $k ? '!border-navy-900 ring-2 ring-navy-900/10' : '' }}">
                <span class="grid size-10 shrink-0 place-items-center rounded-xl {{ $bg }} {{ $warna }}"><x-icon :name="$icon" class="size-5" /></span>
                <span class="min-w-0">
                    <span class="block text-2xl font-extrabold leading-none tabular-nums {{ $warna }}">{{ Format::angka($ringkas[$k]) }}</span>
                    <span class="mt-1 block truncate text-xs font-medium text-navy-500">{{ $label }}</span>
                </span>
            </button>
        @endforeach
    </div>

    <div class="card mb-4 grid grid-cols-2 gap-2 p-3 sm:grid-cols-3 lg:grid-cols-6">
        <div class="relative col-span-2">
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-navy-300" />
            <input type="search" wire:model.live.debounce.400ms="cari" placeholder="Cari nama / Sobat ID…" class="input !pl-9">
        </div>
        <select wire:model.live="kecamatan" class="input">
            <option value="">Semua kecamatan</option>
            @foreach ($opsi['kecamatan'] as $k)<option value="{{ $k }}">{{ $k }}</option>@endforeach
        </select>
        <select wire:model.live="desa" class="input" @disabled($kecamatan === '')>
            <option value="">{{ $kecamatan === '' ? 'Pilih kecamatan dulu' : 'Semua desa/kelurahan' }}</option>
            @foreach ($opsi['desa'] as $d)<option value="{{ $d }}">{{ $d }}</option>@endforeach
        </select>
        <select wire:model.live="posisi" class="input">
            <option value="">Semua posisi</option>
            @foreach ($opsi['posisi'] as $p)<option value="{{ $p }}">{{ $p }}</option>@endforeach
        </select>
        <select wire:model.live="urut" class="input">
            <option value="sisa">Urut: sisa limit terbesar</option>
            <option value="honor">Urut: honor terbesar</option>
            <option value="nama">Urut: nama</option>
            <option value="wilayah">Urut: wilayah</option>
        </select>
    </div>

    <p class="mb-2 text-sm text-navy-500">
        {{ Format::angka($total) }} mitra · {{ $kartu[$ketersediaan][0] }} · {{ $labelBulan }}
    </p>

    <div class="card overflow-hidden">
        {{-- Desktop --}}
        <table class="hidden w-full text-sm md:table">
            <thead class="table-head">
                <tr>
                    <th class="px-4 py-2.5">Mitra</th>
                    <th class="px-4 py-2.5">Wilayah</th>
                    <th class="px-4 py-2.5 text-right">Honor {{ $labelBulan }}</th>
                    <th class="px-4 py-2.5 text-right">Sisa limit</th>
                    <th class="w-44 px-4 py-2.5">Pemakaian</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($baris as $b)
                    @php $m = $b['mitra']; @endphp
                    <tr wire:key="m-{{ $m->id }}" class="hover:bg-navy-50/40">
                        <td class="px-4 py-3">
                            <a href="{{ route('sibeban.mitra.show', ['mitra' => $m, 'tahun' => substr($bulan, 0, 4)]) }}" wire:navigate class="font-semibold text-navy-900 hover:text-bps-blue-700">{{ $m->nama }}</a>
                            <div class="text-xs text-navy-500">{{ $m->sobat_id }}{{ $m->posisi ? ' · '.$m->posisi : '' }}</div>
                            @if ($pp = $pulsaMitra[$m->id] ?? null)<span class="mt-1 inline-flex items-center gap-1 rounded-md bg-violet-50 px-1.5 py-0.5 text-[11px] font-medium text-violet-700" title="Pulsa {{ $pp->periode(false) }} (tidak dihitung sebagai honor)"><x-icon name="phone" class="size-3" /> Pulsa {{ Format::rp($pp->jumlah) }}</span>@endif
                        </td>
                        <td class="px-4 py-3 text-xs text-navy-700">
                            <div>{{ $m->desa ?: '–' }}</div>
                            <div class="text-navy-500">{{ $m->kecamatan ? 'Kec. '.$m->kecamatan : '' }}</div>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            <div class="font-semibold">{{ Format::rp($b['total']) }}</div>
                            @if ($b['kegiatan'])
                                <div class="text-[11px] text-navy-500" title="{{ implode(', ', $b['kegiatan']) }}">{{ count($b['kegiatan']) }} kegiatan</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right font-semibold tabular-nums {{ $b['sisa'] <= 0 ? 'text-rose-600' : 'text-bps-green-700' }}">{{ Format::rp($b['sisa']) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-navy-50">
                                    <div class="h-full rounded-full {{ HonorService::titik($b['status']) }}" style="width: {{ min(100, $b['persen']) }}%"></div>
                                </div>
                                <span class="w-12 text-right text-xs tabular-nums text-navy-500">{{ Format::persen($b['persen']) }}</span>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-empty icon="users" title="Tidak ada mitra" text="Ubah filter atau bulan untuk melihat mitra lain." /></td></tr>
                @endforelse
            </tbody>
        </table>

        {{-- Mobile --}}
        <div class="divide-y divide-line md:hidden">
            @forelse ($baris as $b)
                @php $m = $b['mitra']; @endphp
                <a href="{{ route('sibeban.mitra.show', ['mitra' => $m, 'tahun' => substr($bulan, 0, 4)]) }}" wire:navigate wire:key="mm-{{ $m->id }}" class="block p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate font-semibold">{{ $m->nama }}</p>
                            <p class="truncate text-xs text-navy-500">{{ $m->sobat_id }} · {{ $m->wilayah() ?: 'wilayah belum diisi' }}</p>
                            @if ($pp = $pulsaMitra[$m->id] ?? null)<span class="mt-1 inline-flex items-center gap-1 rounded-md bg-violet-50 px-1.5 py-0.5 text-[11px] font-medium text-violet-700" title="Pulsa {{ $pp->periode(false) }} (tidak dihitung sebagai honor)"><x-icon name="phone" class="size-3" /> Pulsa {{ Format::rp($pp->jumlah) }}</span>@endif
                        </div>
                        <span class="chip {{ HonorService::warna($b['status']) }}">{{ Format::persen($b['persen']) }}</span>
                    </div>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-navy-50"><div class="h-full rounded-full {{ HonorService::titik($b['status']) }}" style="width: {{ min(100, $b['persen']) }}%"></div></div>
                    <div class="mt-2 flex justify-between text-xs tabular-nums">
                        <span class="text-navy-500">Honor <b class="text-navy-900">{{ Format::rp($b['total']) }}</b></span>
                        <span class="text-navy-500">Sisa <b class="{{ $b['sisa'] <= 0 ? 'text-rose-600' : 'text-bps-green-700' }}">{{ Format::rp($b['sisa']) }}</b></span>
                    </div>
                </a>
            @empty
                <x-empty icon="users" title="Tidak ada mitra" text="Ubah filter atau bulan untuk melihat mitra lain." />
            @endforelse
        </div>
    </div>

    @if ($jumlahHalaman > 1)
        <div class="mt-4 flex items-center justify-between text-sm">
            <button wire:click="previousPage" @disabled($halaman <= 1) class="btn btn-ghost btn-sm"><x-icon name="chevron-left" class="size-4" /> Sebelumnya</button>
            <span class="text-navy-500">Halaman {{ $halaman }} / {{ $jumlahHalaman }}</span>
            <button wire:click="nextPage" @disabled($halaman >= $jumlahHalaman) class="btn btn-ghost btn-sm">Berikutnya <x-icon name="chevron-right" class="size-4" /></button>
        </div>
    @endif
</div>
