@php use App\Services\Sibeban\HonorService; use App\Support\Sibeban\Format; use App\Support\Sibeban\Periode; @endphp
<div>
    @include('livewire.sibeban.partials.nav')

    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('sibeban.mitra.index') }}" class="mb-3 inline-flex items-center gap-1 text-sm font-medium text-navy-500 hover:text-navy-900"><x-icon name="chevron-left" class="size-4" /> Kembali</a>

    <div class="card mb-4 flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
        <div class="flex items-center gap-3">
            <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-bps-blue-50 text-lg font-extrabold text-bps-blue-700">{{ mb_substr($mitra->nama, 0, 1) }}</span>
            <div class="min-w-0">
                <h1 class="text-xl font-extrabold tracking-tight">{{ $mitra->nama }}</h1>
                <p class="text-sm text-navy-500">Sobat ID {{ $mitra->sobat_id }}{{ $mitra->posisi ? ' · '.$mitra->posisi : '' }}</p>
                <p class="text-sm text-navy-500">{{ $mitra->wilayah() ?: 'Wilayah belum diisi' }}</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <label class="text-sm text-navy-500">Tahun</label>
            <select wire:model.live="tahun" class="input !w-auto">
                @foreach (range(now()->year + 1, now()->year - 3) as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach
            </select>
        </div>
    </div>

    <p class="mb-3 text-sm text-navy-500">Honor per bulan dibandingkan limit bulanan <b class="text-navy-900">{{ Format::rp($limit) }}</b>. Setiap bulan dihitung terpisah.</p>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($bulans as $b)
            @php $c = $sel[$b]; @endphp
            <div class="card p-4 {{ $b === $bulanIni ? 'ring-2 ring-bps-blue/30' : '' }}" wire:key="b-{{ $b }}">
                <div class="flex items-center justify-between">
                    <p class="font-bold">{{ Periode::label($b, false) }} @if ($b === $bulanIni)<span class="chip ml-1 bg-bps-blue-50 text-bps-blue-700 ring-bps-blue-100">bulan ini</span>@endif</p>
                    <span class="chip {{ HonorService::warna($c['status']) }}">{{ HonorService::LABEL[$c['status']] }}</span>
                </div>
                <div class="mt-3 flex items-end justify-between tabular-nums">
                    <div><p class="text-xs text-navy-500">Honor</p><p class="text-lg font-extrabold">{{ Format::rp($c['total']) }}</p></div>
                    <div class="text-right"><p class="text-xs text-navy-500">Sisa limit</p><p class="font-bold {{ $c['sisa'] <= 0 ? 'text-rose-600' : 'text-bps-green-700' }}">{{ Format::rp($c['sisa']) }}</p></div>
                </div>
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-navy-50"><div class="h-full rounded-full {{ HonorService::titik($c['status']) }}" style="width: {{ min(100, $c['persen']) }}%"></div></div>
                @if ($c['rincian'])
                    <ul class="mt-3 space-y-1 border-t border-line pt-2 text-xs">
                        @foreach ($c['rincian'] as $r)
                            <li class="flex justify-between gap-2">
                                <span class="min-w-0 truncate">
                                    @if ($r['kegiatan_id'])<a href="{{ route('sibeban.kegiatan.show', $r['kegiatan_id']) }}" wire:navigate class="hover:text-bps-blue-700">{{ $r['kegiatan'] }}</a>@else<span class="text-navy-500">{{ $r['kegiatan'] }}</span>@endif
                                    @if ($r['jabatan'])<span class="text-navy-400"> · {{ $r['jabatan'] }}</span>@endif
                                </span>
                                <span class="shrink-0 tabular-nums">{{ Format::rp($r['jumlah']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endforeach
    </div>
</div>
