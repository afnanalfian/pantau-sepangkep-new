@php $me = auth()->user(); @endphp
<div>
    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="max-w-2xl">
            <p class="text-sm font-semibold text-bps-blue-700">SICATUR</p>
            <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">Sistem Pencatatan Surat</h1>
            <p class="mt-1 text-navy-500">Ambil nomor surat keluar dan catat agenda surat masuk. Nama pembuat diisi otomatis dari akun Anda.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ $srikandi }}" target="_blank" rel="noopener" class="btn btn-ghost"><x-icon name="external" class="size-4" /> SRIKANDI (nomor surat keluar)</a>
            <a href="{{ route('portal.sicatur.export', ['jenis' => $jenis, 'tahun' => $tahun]) }}" class="btn btn-ghost"><x-icon name="download" class="size-4" /> Excel</a>
            <button wire:click="create" class="btn btn-blue"><x-icon name="plus" class="size-4" /> {{ $jenis === 'keluar' ? 'Ambil nomor surat' : 'Catat surat masuk' }}</button>
        </div>
    </div>

    {{-- Nomor yang baru diterbitkan --}}
    @if ($baru)
        <div class="mb-6 overflow-hidden rounded-2xl border-2 border-bps-green bg-white" x-data="{ copied: false }">
            <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center">
                <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-bps-green text-white"><x-icon name="check" class="size-6" /></span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm text-navy-500">{{ $baru['jenis'] === 'keluar' ? 'Nomor surat Anda' : 'Nomor agenda surat masuk' }} — {{ $baru['perihal'] }}</p>
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

    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center">
        <div class="flex rounded-xl bg-navy-50 p-1">
            <button wire:click="$set('jenis', 'keluar')" class="tab inline-flex items-center gap-2 {{ $jenis === 'keluar' ? 'active' : '' }}">Surat keluar <span class="rounded-full bg-white px-1.5 text-[11px] font-bold text-navy-500 ring-1 ring-line">{{ $counts['keluar'] }}</span></button>
            <button wire:click="$set('jenis', 'masuk')" class="tab inline-flex items-center gap-2 {{ $jenis === 'masuk' ? 'active' : '' }}">Surat masuk <span class="rounded-full bg-white px-1.5 text-[11px] font-bold text-navy-500 ring-1 ring-line">{{ $counts['masuk'] }}</span></button>
        </div>
        <div class="flex flex-1 flex-wrap gap-2 lg:justify-end">
            <div class="relative min-w-52 flex-1 lg:max-w-xs">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-navy-300" />
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari nomor, perihal, pihak" class="input py-2 pl-9">
            </div>
            <select wire:model.live="bulan" class="input w-auto py-2">
                <option value="">Semua bulan</option>
                @foreach (range(1, 12) as $b)<option value="{{ $b }}">{{ \Illuminate\Support\Carbon::create(2000, $b, 1)->locale('id')->translatedFormat('F') }}</option>@endforeach
            </select>
            <select wire:model.live="tahun" class="input w-auto py-2">@foreach ($tahunList as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach</select>
            <label class="inline-flex items-center gap-2 rounded-xl border border-line bg-white px-3 text-sm"><input type="checkbox" wire:model.live="milikSaya" class="checkbox"> Buatan saya</label>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[880px] text-sm">
                <thead class="table-head"><tr>
                    <th class="px-5 py-3">Nomor</th><th class="px-3 py-3">Tanggal</th><th class="px-3 py-3">Perihal</th>
                    <th class="px-3 py-3">{{ $jenis === 'keluar' ? 'Tujuan' : 'Asal' }}</th><th class="px-3 py-3">Pembuat</th><th class="px-5 py-3"></th>
                </tr></thead>
                <tbody class="divide-y divide-line">
                    @forelse ($surats as $s)
                        <tr wire:key="srt-{{ $s->id }}" class="align-top">
                            <td class="px-5 py-3"><span class="font-mono text-[13px] font-bold whitespace-nowrap">{{ $s->nomor_surat }}</span>
                                @if ($s->sifat && $s->sifat !== 'B')<span class="ml-1 chip bg-rose-50 text-rose-700 ring-rose-600/20">{{ \App\Models\Surat::SIFAT[$s->sifat] }}</span>@endif</td>
                            <td class="px-3 py-3 whitespace-nowrap text-navy-700">{{ tgl($s->tanggal) }}</td>
                            <td class="px-3 py-3"><p class="font-medium">{{ $s->perihal }}</p>
                                @if ($s->klasifikasi || $s->nomor_asal)<p class="text-xs text-navy-500">{{ $s->klasifikasi }}{{ $s->nomor_asal ? ' · no. asal '.$s->nomor_asal : '' }}</p>@endif
                                @if ($s->keterangan)<p class="text-xs text-navy-500">{{ \Illuminate\Support\Str::limit($s->keterangan, 90) }}</p>@endif</td>
                            <td class="px-3 py-3 text-navy-700">{{ $s->pihak }}</td>
                            <td class="px-3 py-3"><span class="flex items-center gap-2">@if ($s->user)<x-avatar :user="$s->user" size="xs" />@endif<span class="text-sm">{{ $s->pembuat }}</span></span>
                                <span class="block text-[11px] text-navy-300">{{ $s->created_at->format('d/m/Y H:i') }}</span></td>
                            <td class="px-5 py-3">
                                <div class="flex justify-end gap-1">
                                    @if ($s->file_path)<a href="{{ route('portal.sicatur.file', $s) }}" target="_blank" class="btn btn-quiet p-1.5" title="{{ $s->file_name }}"><x-icon name="document" class="size-4" /></a>@endif
                                    @if ($s->link)<a href="{{ $s->link }}" target="_blank" rel="noopener" class="btn btn-quiet p-1.5" title="Buka link"><x-icon name="link" class="size-4" /></a>@endif
                                    @if ($this->canEdit($s))
                                        <button wire:click="edit({{ $s->id }})" class="btn btn-quiet p-1.5" title="Ubah"><x-icon name="pencil" class="size-4" /></button>
                                        <button wire:click="$set('confirmDeleteId', {{ $s->id }})" class="btn btn-quiet p-1.5 hover:text-rose-600" title="Hapus"><x-icon name="trash" class="size-4" /></button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty icon="mail" title="Belum ada surat {{ $jenis }} tercatat" text="{{ $jenis === 'keluar' ? 'Klik “Ambil nomor surat” untuk menerbitkan nomor.' : 'Klik “Catat surat masuk” untuk mencatat agenda.' }}" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($surats->hasPages())<div class="border-t border-line px-5 py-3">{{ $surats->links() }}</div>@endif
    </div>

    {{-- Form --}}
    @php $keluar = ($form['jenis'] ?? $jenis) === 'keluar'; @endphp
    <x-modal :show="$showForm" close="$set('showForm', false)" width="max-w-2xl"
             :title="$editId ? 'Ubah data surat' : ($keluar ? 'Ambil nomor surat keluar' : 'Catat surat masuk')"
             :subtitle="$editId ? 'Nomor surat tidak berubah.' : 'Pembuat: '.($me->name ?: $me->username)">
        <form wire:submit="save" id="surat-form" class="space-y-4">
            @unless ($editId)
                <div class="grid grid-cols-2 gap-2">
                    @foreach (['keluar' => 'Surat keluar', 'masuk' => 'Surat masuk'] as $k => $l)
                        <button type="button" wire:click="$set('form.jenis', '{{ $k }}')" class="rounded-xl border-2 px-4 py-2.5 text-sm font-bold {{ ($form['jenis'] ?? '') === $k ? 'border-navy-900 bg-paper' : 'border-line' }}">{{ $l }}</button>
                    @endforeach
                </div>
                <div class="rounded-2xl bg-navy-900 px-4 py-3 text-white">
                    <p class="text-xs text-navy-100/70">Perkiraan nomor</p>
                    <p class="font-mono text-lg font-bold break-all">{{ $this->preview }}</p>
                    <p class="text-[11px] text-navy-100/60">Nomor pasti diberikan saat disimpan.</p>
                </div>
            @endunless
            <div class="grid gap-3 sm:grid-cols-3">
                <div><label class="label">Tanggal surat</label><input wire:model.live="form.tanggal" type="date" class="input" @disabled($editId)></div>
                @if ($keluar)
                    <div><label class="label">Sifat</label>
                        <select wire:model.live="form.sifat" class="input">@foreach (\App\Models\Surat::SIFAT as $k => $v)<option value="{{ $k }}">{{ $k }} – {{ $v }}</option>@endforeach</select></div>
                @else
                    <div class="sm:col-span-1"><label class="label">Nomor dari pengirim</label><input wire:model="form.nomor_asal" class="input"></div>
                @endif
                <div><label class="label">Klasifikasi</label><input wire:model.live.debounce.400ms="form.klasifikasi" list="klas-list" class="input uppercase" placeholder="mis. KP.320">
                    <datalist id="klas-list">@foreach (\App\Models\Surat::KLASIFIKASI as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</datalist></div>
            </div>
            <div><label class="label">Perihal</label><input wire:model="form.perihal" class="input @error('form.perihal') input-error @enderror">@error('form.perihal')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label">{{ $keluar ? 'Tujuan' : 'Asal surat' }}</label><input wire:model="form.pihak" class="input @error('form.pihak') input-error @enderror" placeholder="{{ $keluar ? 'mis. Kepala BPS Provinsi Sulawesi Selatan' : 'mis. Bappeda Kab. Pangkep' }}">@error('form.pihak')<p class="error">{{ $message }}</p>@enderror</div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div><label class="label">File surat (opsional)</label><input type="file" wire:model="file" class="input">@error('file')<p class="error">{{ $message }}</p>@enderror<div wire:loading wire:target="file" class="hint">Mengunggah…</div></div>
                <div><label class="label">Link (opsional)</label><input wire:model="form.link" class="input" placeholder="https://…">@error('form.link')<p class="error">{{ $message }}</p>@enderror</div>
            </div>
            <div><label class="label">Keterangan</label><textarea wire:model="form.keterangan" rows="2" class="input"></textarea></div>
        </form>
        <x-slot:footer>
            <button wire:click="$set('showForm', false)" class="btn btn-ghost">Batal</button>
            <button type="submit" form="surat-form" class="btn btn-blue" wire:loading.attr="disabled" wire:target="save,file">{{ $editId ? 'Simpan' : ($keluar ? 'Terbitkan nomor' : 'Catat surat') }}</button>
        </x-slot:footer>
    </x-modal>

    <x-modal :show="(bool) $confirmDeleteId" close="$set('confirmDeleteId', null)" title="Hapus surat?" subtitle="Nomornya tetap tercatat terpakai dan tidak akan diberikan ke surat lain.">
        <x-slot:footer>
            <button wire:click="$set('confirmDeleteId', null)" class="btn btn-ghost">Batal</button>
            <button wire:click="delete" class="btn btn-danger">Hapus</button>
        </x-slot:footer>
    </x-modal>
</div>
