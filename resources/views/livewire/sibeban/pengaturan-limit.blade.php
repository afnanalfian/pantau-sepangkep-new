<div class="max-w-3xl">
    @include('livewire.sibeban.partials.nav')
    @include('livewire.sibeban.partials.flash')

    <h1 class="text-xl font-semibold text-slate-900">Pengaturan Limit Honor</h1>
    <p class="mb-5 text-sm text-slate-500">Batas honor mitra per bulan (nonorganik) menurut jenis kegiatan dan jabatan. Nilai awal mengikuti sheet LIMIT pada matriks honor (Perka BPS No. 115/2024 jo. 165/2024).</p>

    <form wire:submit="simpan" class="space-y-5">
        <div class="overflow-hidden rounded-xl bg-white ring-1 ring-slate-200">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium">Jenis kegiatan</th>
                        <th class="px-4 py-2 text-left font-medium">PPL (pendataan lapangan)</th>
                        <th class="px-4 py-2 text-left font-medium">PML (pemeriksaan lapangan)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($limit as $jenis => $per)
                        <tr wire:key="l-{{ $jenis }}">
                            <td class="px-4 py-3 font-medium">{{ $jenis }}</td>
                            @foreach (['ppl', 'pml'] as $jab)
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1">
                                        <span class="text-slate-400">Rp</span>
                                        <input type="number" min="1" step="1000" wire:model="limit.{{ $jenis }}.{{ $jab }}" class="w-40 rounded-lg px-3 py-1.5 tabular ring-1 ring-slate-300">
                                    </div>
                                    @error("limit.$jenis.$jab")<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="grid gap-4 rounded-xl bg-white p-5 text-sm ring-1 ring-slate-200 sm:grid-cols-2">
            <label>
                <span class="text-slate-600">Ambang warna oranye (% dari limit)</span>
                <input type="number" min="1" max="100" step="1" wire:model="ambangOranye" class="mt-1 w-32 rounded-lg px-3 py-1.5 tabular ring-1 ring-slate-300">
                <span class="block text-xs text-slate-400">≤ ambang = hijau, ambang–100% = oranye, &gt; 100% = merah.</span>
                @error('ambangOranye')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
            </label>
            <label>
                <span class="text-slate-600">Limit cadangan (Rp)</span>
                <input type="number" min="1" step="1000" wire:model="limitDefault" class="mt-1 w-40 rounded-lg px-3 py-1.5 tabular ring-1 ring-slate-300">
                <span class="block text-xs text-slate-400">Dipakai bila jenis/jabatan tidak ada di tabel.</span>
                @error('limitDefault')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
            </label>
        </div>

        <button type="submit" class="rounded-lg bg-bps-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-bps-blue-700">Simpan pengaturan</button>
    </form>
</div>
