{{--
  Satu isian form SICATUR.
  $f = definisi isian, $model = 'isian.st' | 'uIsian', $utama = tanggal dasar nomor,
  $mode = 'baru' | 'ubah', $batas = ['min','max'] (ubah), $terkunci (ubah), $nilai (ubah, untuk tampilan terkunci)
--}}
@php
    $penuh = in_array($f['tipe'], ['klasifikasi', 'deskripsi', 'tautan'], true);
    $wm = $model.'.'.$f['attr'];
    $id = str_replace('.', '-', $wm);
    $errKey = $mode === 'baru' ? 'isian.'.$wm : 'isian.ubah.'.$f['attr'];
@endphp
<div class="{{ $penuh ? 'sm:col-span-2' : '' }} min-w-0">
    @if (! empty($terkunci))
        <p class="label">{{ $f['kolom'] }}</p>
        <div class="rounded-xl bg-paper px-3.5 py-2.5 text-sm">{{ $nilai ?: '-' }}</div>
        <p class="hint">Tidak bisa diubah karena nomor urut dihitung menurut kolom ini.</p>
    @else
        <label for="{{ $id }}" class="label">{{ $f['kolom'] }}</label>

        @switch($f['tipe'])
            @case('klasifikasi')
                @if ($adaKlasifikasi)
                    <div x-data="sicaturKlas($wire.entangle('{{ $wm }}').live, @js($urlKlasifikasi))" class="relative" @click.outside="tutup()">
                        <input id="{{ $id }}" type="text" x-model="teks" @input="ketik()" @focus="fokus($event)" @keydown="tombol($event)" @blur="blur()"
                               role="combobox" autocomplete="off" spellcheck="false" :aria-expanded="buka" class="input"
                               placeholder="Ketik kode atau kata kunci klasifikasi" :title="teks">
                        <ul x-show="buka" x-cloak class="absolute inset-x-0 top-full z-30 mt-1 max-h-72 overflow-y-auto rounded-xl border border-line bg-white p-1 shadow-lg" role="listbox">
                            <template x-if="memuat"><li class="px-3 py-2.5 text-sm text-navy-500">Memuat daftar klasifikasi…</li></template>
                            <template x-if="!memuat && gagal"><li class="px-3 py-2.5 text-sm text-rose-600">Daftar klasifikasi gagal dimuat. Periksa koneksi, lalu ketik lagi atau muat ulang halaman.</li></template>
                            <template x-if="!memuat && !gagal && !hasil.length"><li class="px-3 py-2.5 text-sm text-navy-500">Tidak ada klasifikasi yang cocok.</li></template>
                            <template x-for="(k, i) in hasil" :key="k.s + k.k">
                                <li role="option" @mousedown.prevent="pilih(k)" @mouseenter="aktif = i" :class="i === aktif ? 'bg-bps-blue-50' : ''"
                                    class="grid cursor-pointer grid-cols-[5.5rem_1fr_auto] gap-2.5 rounded-lg px-2.5 py-2 text-sm">
                                    <span class="font-semibold tabular-nums" x-text="k.k"></span>
                                    <span x-text="k.u"></span>
                                    <span class="hidden self-center text-xs text-navy-400 sm:inline" x-text="k.s"></span>
                                </li>
                            </template>
                        </ul>
                    </div>
                @else
                    <input id="{{ $id }}" wire:model.live.debounce.400ms="{{ $wm }}" class="input font-mono uppercase" placeholder="mis. SS.150">
                    <p class="hint">Daftar kode klasifikasi belum diimpor admin — ketik kodenya.</p>
                @endif
                @break

            @case('tanggal')
                <input id="{{ $id }}" type="date" wire:model.live="{{ $wm }}" class="input"
                       @if ($utama && $mode === 'baru') max="{{ $hariIni }}" @endif
                       @if ($mode === 'ubah' && $utama && $batas) min="{{ $batas['min'] }}" max="{{ $batas['max'] }}" @endif>
                @if ($mode === 'ubah' && $utama && $batas)
                    <p class="hint">Bisa diubah antara {{ \Illuminate\Support\Carbon::parse($batas['min'])->translatedFormat('j M Y') }} dan {{ \Illuminate\Support\Carbon::parse($batas['max'])->translatedFormat('j M Y') }} agar urutan nomor tetap sesuai tanggal.</p>
                @endif
                @php $v = data_get($this, $wm); @endphp
                @if ($utama && $mode === 'baru' && $v && $v > $hariIni)
                    <p class="error">Tanggal tidak boleh setelah hari ini.</p>
                @endif
                @if ($utama && $mode === 'baru' && $v && $v < $hariIni)
                    <label class="mt-2 flex cursor-pointer gap-2.5 rounded-xl border border-bps-blue-100 bg-bps-blue-50/60 px-3 py-2.5 text-sm">
                        <input type="checkbox" wire:model.live="mundur.{{ $jenis }}" class="mt-0.5 size-4 shrink-0 rounded border-line">
                        <span><b>Tanggal mundur / sisipkan nomor.</b> <span class="text-navy-600">Surat ini bertanggal sebelum hari ini. Bila sudah ada nomor yang lebih baru, nomor diselipkan setelah nomor terakhir pada tanggal itu (mis. 100 tgl 5, 101 tgl 10 → surat tgl 7 menjadi 100.1).</span></span>
                    </label>
                @endif
                @break

            @case('pembuat')
                <input id="{{ $id }}" type="text" disabled value="{{ $mode === 'baru' ? $pembuat : data_get($this, $wm) }}"
                       class="input cursor-not-allowed !bg-paper !text-navy-700">
                <p class="hint">{{ $mode === 'baru' ? 'Otomatis sesuai akun yang sedang masuk.' : 'Akun yang menerbitkan nomor ini.' }}</p>
                @break

            @case('pilihan')
                <select id="{{ $id }}" wire:model.live="{{ $wm }}" class="input">
                    <option value="">Pilih {{ mb_strtolower($f['kolom']) }}</option>
                    @foreach ($f['opsi'] as $o)<option value="{{ $o }}">{{ $o }}</option>@endforeach
                </select>
                @break

            @case('tautan')
                <input id="{{ $id }}" type="text" inputmode="url" autocomplete="off" spellcheck="false" maxlength="500" wire:model.live.debounce.400ms="{{ $wm }}" class="input" placeholder="https://drive.google.com/...">
                <p class="hint">Tempel tautan (link), bukan nama.</p>
                @if ($f['attr'] === 'nama_petugas')
                    <a href="{{ route('portal.sicatur.format-petugas') }}" class="mt-1.5 inline-flex items-center gap-1.5 text-sm font-semibold text-bps-blue-700 hover:underline">
                        <x-icon name="download" class="size-4" /> Unduh format Excel daftar petugas (Nama, Jabatan)</a>
                    <p class="hint">Isi format itu, unggah ke Google Drive, lalu tempel tautannya di sini.</p>
                @endif
                @php $v = trim((string) data_get($this, $wm)); @endphp
                @if ($v !== '' && ! preg_match(\App\Services\SicaturService::POLA_TAUTAN, $v))
                    <p class="error">Harus berupa tautan yang diawali http:// atau https:// tanpa spasi.</p>
                @endif
                @break

            @default
                <textarea id="{{ $id }}" rows="2" maxlength="300" wire:model.live.debounce.400ms="{{ $wm }}" class="input"></textarea>
        @endswitch
        @error($errKey)<p class="error">{{ $message }}</p>@enderror
    @endif
</div>
