<div>
    @include('livewire.sibeban.partials.nav')
    @include('livewire.sibeban.partials.flash')

    <x-page-header title="Database mitra" :subtitle="$admin ? 'Kelola data mitra (Sobat ID, wilayah, posisi). Hanya admin yang dapat mengubah.' : 'Daftar mitra statistik. Perubahan data mitra dilakukan oleh admin.'">
        @if ($admin)
            <x-slot:actions>
                <a href="{{ route('sibeban.mitra.ekspor') }}" class="btn btn-ghost btn-sm"><x-icon name="download" class="size-4" /> Ekspor</a>
                <button wire:click="$set('imporTampil', true)" class="btn btn-ghost btn-sm"><x-icon name="upload" class="size-4" /> Impor Excel</button>
                <button wire:click="tambah" class="btn btn-blue btn-sm"><x-icon name="plus" class="size-4" /> Mitra</button>
            </x-slot:actions>
        @endif
    </x-page-header>

    @if ($admin && $tanpaWilayah)
        <div class="mb-4 flex flex-wrap items-center gap-2 rounded-xl border border-bps-orange-100 bg-bps-orange-50 px-4 py-3 text-sm text-bps-orange-700">
            <x-icon name="alert" class="size-4" /> {{ $tanpaWilayah }} mitra belum punya data kecamatan/desa. Klik <b>Ekspor</b>, lengkapi kolom Kecamatan & Desa/Kelurahan, lalu <b>Impor Excel</b> kembali.
        </div>
    @endif

    <div class="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
        <input type="search" wire:model.live.debounce.400ms="cari" placeholder="Cari nama / Sobat ID / posisi…" class="input col-span-2">
        <select wire:model.live="kecamatan" class="input">
            <option value="">Semua kecamatan</option>
            @foreach ($opsi['kecamatan'] as $k)<option value="{{ $k }}">{{ $k }}</option>@endforeach
        </select>
        <select wire:model.live="desa" class="input" @disabled($kecamatan === '')>
            <option value="">{{ $kecamatan === '' ? 'Pilih kecamatan dulu' : 'Semua desa/kelurahan' }}</option>
            @foreach ($opsi['desa'] as $d)<option value="{{ $d }}">{{ $d }}</option>@endforeach
        </select>
    </div>

    <div class="card overflow-x-auto">
        <table class="w-full min-w-[640px] text-sm">
            <thead class="table-head"><tr>
                <th class="px-4 py-2.5">Nama</th><th class="px-4 py-2.5">Sobat ID</th><th class="px-4 py-2.5">Kecamatan</th><th class="px-4 py-2.5">Desa/Kelurahan</th><th class="px-4 py-2.5">Posisi</th><th class="px-4 py-2.5 text-right">Kegiatan</th>
                @if ($admin)<th class="w-20"></th>@endif
            </tr></thead>
            <tbody class="divide-y divide-line">
                @forelse ($mitras as $m)
                    <tr wire:key="m-{{ $m->id }}" class="hover:bg-navy-50/40">
                        <td class="px-4 py-2.5"><a href="{{ route('sibeban.mitra.show', $m) }}" wire:navigate class="font-semibold hover:text-bps-blue-700">{{ $m->nama }}</a>@if ($m->email)<div class="text-xs text-navy-400">{{ $m->email }}</div>@endif</td>
                        <td class="px-4 py-2.5 tabular-nums text-navy-700">{{ $m->sobat_id }}</td>
                        <td class="px-4 py-2.5">{{ $m->kecamatan ?: '–' }}</td>
                        <td class="px-4 py-2.5">{{ $m->desa ?: '–' }}</td>
                        <td class="px-4 py-2.5 text-xs text-navy-700">{{ $m->posisi ?: '–' }}</td>
                        <td class="px-4 py-2.5 text-right tabular-nums">{{ $m->penugasans_count }}</td>
                        @if ($admin)
                            <td class="px-3 py-2.5"><div class="flex justify-end gap-0.5">
                                <button wire:click="edit({{ $m->id }})" class="btn-quiet rounded-lg p-1.5" aria-label="Ubah"><x-icon name="pencil" class="size-4" /></button>
                                <button wire:click="$set('hapusId', {{ $m->id }})" class="btn-quiet rounded-lg p-1.5 hover:!text-rose-600" aria-label="Hapus"><x-icon name="trash" class="size-4" /></button>
                            </div></td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty icon="users" title="Tidak ada mitra" text="Ubah pencarian atau filter wilayah." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $mitras->links() }}</div>

    @if ($admin)
        <x-modal :show="$formTampil" close="$set('formTampil', false)" :title="$editId ? 'Ubah mitra' : 'Tambah mitra'">
            <form wire:submit="simpan" id="form-mitra" class="grid gap-4 sm:grid-cols-2">
                <div><label class="label">Sobat ID</label><input wire:model="form.sobat_id" class="input @error('form.sobat_id') input-error @enderror" inputmode="numeric">@error('form.sobat_id')<p class="error">{{ $message }}</p>@enderror</div>
                <div><label class="label">Nama</label><input wire:model="form.nama" class="input @error('form.nama') input-error @enderror">@error('form.nama')<p class="error">{{ $message }}</p>@enderror</div>
                <div><label class="label">Kecamatan</label><input wire:model="form.kecamatan" list="dl-kec" class="input"><datalist id="dl-kec">@foreach ($opsi['kecamatan'] as $k)<option value="{{ $k }}">@endforeach</datalist></div>
                <div><label class="label">Desa/Kelurahan</label><input wire:model="form.desa" class="input"></div>
                <div><label class="label">Posisi</label><input wire:model="form.posisi" list="dl-pos" class="input" placeholder="mis. Mitra Pendataan"><datalist id="dl-pos">@foreach ($opsi['posisi'] as $p)<option value="{{ $p }}">@endforeach</datalist></div>
                <div><label class="label">Email</label><input type="email" wire:model="form.email" class="input">@error('form.email')<p class="error">{{ $message }}</p>@enderror</div>
            </form>
            <x-slot:footer>
                <button wire:click="$set('formTampil', false)" class="btn btn-ghost">Batal</button>
                <button type="submit" form="form-mitra" class="btn btn-blue">Simpan</button>
            </x-slot:footer>
        </x-modal>

        <x-modal :show="$imporTampil" close="$set('imporTampil', false)" title="Impor database mitra" subtitle="Mitra dengan Sobat ID yang sama diperbarui; yang belum ada ditambahkan.">
            <form wire:submit="impor" id="form-impor" class="space-y-3">
                <p class="text-sm text-navy-700">Kolom: <b>Sobat ID, Nama, Kecamatan, Desa/Kelurahan, Posisi, Email</b>. <a href="{{ route('sibeban.mitra.template') }}" class="font-semibold text-bps-blue-700 underline">Unduh template</a> atau gunakan hasil <a href="{{ route('sibeban.mitra.ekspor') }}" class="font-semibold text-bps-blue-700 underline">ekspor</a>.</p>
                <input type="file" wire:model="file" accept=".xlsx,.xls,.csv" class="input">
                @error('file')<p class="error">{{ $message }}</p>@enderror
            </form>
            <x-slot:footer>
                <button wire:click="$set('imporTampil', false)" class="btn btn-ghost">Batal</button>
                <button type="submit" form="form-impor" class="btn btn-blue" wire:loading.attr="disabled" wire:target="impor,file">Impor</button>
            </x-slot:footer>
        </x-modal>

        <x-modal :show="(bool) $hapusId" close="$set('hapusId', null)" title="Hapus mitra?">
            <p class="text-sm text-navy-700">Mitra beserta seluruh penugasan & honornya di semua kegiatan akan dihapus.</p>
            <x-slot:footer>
                <button wire:click="$set('hapusId', null)" class="btn btn-ghost">Batal</button>
                <button wire:click="hapus" class="btn btn-danger">Hapus</button>
            </x-slot:footer>
        </x-modal>
    @endif
</div>
