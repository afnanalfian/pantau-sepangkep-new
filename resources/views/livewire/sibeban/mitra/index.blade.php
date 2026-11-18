<div>
    @include('livewire.sibeban.partials.nav')
    @include('livewire.sibeban.partials.flash')

    <div class="flex flex-wrap items-end justify-between gap-4 mb-5">
        <div>
            <h1 class="text-xl font-semibold text-slate-900">Master Mitra</h1>
            <p class="text-sm text-slate-500">Daftar petugas (ID SOBAT). Mitra yang belum terdaftar juga otomatis dibuat saat unggah penugasan.</p>
        </div>
        <button wire:click="tambah" class="rounded-lg bg-bps-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-bps-blue-700">+ Mitra</button>
    </div>

    <form wire:submit="impor" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 text-sm ring-1 ring-slate-200">
        <label>
            <span class="text-slate-600">Impor / perbarui dari Excel (sobat_id, nama, posisi, email)</span>
            <input type="file" wire:model="file" accept=".xlsx,.xls,.csv" class="mt-1 block text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2">
            @error('file')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
        </label>
        <button type="submit" class="rounded-lg px-4 py-2 ring-1 ring-slate-300 hover:bg-slate-50" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="impor,file">Impor</span><span wire:loading wire:target="impor,file">Memproses…</span>
        </button>
        <a href="{{ route('sibeban.mitra.template') }}" class="text-bps-blue-700 hover:underline">⤓ Template</a>
    </form>

    <input type="search" wire:model.live.debounce.400ms="cari" placeholder="Cari nama / ID SOBAT / posisi…"
           class="mb-3 w-80 rounded-lg bg-white px-3 py-2 text-sm ring-1 ring-slate-200">

    <div class="overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-2 text-left font-medium">ID SOBAT</th>
                    <th class="px-4 py-2 text-left font-medium">Nama</th>
                    <th class="px-4 py-2 text-left font-medium">Posisi</th>
                    <th class="px-4 py-2 text-right font-medium">Penugasan</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($mitras as $m)
                    <tr wire:key="mi-{{ $m->id }}" class="hover:bg-slate-50/60">
                        <td class="px-4 py-2 tabular text-slate-600">{{ $m->sobat_id }}</td>
                        <td class="px-4 py-2"><a href="{{ route('sibeban.mitra.show', $m) }}" wire:navigate class="font-medium hover:text-bps-blue-700">{{ $m->nama }}</a></td>
                        <td class="px-4 py-2 text-slate-600">{{ $m->posisi ?? '–' }}</td>
                        <td class="px-4 py-2 text-right tabular">{{ $m->penugasans_count }}</td>
                        <td class="px-4 py-2 text-right whitespace-nowrap">
                            <button wire:click="edit({{ $m->id }})" class="text-slate-600 hover:underline">Ubah</button>
                            <button wire:click="hapus({{ $m->id }})" wire:confirm="Hapus {{ $m->nama }}? Semua penugasannya ikut terhapus." class="ml-3 text-red-600 hover:underline">Hapus</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">Belum ada mitra.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $mitras->links() }}</div>

    @if ($formTampil)
        <div class="fixed inset-0 z-40 flex items-start justify-center bg-navy-900/40 p-4 pt-20">
            <form wire:submit="simpan" class="w-full max-w-lg rounded-xl bg-white shadow-xl">
                <div class="border-b border-line px-5 py-3 font-semibold">{{ $editId ? 'Ubah mitra' : 'Mitra baru' }}</div>
                <div class="grid gap-4 p-5 text-sm">
                    @foreach (['sobat_id' => 'ID SOBAT', 'nama' => 'Nama lengkap', 'posisi' => 'Posisi (mis. Mitra Pendataan)', 'email' => 'Email'] as $f => $label)
                        <label>
                            <span class="text-slate-600">{{ $label }}</span>
                            <input type="text" wire:model="{{ $f }}" class="mt-1 w-full rounded-lg px-3 py-2 ring-1 ring-slate-300">
                            @error($f)<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                        </label>
                    @endforeach
                </div>
                <div class="flex justify-end gap-2 border-t border-line px-5 py-3">
                    <button type="button" wire:click="$set('formTampil', false)" class="rounded-lg px-4 py-2 text-sm ring-1 ring-slate-300">Batal</button>
                    <button type="submit" class="rounded-lg bg-bps-blue-700 px-4 py-2 text-sm font-medium text-white">Simpan</button>
                </div>
            </form>
        </div>
    @endif
</div>
