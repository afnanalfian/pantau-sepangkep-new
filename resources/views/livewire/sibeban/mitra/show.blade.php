@php
    use App\Services\Sibeban\HonorService;
    use App\Support\Sibeban\Format;
    use App\Support\Sibeban\Periode;
@endphp
<div class="space-y-5">
    @include('livewire.sibeban.partials.nav')
    @include('livewire.sibeban.partials.flash')

    <div class="rounded-xl bg-white p-5 ring-1 ring-slate-200">
        <a href="{{ route('sibeban.monitoring') }}" wire:navigate class="text-xs text-slate-500 hover:text-bps-blue-700">‹ Monitoring</a>
        <div class="mt-1 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-900">{{ $mitra->nama }}</h1>
                <div class="text-sm text-slate-500 tabular">ID SOBAT {{ $mitra->sobat_id }}@if ($mitra->posisi) · {{ $mitra->posisi }}@endif @if ($mitra->email) · {{ $mitra->email }}@endif</div>
            </div>
            <div class="flex items-center gap-2 text-sm">
                <button wire:click="$set('tahun', {{ $tahun - 1 }})" class="rounded-lg px-2 py-1 ring-1 ring-slate-200">‹</button>
                <span class="font-medium tabular">{{ $tahun }}</span>
                <button wire:click="$set('tahun', {{ $tahun + 1 }})" class="rounded-lg px-2 py-1 ring-1 ring-slate-200">›</button>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-3 sm:grid-cols-6 lg:grid-cols-12 gap-2">
            @foreach ($bulans as $b)
                @php $c = $sel[$b]; @endphp
                <div class="rounded-lg p-2 text-center ring-1 ring-inset {{ HonorService::warna($c['status']) }}">
                    <div class="text-[11px] font-medium uppercase opacity-70">{{ substr(Periode::label($b), 0, 3) }}</div>
                    <div class="text-sm font-semibold tabular">{{ Format::jt($c['total']) }}</div>
                    <div class="text-[11px] tabular opacity-70">{{ $c['total'] ? round($c['persen']).'% · sisa '.Format::jt(max(0, $c['limit'] - $c['total'])) : '' }}</div>
                </div>
            @endforeach
        </div>
        <div class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900">
            <span class="font-medium">Honor berjalan (rekap Excel):</span>
            @forelse ($rekap as $r)
                <span class="ml-2 tabular" title="{{ $r->sumber }} · per {{ $r->updated_at->format('d/m/Y H:i') }}">{{ Periode::label($r->bulan) }} {{ Format::jt($r->jumlah) }}</span>
            @empty
                <span class="ml-1 text-amber-700">belum ada rekap di tahun ini</span>
            @endforelse
            <div class="mt-1 text-amber-700">Total per bulan = rekap ini + kegiatan yang ditambahkan lewat aplikasi setelah rekap diunggah.</div>
        </div>
        <div class="mt-3">@include('livewire.sibeban.partials.legenda')</div>
    </div>

    {{-- Tugaskan ke kegiatan --}}
    <div class="rounded-xl bg-white p-5 ring-1 ring-slate-200">
        <h2 class="font-semibold text-slate-900">Tugaskan ke kegiatan</h2>
        <div class="mt-3 flex flex-wrap items-end gap-3 text-sm">
            <label>
                <span class="text-slate-600">Kegiatan</span>
                <select wire:model.live="kegiatanId" class="mt-1 block w-96 max-w-full rounded-lg px-3 py-2 ring-1 ring-slate-300">
                    <option value="">— pilih kegiatan —</option>
                    @foreach ($pilihanKegiatan as $k)
                        <option value="{{ $k->id }}">{{ $k->nama }} ({{ $k->tanggal_mulai->format('m/Y') }}–{{ $k->tanggal_selesai->format('m/Y') }})</option>
                    @endforeach
                </select>
                @error('kegiatanId')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
            </label>
            <label>
                <span class="text-slate-600">Jabatan</span>
                <select wire:model.live="jabatan" class="mt-1 block rounded-lg px-3 py-2 ring-1 ring-slate-300">
                    <option value="ppl">PPL</option><option value="pml">PML</option>
                </select>
            </label>
            @if ($simulasi)
                <button wire:click="tugaskan" class="rounded-lg bg-bps-blue-700 px-4 py-2 font-medium text-white hover:bg-bps-blue-700">Tugaskan</button>
            @endif
        </div>

        @if ($simulasi)
            <div class="mt-4 flex flex-wrap items-center gap-2 text-sm">
                <span class="text-slate-500">Perkiraan setelah ditugaskan (honor default {{ Format::rp($kegiatanPilihan->honorDefault($jabatan)) }}/bln):</span>
                @include('livewire.sibeban.partials.badge', ['status' => $simulasi['status'], 'persen' => $simulasi['persen_maks']])
            </div>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($simulasi['bulan'] as $b => $c)
                    <div class="rounded-lg px-3 py-1.5 text-xs tabular ring-1 ring-inset {{ HonorService::warna($c['status']) }}">
                        <span class="font-medium">{{ Periode::label($b) }}</span>
                        {{ Format::jt($c['lain']) }} + {{ Format::jt($c['baru']) }} = <b>{{ Format::jt($c['total']) }}</b> ({{ round($c['persen']) }}%)
                    </div>
                @endforeach
            </div>
            @if ($simulasi['status'] === 'merah')
                <p class="mt-2 text-sm text-red-700">Akan melebihi limit. Lepaskan dari kegiatan lain di bawah ini dulu, atau sesuaikan honornya setelah ditugaskan.</p>
            @endif
        @endif
    </div>

    {{-- Riwayat penugasan --}}
    <div class="rounded-xl bg-white ring-1 ring-slate-200">
        <div class="border-b border-line px-5 py-3 font-semibold text-slate-900">Penugasan ({{ $penugasans->count() }})</div>
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-2 text-left font-medium">Kegiatan</th>
                    <th class="px-4 py-2 text-left font-medium">Jabatan</th>
                    <th class="px-4 py-2 text-left font-medium">Honor per bulan</th>
                    <th class="px-4 py-2 text-right font-medium">Total</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($penugasans as $p)
                    <tr wire:key="mp-{{ $p->id }}">
                        <td class="px-4 py-2">
                            <a href="{{ route('sibeban.kegiatan.show', $p->kegiatan) }}" wire:navigate class="font-medium hover:text-bps-blue-700">{{ $p->kegiatan->nama }}</a>
                            <div class="text-xs text-slate-400">{{ $p->kegiatan->jenis }} · {{ $p->kegiatan->tanggal_mulai->format('d/m/Y') }}–{{ $p->kegiatan->tanggal_selesai->format('d/m/Y') }}</div>
                        </td>
                        <td class="px-4 py-2 uppercase text-xs font-semibold text-slate-600">{{ $p->jabatan }}</td>
                        <td class="px-4 py-2">
                            <div class="flex flex-wrap gap-1">
                                @foreach ($p->honors->sortBy('bulan') as $h)
                                    <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs tabular {{ str_starts_with($h->bulan, (string) $tahun) ? '' : 'opacity-50' }}">{{ Periode::label($h->bulan) }}: {{ Format::jt($h->jumlah) }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-4 py-2 text-right tabular">{{ Format::rp($p->honors->sum('jumlah')) }}</td>
                        <td class="px-4 py-2 text-right">
                            <button wire:click="lepas({{ $p->id }})"
                                    wire:confirm="Lepas {{ $mitra->nama }} dari &quot;{{ $p->kegiatan->nama }}&quot;? Honornya di kegiatan itu ikut terhapus."
                                    class="rounded-md px-2 py-0.5 text-xs text-red-700 ring-1 ring-red-200 hover:bg-red-50">Lepas</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">Belum pernah ditugaskan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
