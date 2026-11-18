@php
    use App\Services\Sibeban\HonorService;
    use App\Support\Sibeban\Format;
    use App\Support\Sibeban\Periode;
    $statusKartu = [
        'belum' => ['Belum diisi honor', 'text-navy-700', 'bg-navy-50'],
        'hijau' => ['Aman', 'text-bps-green-700', 'bg-bps-green-50'],
        'oranye' => ['Mendekati limit', 'text-bps-orange-700', 'bg-bps-orange-50'],
        'merah' => ['Melebihi limit', 'text-rose-700', 'bg-rose-50'],
    ];
@endphp
<div>
    @include('livewire.sibeban.partials.nav')
    @include('livewire.sibeban.partials.flash')

    <a href="{{ route('sibeban.kegiatan.index') }}" wire:navigate class="mb-3 inline-flex items-center gap-1 text-sm font-medium text-navy-500 hover:text-navy-900"><x-icon name="chevron-left" class="size-4" /> Semua kegiatan</a>

    <div class="card mb-4 p-4 sm:p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <h1 class="text-xl font-extrabold tracking-tight sm:text-2xl">{{ $kegiatan->nama }}</h1>
                <p class="mt-1 text-sm text-navy-500">
                    {{ $kegiatan->tim ? 'Tim '.$kegiatan->tim.' · ' : '' }}{{ $kegiatan->tanggal_mulai->translatedFormat('j F Y') }} – {{ $kegiatan->tanggal_selesai->translatedFormat('j F Y') }}
                    · {{ $kegiatan->pembuat ? 'dibuat '.$kegiatan->pembuat : 'impor matriks' }}
                </p>
                @if ($kegiatan->keterangan)<p class="mt-2 text-sm text-navy-700">{{ $kegiatan->keterangan }}</p>@endif
            </div>
            @if ($bisa)
                <div class="flex flex-wrap gap-2">
                    <button wire:click="bukaPilih" class="btn btn-blue"><x-icon name="users" class="size-4" /> Pilih petugas</button>
                    <a href="{{ route('sibeban.kegiatan.template', $kegiatan) }}" class="btn btn-ghost {{ $jumlahPetugas ? '' : 'pointer-events-none opacity-50' }}"><x-icon name="download" class="size-4" /> Unduh template</a>
                    <button wire:click="bukaImpor" class="btn btn-green" @disabled(! $jumlahPetugas)><x-icon name="upload" class="size-4" /> Impor honor</button>
                </div>
            @endif
        </div>

        @if ($bisa && ($jumlahPetugas === 0 || $ringkas['belum'] === $jumlahPetugas))
            <ol class="mt-5 grid gap-2 border-t border-line pt-4 text-sm sm:grid-cols-4">
                @foreach ([
                    ['Pilih petugas', 'dari database mitra, bisa difilter per kecamatan & desa', $jumlahPetugas > 0],
                    ['Unduh template', 'Sobat ID & nama petugas terpilih sudah terisi', false],
                    ['Isi jabatan & honor', 'honor tiap petugas per bulan, rupiah penuh', false],
                    ['Impor honor', 'periksa pratinjau status limit, lalu simpan', false],
                ] as $i => [$judul, $ket, $selesai])
                    <li class="flex gap-2.5 rounded-xl bg-paper p-3">
                        <span class="grid size-6 shrink-0 place-items-center rounded-full text-xs font-bold {{ $selesai ? 'bg-bps-green text-white' : 'bg-white text-navy-700 ring-1 ring-line' }}">@if ($selesai)<x-icon name="check" class="size-3.5" />@else{{ $i + 1 }}@endif</span>
                        <span><b class="block text-navy-900">{{ $judul }}</b><span class="text-xs text-navy-500">{{ $ket }}</span></span>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>

    <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <div class="card p-3.5"><p class="text-xs text-navy-500">Petugas</p><p class="mt-1 text-2xl font-extrabold tabular-nums">{{ $jumlahPetugas }}</p></div>
        @foreach ($statusKartu as $k => [$label, $warna, $bg])
            <button wire:click="filter('{{ $k }}')" class="card p-3.5 text-left transition hover:border-navy-300 {{ $filterStatus === $k ? '!border-navy-900 ring-2 ring-navy-900/10' : '' }}">
                <p class="text-xs text-navy-500">{{ $label }}</p>
                <p class="mt-1 text-2xl font-extrabold tabular-nums {{ $warna }}">{{ $ringkas[$k] }}</p>
            </button>
        @endforeach
        <div class="card p-3.5"><p class="text-xs text-navy-500">Total honor</p><p class="mt-1 text-base font-extrabold tabular-nums">{{ Format::rp($totalHonor) }}</p></div>
    </div>

    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <div class="relative w-full sm:w-80">
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-navy-300" />
            <input type="search" wire:model.live.debounce.300ms="cari" placeholder="Cari petugas, jabatan, wilayah…" class="input !pl-9">
        </div>
        <p class="text-xs text-navy-500">Limit honor bulanan per mitra: <b class="text-navy-900">{{ Format::rp($limit) }}</b></p>
    </div>

    <div class="card overflow-x-auto">
        <table class="w-full min-w-[720px] text-sm">
            <thead class="table-head">
                <tr>
                    <th class="px-4 py-2.5">Petugas</th>
                    <th class="px-4 py-2.5">Jabatan</th>
                    @foreach ($bulans as $b)
                        <th class="px-4 py-2.5 text-right">{{ Periode::label($b, count($bulans) > 2) }}<span class="block font-normal normal-case text-navy-400">honor ini · total semua kegiatan</span></th>
                    @endforeach
                    @if ($bisa)<th class="w-20 px-4 py-2.5"></th>@endif
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($petugas as $row)
                    @php $p = $row['p']; $m = $p->mitra; @endphp
                    <tr wire:key="p-{{ $p->id }}" class="align-top hover:bg-navy-50/40">
                        <td class="px-4 py-3">
                            <a href="{{ route('sibeban.mitra.show', $m) }}" wire:navigate class="font-semibold hover:text-bps-blue-700">{{ $m->nama }}</a>
                            <div class="text-xs text-navy-500">{{ $m->sobat_id }}</div>
                            <div class="text-xs text-navy-400">{{ $m->wilayah() }}</div>
                        </td>
                        <td class="px-4 py-3">
                            @if ($p->jabatan)<span class="chip bg-bps-blue-50 text-bps-blue-700 ring-bps-blue-100">{{ $p->jabatan }}</span>@else<span class="text-xs text-navy-400">belum diisi</span>@endif
                        </td>
                        @foreach ($bulans as $b)
                            @php $c = $row['calc']['bulan'][$b]; @endphp
                            <td class="px-4 py-3 text-right tabular-nums">
                                @if ($row['belum'])
                                    <span class="text-xs text-navy-400">–</span>
                                    @if ($c['lain'])<div class="text-[11px] text-navy-400">kegiatan lain {{ Format::rp($c['lain']) }}</div>@endif
                                @else
                                    <div class="font-semibold">{{ Format::rp($c['ini']) }}</div>
                                    <div class="mt-0.5 inline-flex items-center gap-1.5 text-xs">
                                        <span class="size-2 rounded-full {{ HonorService::titik($c['status']) }}"></span>
                                        <span class="{{ $c['status'] === 'merah' ? 'font-semibold text-rose-600' : 'text-navy-500' }}">{{ Format::rp($c['total']) }}</span>
                                    </div>
                                    <div class="text-[11px] {{ $c['sisa'] < 0 ? 'text-rose-600' : 'text-navy-400' }}">{{ $c['sisa'] < 0 ? 'lebih '.Format::rp(-$c['sisa']) : 'sisa '.Format::rp($c['sisa']) }}</div>
                                @endif
                            </td>
                        @endforeach
                        @if ($bisa)
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-0.5">
                                    <button wire:click="ubah({{ $p->id }})" class="btn-quiet rounded-lg p-1.5" title="Ubah jabatan & honor"><x-icon name="pencil" class="size-4" /></button>
                                    <button wire:click="$set('lepasId', {{ $p->id }})" class="btn-quiet rounded-lg p-1.5 hover:!text-rose-600" title="Keluarkan dari kegiatan"><x-icon name="x" class="size-4" /></button>
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ 3 + count($bulans) }}">
                        <x-empty icon="users" title="{{ $jumlahPetugas ? 'Tidak ada yang cocok' : 'Belum ada petugas' }}" :text="$jumlahPetugas ? 'Ubah pencarian atau filter status.' : ($bisa ? 'Klik “Pilih petugas” untuk memilih mitra dari database.' : 'Pembuat kegiatan belum memilih petugas.')" />
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ================= Modal pilih petugas ================= --}}
    <x-modal :show="$pilihTampil" close="$set('pilihTampil', false)" width="max-w-4xl" title="Pilih petugas" subtitle="Centang mitra yang akan dipakai. Angka menunjukkan honor yang sudah diterima dari kegiatan lain di bulan kegiatan ini.">
        @if ($opsi)
            <div class="sticky -top-5 z-10 -mx-5 -mt-5 mb-3 border-b border-line bg-white px-5 pt-5 pb-3 sm:-mx-6 sm:px-6">
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <input type="search" wire:model.live.debounce.300ms="pCari" placeholder="Cari nama / Sobat ID…" class="input col-span-2 sm:col-span-1">
                    <select wire:model.live="pKecamatan" class="input">
                        <option value="">Semua kecamatan</option>
                        @foreach ($opsi['kecamatan'] as $k)<option value="{{ $k }}">{{ $k }}</option>@endforeach
                    </select>
                    <select wire:model.live="pDesa" class="input" @disabled($pKecamatan === '')>
                        <option value="">{{ $pKecamatan === '' ? 'Desa/kel. (pilih kec.)' : 'Semua desa/kelurahan' }}</option>
                        @foreach ($opsi['desa'] as $d)<option value="{{ $d }}">{{ $d }}</option>@endforeach
                    </select>
                    <select wire:model.live="pPosisi" class="input col-span-2 sm:col-span-1">
                        <option value="">Semua posisi</option>
                        @foreach ($opsi['posisi'] as $ps)<option value="{{ $ps }}">{{ $ps }}</option>@endforeach
                    </select>
                </div>
                <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-sm">
                    <label class="inline-flex items-center gap-2 text-navy-700"><input type="checkbox" wire:model.live="pHanyaTersedia" class="rounded border-line"> Sembunyikan mitra yang limitnya sudah penuh</label>
                    <div class="flex items-center gap-2">
                        <span class="text-navy-500">{{ $jumlahKandidat }} mitra</span>
                        <button wire:click="pilihSemua" class="btn btn-ghost btn-sm">Centang semua ({{ $jumlahKandidat }})</button>
                        @if ($pDipilih)<button wire:click="kosongkanPilihan" class="btn btn-quiet btn-sm">Kosongkan</button>@endif
                    </div>
                </div>
            </div>

            <div class="divide-y divide-line rounded-xl border border-line">
                @forelse ($kandidat as $k)
                    @php $m = $k['mitra']; $calc = $k['calc']; @endphp
                    <label wire:key="c-{{ $m->id }}" class="flex cursor-pointer items-center gap-3 px-3 py-2.5 hover:bg-navy-50/50 has-[:checked]:bg-bps-blue-50/60">
                        <input type="checkbox" value="{{ $m->id }}" wire:model.live="pDipilih" class="size-4 rounded border-line text-bps-blue">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-semibold">{{ $m->nama }}</span>
                            <span class="block truncate text-xs text-navy-500">{{ $m->sobat_id }} · {{ $m->wilayah() ?: 'wilayah belum diisi' }}{{ $m->posisi ? ' · '.$m->posisi : '' }}</span>
                        </span>
                        <span class="hidden shrink-0 gap-3 sm:flex">
                            @foreach ($calc['bulan'] as $b => $c)
                                <span class="w-36 text-right text-xs tabular-nums">
                                    <span class="block text-navy-400">{{ Periode::label($b) }}</span>
                                    <span class="inline-flex items-center gap-1"><span class="size-2 rounded-full {{ HonorService::titik($c['status']) }}"></span>{{ Format::rp($c['total']) }}</span>
                                    <span class="block {{ $c['sisa'] <= 0 ? 'text-rose-600' : 'text-bps-green-700' }}">sisa {{ Format::rp($c['sisa']) }}</span>
                                </span>
                            @endforeach
                        </span>
                        <span class="text-right text-xs tabular-nums sm:hidden {{ $calc['sisa_min'] <= 0 ? 'text-rose-600' : 'text-bps-green-700' }}">sisa<br>{{ Format::rp($calc['sisa_min']) }}</span>
                    </label>
                @empty
                    <x-empty icon="users" title="Tidak ada mitra" text="Semua mitra yang cocok sudah dipilih atau limitnya penuh. Ubah filter." />
                @endforelse
            </div>
            @if ($jumlahKandidat > count($kandidat))
                <div class="mt-3 text-center"><button wire:click="$set('pBatas', {{ $pBatas + 100 }})" class="btn btn-ghost btn-sm">Tampilkan lebih banyak ({{ $jumlahKandidat - count($kandidat) }} lagi)</button></div>
            @endif
        @endif
        <x-slot:footer>
            <span class="mr-auto text-sm text-navy-700"><b>{{ count($pDipilih) }}</b> mitra dicentang</span>
            <button wire:click="$set('pilihTampil', false)" class="btn btn-ghost">Batal</button>
            <button wire:click="simpanPilihan" class="btn btn-blue" @disabled(! $pDipilih) wire:loading.attr="disabled">Tambahkan ke kegiatan</button>
        </x-slot:footer>
    </x-modal>

    {{-- ================= Modal impor honor ================= --}}
    <x-modal :show="$imporTampil" close="$set('imporTampil', false)" width="max-w-4xl" title="Impor jabatan & honor" subtitle="Unggah template yang sudah diisi. Periksa pratinjau sebelum menyimpan.">
        @if (! $pratinjau && ! $uploadErrors)
            <label class="flex cursor-pointer flex-col items-center gap-2 rounded-2xl border-2 border-dashed border-line px-4 py-10 text-center hover:border-bps-blue hover:bg-bps-blue-50/40">
                <x-icon name="upload" class="size-8 text-bps-blue" />
                <span class="font-semibold">Pilih file template (.xlsx)</span>
                <span class="text-xs text-navy-500">Kolom: Sobat ID, Jabatan, Honor per bulan. Petugas yang belum dipilih otomatis ditambahkan bila Sobat ID-nya ada di database.</span>
                <input type="file" wire:model="file" accept=".xlsx,.xls,.csv" class="sr-only">
            </label>
            <p wire:loading wire:target="file" class="mt-3 text-center text-sm text-navy-500">Membaca file…</p>
            @error('file')<p class="error mt-2">{{ $message }}</p>@enderror
        @else
            @foreach ($uploadErrors as $e)<p class="mb-1.5 rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-700">{{ $e }}</p>@endforeach
            @foreach ($uploadWarnings as $w)<p class="mb-1.5 rounded-lg bg-bps-orange-50 px-3 py-2 text-xs text-bps-orange-700">{{ $w }}</p>@endforeach

            @if ($pratinjau)
                <div class="mt-2 overflow-x-auto rounded-xl border border-line">
                    <table class="w-full min-w-[640px] text-sm">
                        <thead class="table-head"><tr>
                            <th class="px-3 py-2">Petugas</th><th class="px-3 py-2">Jabatan</th>
                            @foreach ($bulans as $b)<th class="px-3 py-2 text-right">{{ Periode::label($b) }}<span class="block font-normal text-navy-400">honor ini · total</span></th>@endforeach
                            <th class="w-8"></th>
                        </tr></thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($pratinjau as $i => $r)
                                <tr wire:key="pr-{{ $r['mitra_id'] }}">
                                    <td class="px-3 py-2">
                                        <div class="font-semibold">{{ $r['nama_db'] }}</div>
                                        <div class="text-xs text-navy-500">{{ $r['sobat_id'] }}@if (! $r['terpilih']) · <span class="text-bps-blue-700">ditambahkan</span>@endif</div>
                                    </td>
                                    <td class="px-3 py-2 text-xs">{{ $r['jabatan'] ?: '–' }}</td>
                                    @foreach ($bulans as $b)
                                        @php $c = $r['calc']['bulan'][$b]; @endphp
                                        <td class="px-3 py-2 text-right text-xs tabular-nums">
                                            <div class="font-semibold">{{ Format::rp($c['ini']) }}</div>
                                            <div class="inline-flex items-center gap-1 {{ $c['status'] === 'merah' ? 'font-semibold text-rose-600' : 'text-navy-500' }}"><span class="size-2 rounded-full {{ HonorService::titik($c['status']) }}"></span>{{ Format::rp($c['total']) }}</div>
                                        </td>
                                    @endforeach
                                    <td class="px-2"><button wire:click="buangDariPratinjau({{ $i }})" class="btn-quiet rounded p-1" title="Jangan impor baris ini"><x-icon name="x" class="size-4" /></button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
        <x-slot:footer>
            @if ($pratinjau || $uploadErrors)
                <button wire:click="batalPratinjau" class="btn btn-ghost mr-auto">Ganti file</button>
            @endif
            <button wire:click="$set('imporTampil', false)" class="btn btn-ghost">Tutup</button>
            @if ($pratinjau)
                <button wire:click="simpanImpor" class="btn btn-green" wire:loading.attr="disabled">Simpan {{ count($pratinjau) }} petugas</button>
            @endif
        </x-slot:footer>
    </x-modal>

    {{-- ================= Modal ubah satu petugas ================= --}}
    <x-modal :show="(bool) $editPetugas" close="$set('editId', null)" :title="$editPetugas ? 'Ubah: '.$editPetugas->mitra->nama : ''">
        <form wire:submit="simpanUbah" id="form-ubah" class="space-y-4">
            <div>
                <label class="label">Jabatan</label>
                <input wire:model="editJabatan" class="input" placeholder="mis. PPL, PML, Pengolah">
            </div>
            @foreach ($editHonor as $b => $v)
                <div wire:key="eh-{{ $b }}">
                    <label class="label">Honor {{ Periode::label($b, false) }}</label>
                    <div class="relative"><span class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-sm text-navy-400">Rp</span>
                        <input type="number" min="0" step="1" wire:model="editHonor.{{ $b }}" class="input !pl-10 tabular-nums"></div>
                    @error("editHonor.$b")<p class="error">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </form>
        <x-slot:footer>
            <button wire:click="$set('editId', null)" class="btn btn-ghost">Batal</button>
            <button type="submit" form="form-ubah" class="btn btn-blue">Simpan</button>
        </x-slot:footer>
    </x-modal>

    <x-modal :show="(bool) $lepasId" close="$set('lepasId', null)" title="Keluarkan petugas?">
        <p class="text-sm text-navy-700">Petugas dan honornya di kegiatan ini akan dihapus. Data mitra di database tidak terhapus.</p>
        <x-slot:footer>
            <button wire:click="$set('lepasId', null)" class="btn btn-ghost">Batal</button>
            <button wire:click="lepas" class="btn btn-danger">Keluarkan</button>
        </x-slot:footer>
    </x-modal>
</div>
