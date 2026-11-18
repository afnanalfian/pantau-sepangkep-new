@php
    use App\Services\Sibeban\HonorService;
    use App\Support\Sibeban\Format;
    use App\Support\Sibeban\Periode;
@endphp
<div>
    @include('livewire.sibeban.partials.nav')
    <div class="flex flex-wrap items-end justify-between gap-4 mb-5">
        <div>
            <h1 class="text-xl font-semibold text-slate-900">Monitoring Honor Mitra {{ $tahun }}</h1>
            <p class="text-sm text-slate-500">Total honor tiap mitra per bulan dibandingkan limit SBKS. Klik nama untuk melihat rincian &amp; melepas dari kegiatan.</p>
        </div>
        @include('livewire.sibeban.partials.legenda')
    </div>

    {{-- Kartu ringkasan (klik untuk filter) --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        @foreach (['merah' => 'Melebihi limit', 'oranye' => 'Mendekati limit', 'hijau' => 'Aman', 'kosong' => 'Belum ada honor'] as $s => $label)
            <button wire:click="filterStatus('{{ $s }}')"
                class="text-left rounded-xl bg-white p-4 ring-1 transition {{ $status === $s ? 'ring-2 ring-bps-blue-600' : 'ring-slate-200 hover:ring-slate-300' }}">
                <div class="flex items-center gap-2 text-sm text-slate-500">
                    <span class="h-2.5 w-2.5 rounded-full {{ HonorService::titik($s) }}"></span>{{ $label }}
                </div>
                <div class="mt-1 text-2xl font-semibold tabular text-slate-900">{{ Format::angka($ringkas[$s]) }}</div>
                <div class="text-xs text-slate-400">mitra · {{ $bulan ? Periode::label($bulan, false) : 'kondisi terburuk setahun' }}</div>
            </button>
        @endforeach
    </div>

    {{-- Filter --}}
    <div class="flex flex-wrap items-center gap-2 mb-3">
        <input type="search" wire:model.live.debounce.400ms="cari" placeholder="Cari nama / ID SOBAT…"
               class="w-64 rounded-lg border-slate-300 bg-white px-3 py-2 text-sm ring-1 ring-slate-200 focus:ring-bps-blue">
        <select wire:model.live="tahun" class="rounded-lg bg-white px-3 py-2 text-sm ring-1 ring-slate-200">
            @foreach ($daftarTahun as $t)
                <option value="{{ $t }}">{{ $t }}</option>
            @endforeach
        </select>
        <select wire:model.live="bulan" class="rounded-lg bg-white px-3 py-2 text-sm ring-1 ring-slate-200">
            <option value="">Status: terburuk setahun</option>
            @foreach ($bulans as $b)
                <option value="{{ $b }}">Status bulan {{ Periode::label($b, false) }}</option>
            @endforeach
        </select>
        <select wire:model.live="posisi" class="rounded-lg bg-white px-3 py-2 text-sm ring-1 ring-slate-200">
            <option value="">Semua posisi</option>
            @foreach ($daftarPosisi as $p)
                <option value="{{ $p }}">{{ $p }}</option>
            @endforeach
        </select>
        <select wire:model.live="urut" class="rounded-lg bg-white px-3 py-2 text-sm ring-1 ring-slate-200">
            <option value="persen">Urut: % limit tertinggi</option>
            <option value="sisa">Urut: paling longgar</option>
            <option value="total">Urut: total honor setahun</option>
            <option value="nama">Urut: nama</option>
        </select>
        @if ($status)
            <button wire:click="filterStatus('{{ $status }}')" class="text-sm text-bps-blue-700 hover:underline">Hapus filter status ×</button>
        @endif
        <div wire:loading class="text-sm text-slate-400">Memuat…</div>
    </div>

    {{-- Matriks --}}
    <div class="overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="sticky left-0 z-10 bg-slate-50 px-3 py-2 text-left font-medium">Mitra</th>
                    @foreach ($bulans as $b)
                        <th class="px-1.5 py-2 text-center font-medium {{ $bulan === $b ? 'text-bps-blue-700' : '' }}">{{ substr(Periode::label($b), 0, 3) }}</th>
                    @endforeach
                    <th class="px-3 py-2 text-right font-medium">Total setahun</th>
                    <th class="px-3 py-2 text-left font-medium">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($baris as $r)
                    <tr wire:key="m-{{ $r['mitra']->id }}" class="hover:bg-slate-50/60">
                        <td class="sticky left-0 z-10 bg-white px-3 py-2 min-w-[220px]">
                            <a href="{{ route('sibeban.mitra.show', $r['mitra']) }}" wire:navigate class="font-medium text-slate-900 hover:text-bps-blue-700">{{ $r['mitra']->nama }}</a>
                            <div class="text-xs text-slate-400 tabular">{{ $r['mitra']->sobat_id }}@if ($r['mitra']->posisi) · {{ $r['mitra']->posisi }}@endif</div>
                        </td>
                        @foreach ($bulans as $b)
                            @php $c = $r['sel'][$b]; @endphp
                            <td class="px-1 py-1.5">
                                <div title="{{ Periode::label($b, false) }}&#10;Total {{ Format::rp($c['total']) }} dari limit {{ Format::rp($c['limit']) }} ({{ Format::persen($c['persen']) }})&#10;{{ implode(', ', $c['kegiatan']) }}"
                                     class="rounded-md px-1.5 py-1 text-center text-[11px] leading-tight tabular ring-1 ring-inset {{ HonorService::warna($c['status']) }} {{ $bulan === $b ? 'outline outline-2 outline-bps-blue' : '' }}">
                                    {{ Format::jt($c['total']) }}
                                    @if ($c['total'] > 0)
                                        <div class="opacity-70">{{ round($c['persen']) }}%</div>
                                    @endif
                                </div>
                            </td>
                        @endforeach
                        <td class="px-3 py-2 text-right tabular text-slate-700">{{ Format::rp($r['total_tahun']) }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">@include('livewire.sibeban.partials.badge', ['status' => $r['fokus'], 'persen' => $r['fokus'] === 'kosong' ? null : $r['persen']])</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($bulans) + 3 }}" class="px-3 py-10 text-center text-slate-400">Tidak ada mitra yang cocok dengan filter.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Paginasi --}}
    <div class="mt-3 flex items-center justify-between text-sm text-slate-500">
        <div>{{ Format::angka($total) }} mitra · halaman {{ $halaman }} / {{ $jumlahHalaman }}</div>
        <div class="flex items-center gap-2">
            <select wire:model.live="perHalaman" class="rounded-lg bg-white px-2 py-1.5 ring-1 ring-slate-200">
                @foreach ([25, 50, 100, 500] as $n)<option value="{{ $n }}">{{ $n }} / hal</option>@endforeach
            </select>
            <button wire:click="previousPage" @disabled($halaman <= 1) class="rounded-lg bg-white px-3 py-1.5 ring-1 ring-slate-200 disabled:opacity-40">‹ Sebelumnya</button>
            <button wire:click="nextPage" @disabled($halaman >= $jumlahHalaman) class="rounded-lg bg-white px-3 py-1.5 ring-1 ring-slate-200 disabled:opacity-40">Berikutnya ›</button>
        </div>
    </div>
</div>
