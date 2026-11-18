@php
    use App\Services\Sibeban\HonorService;
    use App\Support\Sibeban\Format;
    use App\Support\Sibeban\Periode;
    $k = $kegiatan;
@endphp
<div class="space-y-5">
    @include('livewire.sibeban.partials.nav')
    @include('livewire.sibeban.partials.flash')

    {{-- ===================== Header kegiatan ===================== --}}
    <div class="rounded-xl bg-white p-5 ring-1 ring-slate-200">
        <a href="{{ route('sibeban.kegiatan.index') }}" wire:navigate class="text-xs text-slate-500 hover:text-bps-blue-700">‹ Semua kegiatan</a>
        <div class="mt-1 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold text-slate-900">{{ $k->nama }}</h1>
                <div class="mt-1 text-sm text-slate-500">
                    {{ $k->jenis }}@if ($k->tim) · Tim {{ $k->tim }}@endif ·
                    {{ $k->tanggal_mulai->translatedFormat('d M Y') }} – {{ $k->tanggal_selesai->translatedFormat('d M Y') }}
                    ({{ implode(', ', array_map(fn ($b) => Periode::label($b), $bulans)) }})
                </div>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-x-6 gap-y-1 text-sm">
                <div><div class="text-xs text-slate-400">Honor PPL / bln</div><div class="tabular font-medium">{{ Format::rp($k->honor_ppl) }}</div></div>
                <div><div class="text-xs text-slate-400">Honor PML / bln</div><div class="tabular font-medium">{{ Format::rp($k->honor_pml) }}</div></div>
                <div><div class="text-xs text-slate-400">Limit PPL / PML</div><div class="tabular font-medium">{{ Format::jt($limitPpl) }} / {{ Format::jt($limitPml) }}</div></div>
                <div><div class="text-xs text-slate-400">Total honor kegiatan</div><div class="tabular font-medium">{{ Format::rp($totalHonor) }}</div></div>
            </div>
        </div>
    </div>

    {{-- ===================== Upload ===================== --}}
    <div class="rounded-xl bg-white p-5 ring-1 ring-slate-200">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-slate-900">Unggah petugas dari Excel</h2>
            <a href="{{ route('sibeban.kegiatan.template', $k) }}" class="text-sm text-bps-blue-700 hover:underline">⤓ Unduh template sesuai periode</a>
        </div>
        <p class="mt-1 text-sm text-slate-500">
            Isi file: daftar calon petugas beserta <b>honor berjalan</b> mereka (honor yang sedang diterima dari kegiatan lain) per bulan.
            Kolom: <code class="rounded bg-slate-100 px-1">sobat_id</code>, <code class="rounded bg-slate-100 px-1">nama</code> (opsional),
            <code class="rounded bg-slate-100 px-1">jabatan</code> (ppl/pml), lalu kolom bulan (Jan 2026 … Des 2026).
            Hanya bulan dalam periode kegiatan yang diambil; sel kosong = 0.
        </p>
        <div class="mt-2 inline-flex flex-wrap items-center gap-2 rounded-lg bg-bps-blue-50 px-3 py-2 text-sm text-navy-900">
            <span class="font-medium">Rumus per bulan:</span>
            honor berjalan (Excel) + honor kegiatan ini (PPL {{ Format::rp($k->honor_ppl) }} / PML {{ Format::rp($k->honor_pml) }})
            dibandingkan limit {{ Format::rp($limitPpl) }}{{ $limitPml !== $limitPpl ? ' / '.Format::rp($limitPml) : '' }}
        </div>

        <form wire:submit="pratinjauUpload" class="mt-4 flex flex-wrap items-end gap-4">
            <label class="text-sm">
                <span class="text-slate-600">File (.xlsx / .xls / .csv)</span>
                <input type="file" wire:model="file" accept=".xlsx,.xls,.csv"
                       class="mt-1 block text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm hover:file:bg-slate-200">
                @error('file')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
            </label>
            <button type="submit" class="rounded-lg bg-bps-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-bps-blue-700 disabled:opacity-50" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="pratinjauUpload,file">Hitung &amp; pratinjau</span>
                <span wire:loading wire:target="pratinjauUpload,file">Memproses…</span>
            </button>
        </form>

        @foreach ($uploadErrors as $e)
            <div class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $e }}</div>
        @endforeach
        @foreach ($uploadWarnings as $w)
            <div class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">{{ $w }}</div>
        @endforeach
    </div>

    {{-- ===================== Pratinjau ===================== --}}
    @if ($pratinjau)
        @php $pr = collect($pratinjau)->countBy(fn ($r) => $r['calc']['status']); @endphp
        <div class="rounded-xl bg-white ring-2 ring-bps-blue/50">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-3">
                <div>
                    <h2 class="font-semibold text-slate-900">Pratinjau hasil kalkulasi · {{ count($pratinjau) }} petugas</h2>
                    <p class="text-xs text-slate-500">Angka besar = total. Angka kecil = honor berjalan (Excel) + honor kegiatan ini. Belum tersimpan.</p>
                </div>
                <div class="flex items-center gap-3 text-sm">
                    @foreach (['merah', 'oranye', 'hijau', 'kosong'] as $s)
                        @if ($pr[$s] ?? 0)
                            <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full {{ HonorService::titik($s) }}"></span>{{ $pr[$s] }}</span>
                        @endif
                    @endforeach
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">Petugas</th>
                            <th class="px-3 py-2 text-left font-medium">Jabatan</th>
                            @foreach ($bulans as $b)
                                <th class="px-2 py-2 text-center font-medium">{{ Periode::label($b) }}</th>
                            @endforeach
                            <th class="px-3 py-2 text-left font-medium">Status</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($pratinjau as $i => $r)
                            <tr wire:key="pr-{{ $r['sobat_id'] }}">
                                <td class="px-3 py-2">
                                    <div class="font-medium text-slate-900">{{ $r['nama_db'] }}</div>
                                    <div class="text-xs text-slate-400 tabular">
                                        {{ $r['sobat_id'] }} · baris {{ $r['baris'] }}
                                        @if ($r['mitra_baru'])<span class="ml-1 rounded bg-violet-100 px-1 text-violet-700">mitra baru</span>@endif
                                        @if ($r['sudah'])<span class="ml-1 rounded bg-slate-100 px-1 text-slate-600">diperbarui</span>@endif
                                    </div>
                                </td>
                                <td class="px-3 py-2 uppercase text-xs font-semibold text-slate-600">{{ $r['jabatan'] }}</td>
                                @foreach ($bulans as $b)
                                    @php $c = $r['calc']['bulan'][$b]; @endphp
                                    <td class="px-1 py-1.5">
                                        <div class="rounded-md px-2 py-1 text-center text-[11px] leading-tight tabular ring-1 ring-inset {{ HonorService::warna($c['status']) }}"
                                             title="Honor berjalan {{ Format::rp($c['lain']) }} + kegiatan ini {{ Format::rp($c['baru']) }} = {{ Format::rp($c['total']) }} / limit {{ Format::rp($c['limit']) }} ({{ Format::persen($c['persen']) }})">
                                            <div class="font-semibold">{{ Format::jt($c['total']) }}</div>
                                            <div class="opacity-70">{{ Format::jt($c['lain']) }} + {{ Format::jt($c['baru']) }}</div>
                                        </div>
                                    </td>
                                @endforeach
                                <td class="px-3 py-2 whitespace-nowrap">@include('livewire.sibeban.partials.badge', ['status' => $r['calc']['status'], 'persen' => $r['calc']['persen_maks']])</td>
                                <td class="px-3 py-2 text-right whitespace-nowrap text-xs">
                                    <button wire:click="buangDariPratinjau({{ $i }})" class="text-slate-500 hover:text-red-600" title="Jangan ikutkan petugas ini">Buang</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-line px-5 py-3">
                <button wire:click="batalPratinjau" class="rounded-lg px-4 py-2 text-sm ring-1 ring-slate-300">Batal</button>
                @if ($pr['merah'] ?? 0)
                    <button wire:click="buangMerahDariPratinjau" class="rounded-lg px-4 py-2 text-sm text-red-700 ring-1 ring-red-300 hover:bg-red-50">Buang {{ $pr['merah'] }} petugas merah</button>
                @endif
                <button wire:click="simpanPratinjau" wire:loading.attr="disabled" class="rounded-lg bg-bps-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-bps-blue-700">
                    Simpan {{ count($pratinjau) }} petugas ke kegiatan
                </button>
            </div>
        </div>
    @endif

    {{-- ===================== Daftar petugas ===================== --}}
    <div class="rounded-xl bg-white ring-1 ring-slate-200">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-3">
            <h2 class="font-semibold text-slate-900">Petugas kegiatan · {{ $jumlahPetugas }}</h2>
            <div class="flex flex-wrap items-center gap-2 text-sm">
                @foreach (['merah' => 'Melebihi', 'oranye' => 'Mendekati', 'hijau' => 'Aman'] as $s => $label)
                    <button wire:click="filter('{{ $s }}')"
                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 ring-1 {{ $filterStatus === $s ? 'ring-2 ring-bps-blue-600 bg-bps-blue-50' : 'ring-slate-200' }}">
                        <span class="h-2 w-2 rounded-full {{ HonorService::titik($s) }}"></span>{{ $label }} {{ $ringkas[$s] }}
                    </button>
                @endforeach
                <input type="search" wire:model.live.debounce.300ms="cari" placeholder="Cari petugas…" class="w-48 rounded-lg px-3 py-1.5 ring-1 ring-slate-200">
                <button wire:click="$toggle('rekTampil')" class="rounded-lg bg-emerald-600 px-3 py-1.5 font-medium text-white hover:bg-emerald-700">
                    {{ $rekTampil ? 'Tutup rekomendasi' : '★ Rekomendasi petugas' }}
                </button>
            </div>
        </div>

        {{-- Rekomendasi --}}
        @if ($rekTampil)
            <div class="border-b border-line bg-emerald-50/40 px-5 py-4">
                <div class="flex flex-wrap items-center gap-3 text-sm">
                    <span class="font-medium text-emerald-900">Mitra paling longgar limitnya bila ditambahkan dengan honor default</span>
                    <select wire:model.live="rekJabatan" class="rounded-lg bg-white px-2 py-1.5 ring-1 ring-slate-200">
                        <option value="ppl">sebagai PPL ({{ Format::rp($k->honor_ppl) }}/bln)</option>
                        <option value="pml">sebagai PML ({{ Format::rp($k->honor_pml) }}/bln)</option>
                    </select>
                    <input type="search" wire:model.live.debounce.300ms="rekCari" placeholder="Cari mitra…" class="w-48 rounded-lg bg-white px-3 py-1.5 ring-1 ring-slate-200">
                    <label class="inline-flex items-center gap-1.5 text-slate-600"><input type="checkbox" wire:model.live="rekSembunyikanMerah"> sembunyikan yang akan melebihi limit</label>
                </div>
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-2 py-1.5 text-left font-medium">Mitra</th>
                                @foreach ($bulans as $b)<th class="px-2 py-1.5 text-center font-medium">{{ Periode::label($b) }}</th>@endforeach
                                <th class="px-2 py-1.5 text-left font-medium">Setelah ditambah</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-emerald-100">
                            @forelse ($rekomendasi as $r)
                                <tr wire:key="rek-{{ $r['mitra']['id'] }}">
                                    <td class="px-2 py-1.5">
                                        <a href="{{ route('sibeban.mitra.show', $r['mitra']['id']) }}" wire:navigate class="font-medium hover:text-bps-blue-700">{{ $r['mitra']['nama'] }}</a>
                                        <div class="text-xs text-slate-400 tabular">{{ $r['mitra']['sobat_id'] }} · {{ $r['jumlah_kegiatan_lain'] }} kegiatan lain di periode ini</div>
                                    </td>
                                    @foreach ($bulans as $b)
                                        @php $c = $r['calc']['bulan'][$b]; @endphp
                                        <td class="px-1 py-1">
                                            <div class="rounded-md px-2 py-1 text-center text-[11px] tabular ring-1 ring-inset {{ HonorService::warna($c['status']) }}"
                                                 title="Sekarang {{ Format::rp($c['lain']) }} → {{ Format::rp($c['total']) }} / limit {{ Format::rp($c['limit']) }}">
                                                {{ round($c['persen']) }}% · sisa {{ Format::jt(max(0, $c['sisa'])) }}
                                            </div>
                                        </td>
                                    @endforeach
                                    <td class="px-2 py-1.5 whitespace-nowrap">@include('livewire.sibeban.partials.badge', ['status' => $r['calc']['status'], 'persen' => $r['calc']['persen_maks']])</td>
                                    <td class="px-2 py-1.5 text-right">
                                        <button wire:click="tambahPetugas({{ $r['mitra']['id'] }}, '{{ $rekJabatan }}')" class="rounded-lg bg-white px-3 py-1 text-xs font-medium text-emerald-700 ring-1 ring-emerald-300 hover:bg-emerald-50">
                                            + Tambah {{ strtoupper($rekJabatan) }}
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="{{ count($bulans) + 3 }}" class="px-2 py-6 text-center text-slate-400">Tidak ada mitra yang memenuhi.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium">Petugas</th>
                        <th class="px-3 py-2 text-left font-medium">Jabatan</th>
                        @foreach ($bulans as $b)
                            <th class="px-2 py-2 text-center font-medium">{{ Periode::label($b) }}</th>
                        @endforeach
                        <th class="px-3 py-2 text-left font-medium">Status</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($petugas as $row)
                        @php $p = $row['p']; @endphp
                        <tr wire:key="p-{{ $p->id }}" class="{{ $aturId === $p->id ? 'bg-bps-blue-50/50' : 'hover:bg-slate-50/60' }}">
                            <td class="px-3 py-2">
                                <a href="{{ route('sibeban.mitra.show', $p->mitra) }}" wire:navigate class="font-medium text-slate-900 hover:text-bps-blue-700">{{ $p->mitra->nama }}</a>
                                <div class="text-xs text-slate-400 tabular">{{ $p->mitra->sobat_id }}</div>
                            </td>
                            <td class="px-3 py-2 uppercase text-xs font-semibold text-slate-600">{{ $p->jabatan }}</td>
                            @foreach ($bulans as $b)
                                @php $c = $row['calc']['bulan'][$b]; @endphp
                                <td class="px-1 py-1.5">
                                    <div class="rounded-md px-2 py-1 text-center text-[11px] leading-tight tabular ring-1 ring-inset {{ HonorService::warna($c['status']) }}"
                                         title="Honor berjalan {{ Format::rp($c['lain']) }} + kegiatan ini {{ Format::rp($c['baru']) }} = {{ Format::rp($c['total']) }} / limit {{ Format::rp($c['limit']) }} ({{ Format::persen($c['persen']) }})">
                                        <div class="font-semibold">{{ Format::jt($c['total']) }}</div>
                                        <div class="opacity-70">{{ Format::jt($c['lain']) }} + {{ Format::jt($c['baru']) }}</div>
                                    </div>
                                </td>
                            @endforeach
                            <td class="px-3 py-2 whitespace-nowrap">@include('livewire.sibeban.partials.badge', ['status' => $row['calc']['status'], 'persen' => $row['calc']['persen_maks']])</td>
                            <td class="px-3 py-2 text-right whitespace-nowrap text-xs">
                                <button wire:click="atur({{ $p->id }})" class="text-bps-blue-700 hover:underline">{{ $aturId === $p->id ? 'Tutup' : 'Atur' }}</button>
                                <button wire:click="lepas({{ $p->id }})" wire:confirm="Keluarkan {{ $p->mitra->nama }} dari kegiatan ini?" class="ml-2 text-red-600 hover:underline">Keluarkan</button>
                            </td>
                        </tr>
                        @if ($aturId === $p->id)
                            <tr wire:key="p-d-{{ $p->id }}" class="bg-bps-blue-50/50">
                                <td colspan="{{ count($bulans) + 4 }}" class="px-5 py-4">
                                    <div class="grid gap-5 lg:grid-cols-2">
                                        <form wire:submit="simpanEdit" class="rounded-lg bg-white p-4 ring-1 ring-slate-200">
                                            <div class="mb-3 font-medium text-slate-800">Honor di kegiatan ini</div>
                                            <div class="flex flex-wrap items-end gap-3 text-sm">
                                                <label>
                                                    <span class="text-xs text-slate-500">Jabatan</span>
                                                    <select wire:model="editJabatan" class="mt-1 block rounded-lg px-2 py-1.5 ring-1 ring-slate-300">
                                                        <option value="ppl">PPL</option><option value="pml">PML</option>
                                                    </select>
                                                </label>
                                                @foreach ($editHonor as $b => $v)
                                                    <label>
                                                        <span class="text-xs text-slate-500">{{ Periode::label($b) }}</span>
                                                        <input type="number" min="0" step="1000" wire:model="editHonor.{{ $b }}" class="mt-1 block w-32 rounded-lg px-2 py-1.5 tabular ring-1 ring-slate-300">
                                                    </label>
                                                @endforeach
                                            </div>
                                            @error('editHonor.*')<div class="mt-1 text-xs text-red-600">{{ $message }}</div>@enderror
                                            <div class="mt-4 mb-1 text-xs font-medium text-slate-600">Honor berjalan (rekap) — ubah bila ada perubahan, mis. petugas sudah selesai/dilepas dari kegiatan lain</div>
                                            <div class="flex flex-wrap items-end gap-3 text-sm">
                                                @foreach ($editBerjalan as $b => $v)
                                                    <label>
                                                        <span class="text-xs text-slate-500">{{ Periode::label($b) }}</span>
                                                        <input type="number" min="0" step="1000" wire:model="editBerjalan.{{ $b }}" class="mt-1 block w-32 rounded-lg bg-amber-50 px-2 py-1.5 tabular ring-1 ring-amber-200">
                                                    </label>
                                                @endforeach
                                            </div>
                                            @error('editBerjalan.*')<div class="mt-1 text-xs text-red-600">{{ $message }}</div>@enderror
                                            <div class="mt-3 flex gap-2">
                                                <button type="submit" class="rounded-lg bg-bps-blue-700 px-3 py-1.5 text-sm font-medium text-white">Simpan</button>
                                                <button type="button" wire:click="isiDefault" class="rounded-lg px-3 py-1.5 text-sm ring-1 ring-slate-300">Isi honor default</button>
                                            </div>
                                        </form>
                                        <div class="rounded-lg bg-white p-4 ring-1 ring-slate-200">
                                            @include('livewire.kegiatan.partials.kegiatan-lain', ['lain' => $row['lain'], 'bulans' => $bulans, 'nama' => $p->mitra->nama])
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="{{ count($bulans) + 4 }}" class="px-3 py-10 text-center text-slate-400">
                            {{ $jumlahPetugas ? 'Tidak ada petugas yang cocok dengan filter.' : 'Belum ada petugas. Unggah file Excel atau pakai Rekomendasi petugas.' }}
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
