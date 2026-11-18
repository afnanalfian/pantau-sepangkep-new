{{-- Isian form SICATUR sesuai jenis. $j = definisi jenis, $baru = form terbitkan (bukan ubah) --}}
@php $isian = $j['isian']; $pakaiKlas = $baru && str_contains($j['format'], '{klasifikasi}'); @endphp
<div class="grid gap-4 sm:grid-cols-2">
    @if ($baru)
        <div>
            <label class="label">Tanggal surat</label>
            <input type="date" wire:model.live="form.tanggal" class="input @error('form.tanggal') input-error @enderror">
            @error('form.tanggal')<p class="error">{{ $message }}</p>@enderror
        </div>
        @if ($pakaiKlas)
            <div>
                <label class="label">Kode klasifikasi</label>
                <input wire:model.live.debounce.400ms="form.klasifikasi" list="dl-klas" class="input font-mono uppercase @error('form.klasifikasi') input-error @enderror" placeholder="{{ $j['klasifikasi'] }}">
                <datalist id="dl-klas">@foreach ($klasifikasiList as $k)<option value="{{ $k }}">@endforeach</datalist>
                @error('form.klasifikasi')<p class="error">{{ $message }}</p>@enderror
            </div>
        @endif
    @endif

    <div class="sm:col-span-2">
        <label class="label">{{ $isian['perihal'] }}</label>
        <textarea wire:model="form.perihal" rows="2" class="input @error('form.perihal') input-error @enderror"></textarea>
        @error('form.perihal')<p class="error">{{ $message }}</p>@enderror
    </div>

    @isset($isian['pegawai'])
        <div class="sm:col-span-2" x-data="{ q: '', buka: false }">
            <label class="label">{{ $isian['pegawai'] }}</label>
            <div class="rounded-xl border border-line @error('form.pegawai') !border-rose-400 @enderror">
                <div class="flex flex-wrap gap-1.5 p-2">
                    @forelse ($form['pegawai'] ?? [] as $nama)
                        <span class="chip bg-bps-blue-50 text-bps-blue-700 ring-bps-blue-100" wire:key="pg-{{ md5($nama) }}">{{ $nama }}
                            <button type="button" wire:click="$set('form.pegawai', {{ json_encode(array_values(array_diff($form['pegawai'], [$nama]))) }})" class="-mr-0.5 hover:text-rose-600" aria-label="Hapus">×</button></span>
                    @empty
                        <span class="px-1 text-sm text-navy-400">Belum ada pegawai dipilih</span>
                    @endforelse
                </div>
                <div class="border-t border-line">
                    <input x-model="q" @focus="buka = true" type="search" placeholder="Cari & centang nama pegawai…" class="w-full border-0 bg-transparent px-3 py-2 text-sm focus:ring-0 focus:outline-none">
                    <div x-show="buka || q" x-cloak class="max-h-52 overflow-y-auto border-t border-line">
                        @foreach ($pegawaiList as $p)
                            @php $n = $p->name ?: $p->username; @endphp
                            <label class="flex cursor-pointer items-center gap-2 px-3 py-1.5 text-sm hover:bg-navy-50" x-show="!q || {{ json_encode(mb_strtolower($n.' '.$p->nip)) }}.includes(q.toLowerCase())" wire:key="pl-{{ $p->id }}">
                                <input type="checkbox" value="{{ $n }}" wire:model.live="form.pegawai" class="rounded border-line"> {{ $n }} <span class="text-xs text-navy-400">{{ $p->nip }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
            @error('form.pegawai')<p class="error">{{ $message }}</p>@enderror
        </div>
    @endisset

    @isset($isian['pelaksanaan'])
        <div>
            <label class="label">{{ $isian['pelaksanaan'] }} — mulai</label>
            <input type="date" wire:model="form.tanggal_mulai" class="input">
        </div>
        <div>
            <label class="label">s.d.</label>
            <input type="date" wire:model="form.tanggal_selesai" class="input @error('form.tanggal_selesai') input-error @enderror">
            @error('form.tanggal_selesai')<p class="error">{{ $message }}</p>@enderror
        </div>
    @endisset

    @isset($isian['tempat'])
        <div class="sm:col-span-2"><label class="label">{{ $isian['tempat'] }}</label><input wire:model="form.tempat" class="input"></div>
    @endisset

    @isset($isian['pihak'])
        <div class="sm:col-span-2">
            <label class="label">{{ $isian['pihak'] }}</label>
            <input wire:model="form.pihak" class="input @error('form.pihak') input-error @enderror">
            @error('form.pihak')<p class="error">{{ $message }}</p>@enderror
        </div>
    @endisset

    <details class="group sm:col-span-2" @if (! $baru) open @endif>
        <summary class="cursor-pointer text-sm font-semibold text-bps-blue-700">Lampiran & keterangan (opsional)</summary>
        <div class="mt-3 grid gap-4 sm:grid-cols-2">
            <div><label class="label">File / scan surat</label><input type="file" wire:model="file" class="input">@error('file')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label">Tautan dokumen</label><input wire:model="form.link" class="input" placeholder="https://drive.google.com/…">@error('form.link')<p class="error">{{ $message }}</p>@enderror</div>
            <div class="sm:col-span-2"><label class="label">Keterangan</label><textarea wire:model="form.keterangan" rows="2" class="input"></textarea></div>
        </div>
    </details>
</div>
