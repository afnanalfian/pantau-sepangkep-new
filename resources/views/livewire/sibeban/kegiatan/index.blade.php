@php
    use App\Services\Sibeban\HonorService;
    use App\Support\Sibeban\Format;
    use App\Support\Sibeban\Periode;
@endphp
<div>
    @include('livewire.sibeban.partials.nav')
    @include('livewire.sibeban.partials.flash')

    <div class="flex flex-wrap items-end justify-between gap-4 mb-5">
        <div>
            <h1 class="text-xl font-semibold text-slate-900">Kegiatan</h1>
            <p class="text-sm text-slate-500">Buat kegiatan baru, lalu unggah daftar petugas (ID SOBAT, jabatan, honor per bulan).</p>
        </div>
        <button wire:click="tambah" class="rounded-lg bg-bps-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-bps-blue-700">+ Kegiatan baru</button>
    </div>

    <div class="flex flex-wrap gap-2 mb-3">
        <input type="search" wire:model.live.debounce.400ms="cari" placeholder="Cari nama kegiatan…"
               class="w-72 rounded-lg bg-white px-3 py-2 text-sm ring-1 ring-slate-200">
        <select wire:model.live="tahun" class="rounded-lg bg-white px-3 py-2 text-sm ring-1 ring-slate-200">
            <option value="">Semua tahun</option>
            @foreach (range(now()->year + 1, now()->year - 3) as $t)
                <option value="{{ $t }}">{{ $t }}</option>
            @endforeach
        </select>
    </div>

    <div class="overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-2 text-left font-medium">Kegiatan</th>
                    <th class="px-4 py-2 text-left font-medium">Periode</th>
                    <th class="px-4 py-2 text-right font-medium">Honor / bulan</th>
                    <th class="px-4 py-2 text-left font-medium">Petugas &amp; status limit</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($kegiatans as $k)
                    @php $h = $ringkas[$k->id]; @endphp
                    <tr wire:key="k-{{ $k->id }}" class="hover:bg-slate-50/60">
                        <td class="px-4 py-3">
                            <a href="{{ route('sibeban.kegiatan.show', $k) }}" wire:navigate class="font-medium text-slate-900 hover:text-bps-blue-700">{{ $k->nama }}</a>
                            <div class="text-xs text-slate-400">
                                {{ $k->jenis }}@if ($k->tim) · Tim {{ $k->tim }}@endif
                                @if ($k->sumber === 'import-matriks') · <span class="text-slate-500">impor matriks</span>@endif
                            </div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            {{ $k->tanggal_mulai->format('d/m/Y') }} – {{ $k->tanggal_selesai->format('d/m/Y') }}
                            <div class="text-xs text-slate-400">{{ count($k->bulans()) }} bulan</div>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap tabular text-xs">
                            <div>PPL {{ Format::rp($k->honor_ppl) }}</div>
                            <div class="text-slate-500">PML {{ Format::rp($k->honor_pml) }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3 text-xs">
                                <span class="font-medium text-slate-700">{{ $k->penugasans_count }} petugas</span>
                                @foreach (['merah', 'oranye', 'hijau'] as $s)
                                    @if ($h[$s])
                                        <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full {{ HonorService::titik($s) }}"></span>{{ $h[$s] }}</span>
                                    @endif
                                @endforeach
                            </div>
                            @if ($k->penugasans_count)
                                <div class="mt-1.5 flex h-1.5 w-48 overflow-hidden rounded-full bg-slate-100">
                                    @foreach (['hijau', 'oranye', 'merah'] as $s)
                                        <div class="{{ HonorService::titik($s) }}" style="width: {{ $h[$s] / $k->penugasans_count * 100 }}%"></div>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('sibeban.kegiatan.show', $k) }}" wire:navigate class="text-bps-blue-700 hover:underline">Kelola</a>
                            <button wire:click="edit({{ $k->id }})" class="ml-3 text-slate-600 hover:underline">Ubah</button>
                            <button wire:click="hapus({{ $k->id }})" wire:confirm="Hapus kegiatan &quot;{{ $k->nama }}&quot; beserta seluruh penugasan dan honornya?"
                                    class="ml-3 text-red-600 hover:underline">Hapus</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">Belum ada kegiatan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $kegiatans->links() }}</div>

    {{-- Modal form --}}
    @if ($formTampil)
        <div class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-navy-900/40 p-4 pt-16" wire:keydown.escape.window="$set('formTampil', false)">
            <form wire:submit="simpan" class="w-full max-w-2xl rounded-xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-line px-5 py-3">
                    <h2 class="font-semibold">{{ $editId ? 'Ubah kegiatan' : 'Kegiatan baru' }}</h2>
                    <button type="button" wire:click="$set('formTampil', false)" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-5 text-sm">
                    <label class="sm:col-span-2">
                        <span class="text-slate-600">Nama kegiatan</span>
                        <input type="text" wire:model="nama" class="mt-1 w-full rounded-lg px-3 py-2 ring-1 ring-slate-300" placeholder="mis. Survei Sosial Ekonomi Nasional Maret 2027">
                        @error('nama')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                    </label>
                    <label>
                        <span class="text-slate-600">Jenis kegiatan</span>
                        <select wire:model="jenis" class="mt-1 w-full rounded-lg px-3 py-2 ring-1 ring-slate-300">
                            @foreach (\App\Models\Sibeban\Kegiatan::JENIS as $j)<option>{{ $j }}</option>@endforeach
                        </select>
                        <span class="text-xs text-slate-400">Menentukan limit honor (lihat Pengaturan Limit).</span>
                    </label>
                    <label>
                        <span class="text-slate-600">Tim</span>
                        <input type="text" wire:model="tim" list="daftar-tim" class="mt-1 w-full rounded-lg px-3 py-2 ring-1 ring-slate-300">
                        <datalist id="daftar-tim">@foreach (\App\Livewire\Kegiatan\Index::TIM as $t)<option value="{{ $t }}">@endforeach</datalist>
                    </label>
                    <label>
                        <span class="text-slate-600">Tanggal mulai</span>
                        <input type="date" wire:model.live="tanggal_mulai" class="mt-1 w-full rounded-lg px-3 py-2 ring-1 ring-slate-300">
                        @error('tanggal_mulai')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                    </label>
                    <label>
                        <span class="text-slate-600">Tanggal selesai</span>
                        <input type="date" wire:model.live="tanggal_selesai" class="mt-1 w-full rounded-lg px-3 py-2 ring-1 ring-slate-300">
                        @error('tanggal_selesai')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                    </label>
                    @php $bl = Periode::bulanAntara($tanggal_mulai ?: null, $tanggal_selesai ?: null); @endphp
                    @if ($bl)
                        <div class="sm:col-span-2 -mt-2 text-xs text-slate-500">
                            Honor dihitung untuk {{ count($bl) }} bulan: {{ implode(', ', array_map(fn ($b) => Periode::label($b), $bl)) }}
                        </div>
                    @endif
                    <label>
                        <span class="text-slate-600">Honor PPL per bulan (Rp)</span>
                        <input type="number" min="0" step="1000" wire:model="honor_ppl" class="mt-1 w-full rounded-lg px-3 py-2 ring-1 ring-slate-300 tabular">
                        @error('honor_ppl')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                    </label>
                    <label>
                        <span class="text-slate-600">Honor PML per bulan (Rp)</span>
                        <input type="number" min="0" step="1000" wire:model="honor_pml" class="mt-1 w-full rounded-lg px-3 py-2 ring-1 ring-slate-300 tabular">
                        @error('honor_pml')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="sm:col-span-2">
                        <span class="text-slate-600">Keterangan</span>
                        <textarea wire:model="keterangan" rows="2" class="mt-1 w-full rounded-lg px-3 py-2 ring-1 ring-slate-300"></textarea>
                    </label>
                    @if ($editId)
                        <p class="sm:col-span-2 text-xs text-amber-700 bg-amber-50 rounded-lg px-3 py-2">
                            Jika periode diubah, honor petugas di bulan yang keluar dari periode akan dihapus dan bulan baru diisi honor default jabatan.
                        </p>
                    @endif
                </div>
                <div class="flex justify-end gap-2 border-t border-line px-5 py-3">
                    <button type="button" wire:click="$set('formTampil', false)" class="rounded-lg px-4 py-2 text-sm ring-1 ring-slate-300">Batal</button>
                    <button type="submit" class="rounded-lg bg-bps-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-bps-blue-700">
                        <span wire:loading.remove wire:target="simpan">Simpan</span><span wire:loading wire:target="simpan">Menyimpan…</span>
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>
