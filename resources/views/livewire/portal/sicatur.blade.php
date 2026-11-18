@php
    use App\Models\Surat;
    $me = auth()->user();
    $warna = [
        'blue' => ['bg-bps-blue-50 text-bps-blue-700', 'bg-bps-blue'],
        'green' => ['bg-bps-green-50 text-bps-green-700', 'bg-bps-green'],
        'orange' => ['bg-bps-orange-50 text-bps-orange-700', 'bg-bps-orange'],
        'navy' => ['bg-navy-50 text-navy-700', 'bg-navy-800'],
    ];
@endphp
<div>
    {{-- Kepala --}}
    <div class="mb-5 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex items-center gap-3">
            <img src="{{ asset('images/portal/si-catur.png') }}" alt="" class="size-14 shrink-0 object-contain">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight">SICATUR</h1>
                <p class="text-sm text-navy-500">Sistem Pencatatan Surat · nomor langsung tercatat dan tidak bisa kembar</p>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($pedoman)
                <a href="{{ $pedoman }}" target="_blank" rel="noopener" class="btn btn-ghost"><x-icon name="book" class="size-4" /> Buku Pedoman</a>
            @endif
            <a href="{{ $srikandi }}" target="_blank" rel="noopener" class="btn btn-ghost"><x-icon name="external" class="size-4" /> Nomor Surat Keluar (SRIKANDI)</a>
        </div>
    </div>

    {{-- Tab --}}
    <nav class="-mx-1 mb-5 flex gap-1 overflow-x-auto rounded-2xl bg-navy-50 p-1">
        @php
            $tabs = ['beranda' => 'Beranda'] + collect($jenisList)->map(fn ($j) => $j['singkat'])->all() + ['riwayat' => 'Riwayat', 'link' => 'Link Surat ('.$jumlahLink.')'];
            if ($me->is_admin) { $tabs['pengaturan'] = 'Pengaturan'; }
        @endphp
        @foreach ($tabs as $k => $label)
            <button wire:click="bukaTab('{{ $k }}')" class="tab shrink-0 {{ $tab === $k ? 'active' : '' }}">{{ $label }}</button>
        @endforeach
    </nav>

    {{-- Nomor yang baru terbit --}}
    @if ($baru)
        <div class="mb-5 overflow-hidden rounded-2xl border-2 border-bps-green bg-white" x-data="{ copied: false }">
            <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center">
                <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-bps-green text-white"><x-icon name="check" class="size-6" /></span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm text-navy-500">Nomor {{ $baru['jenis'] }} Anda — {{ $baru['perihal'] }}</p>
                    <p class="mt-1 font-mono text-2xl font-extrabold tracking-tight break-all select-all sm:text-3xl" x-ref="no">{{ $baru['nomor'] }}</p>
                </div>
                <div class="flex gap-2">
                    <button class="btn btn-green" @click="navigator.clipboard.writeText($refs.no.innerText.trim()); copied = true; setTimeout(() => copied = false, 2000)">
                        <x-icon name="clip" class="size-4" /> <span x-text="copied ? 'Tersalin' : 'Salin nomor'"></span>
                    </button>
                    <button wire:click="$set('baru', null)" class="btn btn-ghost px-2.5" aria-label="Tutup"><x-icon name="x" class="size-4" /></button>
                </div>
            </div>
        </div>
    @endif

    {{-- ===================== BERANDA ===================== --}}
    @if ($tab === 'beranda')
        <h2 class="text-lg font-bold">Terbitkan nomor surat</h2>
        <p class="mb-4 text-sm text-navy-500">Pilih jenis surat. Nomor dibuat otomatis berurutan per tahun dan nama Anda tercatat sebagai pembuat.</p>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($jenisList as $kode => $j)
                @php $t = $terakhir[$kode]; [$soft, $solid] = $warna[$j['warna']] ?? $warna['blue']; @endphp
                <div class="card relative flex flex-col overflow-hidden p-5" wire:key="b-{{ $kode }}">
                    <span class="absolute inset-x-0 top-0 h-1 {{ $solid }}"></span>
                    <div class="flex items-start justify-between gap-2">
                        <p class="font-bold leading-snug">{{ $j['label'] }}</p>
                        <span class="chip shrink-0 {{ $soft }} ring-transparent">{{ $stat[$kode] ?? 0 }} nomor</span>
                    </div>
                    <div class="mt-4 flex-1">
                        @if ($t)
                            <p class="text-xs text-navy-500">Nomor terakhir</p>
                            <p class="font-mono text-[15px] font-bold break-all">{{ $t->nomor_surat }}</p>
                            <p class="mt-0.5 text-xs text-navy-500">{{ $t->tanggal->translatedFormat('j M Y') }} · {{ $t->pembuat }}</p>
                        @else
                            <p class="text-sm text-navy-500">Belum ada nomor tahun {{ $tahunIni }}.</p>
                            <p class="mt-0.5 font-mono text-xs text-navy-400">{{ $j['format'] }}</p>
                        @endif
                    </div>
                    <button wire:click="bukaTab('{{ $kode }}')" class="btn btn-blue mt-4 self-start"><x-icon name="plus" class="size-4" /> Buka form {{ $j['singkat'] }}</button>
                </div>
            @endforeach
        </div>

        @if ($sayaTerbaru->isNotEmpty())
            <h2 class="mt-8 mb-3 text-lg font-bold">Nomor terakhir yang saya buat</h2>
            <div class="card divide-y divide-line">
                @foreach ($sayaTerbaru as $s)
                    <div class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0"><p class="font-mono text-sm font-bold break-all">{{ $s->nomor_surat }}</p><p class="truncate text-xs text-navy-500">{{ Surat::labelJenis($s->jenis) }} · {{ $s->perihal }}</p></div>
                        <p class="shrink-0 text-xs text-navy-500">{{ $s->tanggal->translatedFormat('j M Y') }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    @endif

    {{-- ===================== FORM JENIS ===================== --}}
    @isset($j)
        @if (isset($jenisList[$tab]))
            <div class="grid gap-5 lg:grid-cols-5">
                <form wire:submit="terbitkan" class="card p-5 lg:col-span-3">
                    <h2 class="text-lg font-bold">{{ $j['label'] }}</h2>
                    <p class="mb-4 text-sm text-navy-500">{{ $jumlah }} nomor tercatat tahun {{ now()->year }}.</p>
                    @include('livewire.portal.partials.sicatur-isian', ['j' => $j, 'baru' => true])
                    <div class="mt-5 rounded-xl bg-paper p-4">
                        <p class="text-xs text-navy-500">Nomor yang akan terbit</p>
                        <p class="font-mono text-xl font-extrabold break-all">{{ $this->preview }}</p>
                        <p class="mt-0.5 text-[11px] text-navy-400">Bisa bergeser bila ada yang menerbitkan nomor bersamaan.</p>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button class="btn btn-blue" wire:loading.attr="disabled" wire:target="terbitkan,file"><x-icon name="check" class="size-4" /> Terbitkan nomor</button>
                    </div>
                </form>

                <div class="lg:col-span-2">
                    <div class="mb-2 flex items-center justify-between">
                        <h3 class="font-bold">Nomor terbaru</h3>
                        <button wire:click="bukaTab('riwayat'); $set('fJenis', '{{ $tab }}')" class="text-sm font-semibold text-bps-blue-700">Semua riwayat →</button>
                    </div>
                    <div class="card divide-y divide-line">
                        @forelse ($daftar as $s)
                            <div class="px-4 py-3" wire:key="d-{{ $s->id }}">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="font-mono text-sm font-bold break-all">{{ $s->nomor_surat }}</p>
                                    @if ($this->canEdit($s))
                                        <div class="flex shrink-0 gap-0.5">
                                            <button wire:click="edit({{ $s->id }})" class="btn-quiet rounded p-1" aria-label="Ubah"><x-icon name="pencil" class="size-3.5" /></button>
                                            <button wire:click="$set('confirmDeleteId', {{ $s->id }})" class="btn-quiet rounded p-1 hover:!text-rose-600" aria-label="Hapus"><x-icon name="trash" class="size-3.5" /></button>
                                        </div>
                                    @endif
                                </div>
                                <p class="text-sm text-navy-700">{{ $s->perihal }}</p>
                                <p class="text-xs text-navy-500">{{ $s->tanggal->translatedFormat('j M Y') }} · {{ $s->pembuat }}@if ($s->file_path) · <a href="{{ route('portal.sicatur.file', $s) }}" target="_blank" class="text-bps-blue-700">file</a>@endif</p>
                            </div>
                        @empty
                            <x-empty icon="document" title="Belum ada nomor" text="Nomor pertama tahun ini akan muncul di sini." />
                        @endforelse
                    </div>
                </div>
            </div>
        @endif
    @endisset

    {{-- ===================== RIWAYAT ===================== --}}
    @if ($tab === 'riwayat')
        <div class="mb-4 flex flex-col gap-2 lg:flex-row lg:items-center">
            <div class="relative flex-1 lg:max-w-xs">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-navy-300" />
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari nomor, perihal, pihak, pegawai…" class="input !pl-9">
            </div>
            <div class="flex flex-wrap gap-2">
                <select wire:model.live="fJenis" class="input !w-auto"><option value="">Semua jenis</option>@foreach ($jenisRiwayat as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                <select wire:model.live="bulan" class="input !w-auto">
                    <option value="">Semua bulan</option>
                    @foreach (range(1, 12) as $b)<option value="{{ $b }}">{{ \Illuminate\Support\Carbon::create(2000, $b, 1)->locale('id')->translatedFormat('F') }}</option>@endforeach
                </select>
                <select wire:model.live="tahun" class="input !w-auto">@foreach ($tahunList as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach</select>
                <label class="inline-flex items-center gap-2 rounded-xl border border-line bg-white px-3 text-sm"><input type="checkbox" wire:model.live="milikSaya" class="rounded border-line"> Buatan saya</label>
                <a href="{{ route('portal.sicatur.export', ['jenis' => $fJenis, 'tahun' => $tahun]) }}" class="btn btn-ghost"><x-icon name="download" class="size-4" /> Excel</a>
            </div>
        </div>

        <div class="card overflow-x-auto">
            <table class="w-full min-w-[860px] text-sm">
                <thead class="table-head"><tr>
                    <th class="px-4 py-2.5">Nomor</th><th class="px-3 py-2.5">Jenis</th><th class="px-3 py-2.5">Tanggal</th><th class="px-3 py-2.5">Perihal</th><th class="px-3 py-2.5">Pembuat</th><th class="w-24 px-4 py-2.5"></th>
                </tr></thead>
                <tbody class="divide-y divide-line">
                    @forelse ($surats as $s)
                        <tr wire:key="r-{{ $s->id }}" class="align-top hover:bg-navy-50/40">
                            <td class="px-4 py-3 font-mono text-[13px] font-bold">{{ $s->nomor_surat }}</td>
                            <td class="px-3 py-3"><span class="chip bg-navy-50 text-navy-700 ring-navy-100">{{ Surat::labelJenis($s->jenis) }}</span></td>
                            <td class="px-3 py-3 whitespace-nowrap">{{ $s->tanggal->translatedFormat('j M Y') }}</td>
                            <td class="px-3 py-3">
                                <p>{{ $s->perihal }}</p>
                                @if ($s->pegawai)<p class="text-xs text-navy-500">{{ implode(', ', $s->pegawai) }}</p>@endif
                                @if ($s->pihak)<p class="text-xs text-navy-500">{{ $s->pihak }}</p>@endif
                                @if ($s->tanggal_mulai)<p class="text-xs text-navy-500">{{ $s->tanggal_mulai->translatedFormat('j M') }}{{ $s->tanggal_selesai && ! $s->tanggal_selesai->eq($s->tanggal_mulai) ? ' – '.$s->tanggal_selesai->translatedFormat('j M Y') : ' '.$s->tanggal_mulai->format('Y') }}{{ $s->tempat ? ' · '.$s->tempat : '' }}</p>@endif
                            </td>
                            <td class="px-3 py-3 text-xs">{{ $s->pembuat }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-0.5">
                                    @if ($s->file_path)<a href="{{ route('portal.sicatur.file', $s) }}" target="_blank" class="btn-quiet rounded-lg p-1.5" title="{{ $s->file_name }}"><x-icon name="clip" class="size-4" /></a>@endif
                                    @if ($s->link)<a href="{{ $s->link }}" target="_blank" rel="noopener" class="btn-quiet rounded-lg p-1.5" title="Buka tautan"><x-icon name="link" class="size-4" /></a>@endif
                                    @if ($this->canEdit($s))
                                        <button wire:click="edit({{ $s->id }})" class="btn-quiet rounded-lg p-1.5" aria-label="Ubah"><x-icon name="pencil" class="size-4" /></button>
                                        <button wire:click="$set('confirmDeleteId', {{ $s->id }})" class="btn-quiet rounded-lg p-1.5 hover:!text-rose-600" aria-label="Hapus"><x-icon name="trash" class="size-4" /></button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty icon="document" title="Tidak ada nomor" text="Ubah filter atau tahun." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $surats->links() }}</div>
    @endif

    {{-- ===================== LINK SURAT ===================== --}}
    @if ($tab === 'link')
        <div class="mb-4 flex items-end justify-between gap-3">
            <div><h2 class="text-lg font-bold">Link surat</h2><p class="text-sm text-navy-500">Template dan tautan penting seputar persuratan.</p></div>
            @if ($me->is_admin)<button wire:click="tambahLink" class="btn btn-blue btn-sm"><x-icon name="plus" class="size-4" /> Tautan</button>@endif
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            @forelse ($links as $l)
                <div class="card group flex items-start gap-3 p-4" wire:key="l-{{ $l->id }}">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-bps-blue-50 text-bps-blue-700"><x-icon name="link" class="size-5" /></span>
                    <a href="{{ $l->url }}" target="_blank" rel="noopener" class="min-w-0 flex-1">
                        <p class="font-bold hover:text-bps-blue-700">{{ $l->judul }} <x-icon name="external" class="inline size-3.5 text-navy-300" /></p>
                        @if ($l->keterangan)<p class="text-sm text-navy-500">{{ $l->keterangan }}</p>@endif
                        <p class="truncate text-xs text-navy-400">{{ $l->url }}</p>
                    </a>
                    @if ($me->is_admin)
                        <div class="flex shrink-0 gap-0.5">
                            <button wire:click="ubahLink({{ $l->id }})" class="btn-quiet rounded-lg p-1.5"><x-icon name="pencil" class="size-4" /></button>
                            <button wire:click="$set('hapusLinkId', {{ $l->id }})" class="btn-quiet rounded-lg p-1.5 hover:!text-rose-600"><x-icon name="trash" class="size-4" /></button>
                        </div>
                    @endif
                </div>
            @empty
                <div class="card col-span-full"><x-empty icon="link" title="Belum ada tautan" :text="$me->is_admin ? 'Tambahkan tautan template surat atau dokumen penting.' : 'Admin belum menambahkan tautan.'" /></div>
            @endforelse
        </div>
    @endif

    {{-- ===================== PENGATURAN (admin) ===================== --}}
    @if ($tab === 'pengaturan' && $me->is_admin)
        <div class="grid gap-5 lg:grid-cols-3">
            <form wire:submit="simpanPengaturan" class="card space-y-5 p-5 lg:col-span-2">
                <div>
                    <h2 class="text-lg font-bold">Format nomor & nomor terakhir</h2>
                    <p class="text-sm text-navy-500">Kode: <code>{nomor}</code> <code>{klasifikasi}</code> <code>{hari}</code> <code>{bulan}</code> <code>{bulan_romawi}</code> <code>{tahun}</code>. Isi “nomor terakhir” bila tahun ini sudah ada nomor yang terbit di luar aplikasi — nomor berikutnya melanjutkan dari angka itu.</p>
                </div>
                <div class="space-y-3">
                    @foreach ($jenisList as $kode => $jj)
                        <div class="grid gap-2 rounded-xl border border-line p-3 sm:grid-cols-[1fr_auto] sm:items-end" wire:key="f-{{ $kode }}">
                            <div>
                                <label class="label">{{ $jj['label'] }}</label>
                                <input wire:model="format.{{ $kode }}" class="input font-mono text-sm">
                                @error("format.$kode")<p class="error">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="label">Nomor terakhir {{ now()->year }}</label>
                                <input type="number" min="0" wire:model="mulai.{{ $kode }}" class="input !w-32" placeholder="0">
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="border-t border-line pt-5">
                    <h3 class="font-bold">Buku pedoman</h3>
                    <p class="mb-3 text-sm text-navy-500">Unggah PDF atau isi tautan (mis. Google Drive). File diutamakan bila keduanya ada.</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div><label class="label">Tautan</label><input wire:model="pedomanUrl" class="input" placeholder="https://drive.google.com/…">@error('pedomanUrl')<p class="error">{{ $message }}</p>@enderror</div>
                        <div><label class="label">File PDF</label><input type="file" wire:model="pedomanFile" accept="application/pdf" class="input">@error('pedomanFile')<p class="error">{{ $message }}</p>@enderror
                            @if ($pedomanFile)<p class="hint">Tersimpan. <a href="{{ route('portal.sicatur.pedoman') }}" target="_blank" class="text-bps-blue-700">Lihat</a> · <button type="button" wire:click="hapusPedomanFile" class="text-rose-600">hapus</button></p>@endif
                        </div>
                    </div>
                </div>
                <div class="flex justify-end border-t border-line pt-4"><button class="btn btn-blue" wire:loading.attr="disabled">Simpan pengaturan</button></div>
            </form>

            <form wire:submit="imporRiwayat" class="card h-fit space-y-3 p-5">
                <h2 class="text-lg font-bold">Impor riwayat lama</h2>
                <p class="text-sm text-navy-500">Pindahkan nomor dari spreadsheet SICATUR lama supaya riwayat lengkap dan penomoran melanjutkan. Kolom: <b>Jenis, Nomor Urut, Nomor Surat, Tanggal, Klasifikasi, Perihal, Pihak, Pembuat</b>.</p>
                <a href="{{ route('portal.sicatur.template') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-bps-blue-700"><x-icon name="download" class="size-4" /> Unduh template</a>
                <input type="file" wire:model="imporFile" accept=".xlsx,.xls,.csv" class="input">
                @error('imporFile')<p class="error">{{ $message }}</p>@enderror
                <button class="btn btn-ghost w-full" wire:loading.attr="disabled" wire:target="imporRiwayat,imporFile"><x-icon name="upload" class="size-4" /> Impor</button>
            </form>
        </div>
    @endif

    {{-- ===================== Modal ===================== --}}
    @if ($editTampil && $editId)
        @php $es = Surat::find($editId); $ej = Surat::jenis($es->jenis) ?? ['label' => Surat::labelJenis($es->jenis), 'format' => '', 'isian' => ['perihal' => 'Perihal', 'pihak' => 'Pihak (opsional)']]; @endphp
        <x-modal :show="true" close="tutupEdit" :title="'Ubah '.$ej['label']" :subtitle="$es->nomor_surat.' · nomor & tanggal tidak berubah'">
            <form wire:submit="simpanEdit" id="form-edit">
                @include('livewire.portal.partials.sicatur-isian', ['j' => $ej, 'baru' => false, 'pegawaiList' => \App\Models\User::query()->pegawai()->orderBy('name')->get(['id', 'name', 'username', 'nip']), 'klasifikasiList' => collect()])
            </form>
            <x-slot:footer>
                <button wire:click="tutupEdit" class="btn btn-ghost">Batal</button>
                <button type="submit" form="form-edit" class="btn btn-blue">Simpan</button>
            </x-slot:footer>
        </x-modal>
    @endif

    <x-modal :show="(bool) $confirmDeleteId" close="$set('confirmDeleteId', null)" title="Hapus nomor ini?">
        <p class="text-sm text-navy-700">Data dihapus dari daftar, tetapi nomornya tidak akan dipakai ulang supaya tidak kembar.</p>
        <x-slot:footer>
            <button wire:click="$set('confirmDeleteId', null)" class="btn btn-ghost">Batal</button>
            <button wire:click="delete" class="btn btn-danger">Hapus</button>
        </x-slot:footer>
    </x-modal>

    @if ($me->is_admin)
        <x-modal :show="$linkTampil" close="$set('linkTampil', false)" :title="$linkId ? 'Ubah tautan' : 'Tambah tautan'">
            <form wire:submit="simpanLink" id="form-link" class="space-y-4">
                <div><label class="label">Judul</label><input wire:model="linkForm.judul" class="input" placeholder="mis. Template Surat Tugas">@error('linkForm.judul')<p class="error">{{ $message }}</p>@enderror</div>
                <div><label class="label">Tautan</label><input wire:model="linkForm.url" class="input" placeholder="https://…">@error('linkForm.url')<p class="error">{{ $message }}</p>@enderror</div>
                <div><label class="label">Keterangan <span class="font-normal text-navy-400">(opsional)</span></label><input wire:model="linkForm.keterangan" class="input"></div>
            </form>
            <x-slot:footer>
                <button wire:click="$set('linkTampil', false)" class="btn btn-ghost">Batal</button>
                <button type="submit" form="form-link" class="btn btn-blue">Simpan</button>
            </x-slot:footer>
        </x-modal>
        <x-modal :show="(bool) $hapusLinkId" close="$set('hapusLinkId', null)" title="Hapus tautan?">
            <x-slot:footer>
                <button wire:click="$set('hapusLinkId', null)" class="btn btn-ghost">Batal</button>
                <button wire:click="hapusLink" class="btn btn-danger">Hapus</button>
            </x-slot:footer>
        </x-modal>
    @endif
</div>
