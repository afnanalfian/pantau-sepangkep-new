@php
    use App\Models\Surat;
    use Illuminate\Support\Carbon;
    $tgl = fn ($d) => $d ? Carbon::parse($d)->translatedFormat('j M Y') : '';
    $lk = config('sicatur.lengkapi');
    $tabs = ['beranda' => 'Beranda'] + collect($jenisList)->map(fn ($c) => $c['label'])->all()
        + ['riwayat' => 'Riwayat', 'link' => $lk['label'].($menunggu ? ' ('.$menunggu.')' : '')];
    if (auth()->user()->is_admin) { $tabs['pengaturan'] = 'Pengaturan'; }
@endphp
<div x-data class="sicatur">
    {{-- Kepala --}}
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <img src="{{ asset('images/portal/si-catur.png') }}" alt="" class="size-11 object-contain">
            <span class="text-[22px] font-bold tracking-tight">SICATUR</span>
        </div>
        <div class="flex items-center gap-4">
            <span class="hidden text-right text-sm text-navy-500 md:inline">{{ config('sicatur.nama_instansi') }}</span>
            @if ($pedoman)
                <a href="{{ $pedoman }}" target="_blank" rel="noopener noreferrer" class="rounded-lg px-3 py-1.5 text-sm font-semibold whitespace-nowrap text-bps-blue-700 ring-[1.5px] ring-bps-blue ring-inset hover:bg-bps-blue-50">Buku Pedoman</a>
            @endif
        </div>
    </div>

    {{-- Tab --}}
    <nav class="-mx-1 mb-6 flex gap-1.5 overflow-x-auto border-y border-line bg-white px-1 py-2.5 sm:rounded-2xl sm:border" role="tablist" aria-label="Jenis surat">
        @foreach ($tabs as $k => $label)
            <button type="button" role="tab" wire:click="pilihTab('{{ $k }}')" aria-selected="{{ $tab === $k ? 'true' : 'false' }}"
                    class="shrink-0 rounded-[10px] px-4 py-2 text-[15px] font-medium transition {{ $tab === $k ? 'bg-bps-blue-50 text-bps-blue-700' : 'text-navy-500 hover:bg-navy-50 hover:text-navy-900' }}">{{ $label }}</button>
        @endforeach
    </nav>

    {{-- ===================== BERANDA ===================== --}}
    @if ($tab === 'beranda')
        <h1 class="text-2xl font-medium">Terbitkan nomor surat</h1>
        <p class="mb-5 text-navy-500">Pilih jenis surat. Nomor langsung tercatat dan tidak bisa kembar.</p>
        <div class="grid gap-5 md:grid-cols-2">
            @foreach ($jenisList as $j => $c)
                @php $k = $kartu[$j]; @endphp
                <article class="card p-6" wire:key="kartu-{{ $j }}">
                    <h2 class="text-lg font-medium">{{ $c['judul'] }}</h2>
                    <p class="mb-3.5 text-sm text-navy-500">{{ $k['total'] }} nomor tercatat</p>
                    <div class="mb-4 min-h-[3.2em]">
                        @if ($k['terakhir'])
                            <span class="block font-medium tabular-nums break-all">{{ $k['terakhir']->nomorTampil() }}</span>
                            <span class="text-[13px] text-navy-500">Terakhir dicatat {{ $tgl($k['terakhir']->tanggal) }}</span>
                        @else
                            Belum ada nomor.
                        @endif
                    </div>
                    <button wire:click="pilihTab('{{ $j }}')" class="btn btn-blue">Buka form {{ $c['label'] }}</button>
                </article>
            @endforeach
        </div>
    @endif

    {{-- ===================== FORM JENIS ===================== --}}
    @if (isset($jenisList[$tab]))
        @php $jenis = $tab; $h = $hasil[$jenis] ?? null; @endphp
        <section class="card mb-5 p-5 sm:p-6" wire:key="form-{{ $jenis }}">
            <h1 class="text-xl font-medium">{{ str_starts_with($c['judul'], 'Form') ? $c['judul'] : 'Form '.$c['judul'] }}</h1>
            <p class="mb-4 text-sm text-navy-500">Semua kolom wajib diisi. Nomor dibuat otomatis dan tidak bisa kembar.</p>

            <div class="grid gap-x-5 gap-y-4 sm:grid-cols-2">
                @foreach ($c['form'] as $f)
                    @include('livewire.portal.partials.sicatur-bidang', ['f' => $f, 'model' => 'isian.'.$jenis, 'utama' => $c['tanggal'] === $f['attr'], 'mode' => 'baru', 'terkunci' => false])
                @endforeach
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-3" x-data="{ salin: 'Salin nomor', tautan: 'Salin tautan untuk pengunggah' }">
                <button wire:click="terbitkan('{{ $jenis }}')" wire:loading.attr="disabled" wire:target="terbitkan" class="btn btn-blue" @disabled($kurang)>
                    <x-icon name="plus" class="size-4" />
                    <span wire:loading.remove wire:target="terbitkan">Terbitkan nomor {{ $c['label'] }}</span>
                    <span wire:loading wire:target="terbitkan">Memproses…</span>
                </button>
                @if ($h)
                    <div class="rounded-[10px] bg-[#d8edf8] px-4 py-2.5 text-lg font-medium tabular-nums break-all text-[#0e3a57]" x-ref="nomor">{{ $h['nomor'] }}</div>
                    <button class="btn btn-blue" @click="navigator.clipboard.writeText($refs.nomor.innerText.trim()); salin = 'Tersalin'; setTimeout(() => salin = 'Salin nomor', 1800)">
                        <x-icon name="clip" class="size-4" /> <span x-text="salin"></span></button>
                    <button wire:click="bukaUbah({{ $h['id'] }})" class="btn btn-ghost !text-bps-blue-700 ring-[1.5px] ring-bps-blue ring-inset"><x-icon name="pencil" class="size-4" /> Ubah isian</button>
                    @if ($h['tautan'])
                        <button class="btn btn-ghost !text-bps-blue-700 ring-[1.5px] ring-bps-blue ring-inset" @click="navigator.clipboard.writeText(@js($h['tautan'])); tautan = 'Tersalin'; setTimeout(() => tautan = 'Salin tautan untuk pengunggah', 1800)">
                            <x-icon name="link" class="size-4" /> <span x-text="tautan"></span></button>
                    @endif
                @endif
            </div>
            @if (! $h && $kurang)
                <p class="mt-2.5 text-[13px] text-navy-500">Belum lengkap: {{ implode(', ', $kurang) }}.</p>
            @endif
            @if (! $h && $pratinjau)
                <div class="mt-3 rounded-[10px] border px-3.5 py-2.5 text-sm {{ $pratinjau['sisipan'] ? 'border-bps-orange-100 bg-bps-orange-50' : 'border-line bg-paper' }}">
                    <span class="text-navy-500">Nomor yang akan terbit:</span>
                    <b class="tabular-nums break-all">{{ $pratinjau['nomor'] ?: 'No Urut '.$pratinjau['noUrut'] }}</b>
                    @if ($pratinjau['sisipan'])
                        <span class="block text-[13px] text-bps-orange-700">Nomor sisipan setelah No Urut {{ $pratinjau['induk'] }}, karena sudah ada nomor bertanggal lebih baru.</span>
                    @elseif (! $pratinjau['terjaga'])
                        <span class="block text-[13px] text-navy-500">Belum ada nomor sebelum tanggal ini, jadi memakai nomor berikutnya.</span>
                    @endif
                    <span class="block text-xs text-navy-400">Perkiraan — bisa bergeser bila ada yang menerbitkan nomor bersamaan.</span>
                </div>
            @endif
            @if ($h)
                <p class="mt-2.5 text-sm">{{ $h['catatan'] }}</p>
            @endif
            @error('terbit.'.$jenis)<p class="mt-2.5 rounded-[10px] bg-rose-50 px-3 py-2.5 text-sm text-rose-700">{{ $message }}</p>@enderror
        </section>

        <section class="card p-5 sm:p-6">
            <h2 class="text-xl font-medium">Nomor terbaru ({{ $totalTab }} tercatat)</h2>
            @if ($terbaru->isEmpty())
                <p class="mt-3 text-navy-500">Belum ada nomor tahun {{ now()->year }}.</p>
            @else
                <ol class="mt-3">
                    @foreach ($terbaru as $s)
                        <li class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3.5 border-t border-line py-2.5 sm:grid-cols-[minmax(0,1.3fr)_minmax(0,1.7fr)_auto]" wire:key="t-{{ $s->id }}" x-data="{ s: 'Salin' }">
                            <span class="font-medium tabular-nums break-all">{{ $s->nomorTampil() }}</span>
                            <div class="order-3 col-span-2 min-w-0 text-sm text-navy-500 sm:order-none sm:col-span-1">
                                <span class="block truncate">{{ $tgl($s->tanggal) }}</span>
                                <span class="block truncate">{{ $s->perihal }}</span>
                            </div>
                            <div class="flex flex-wrap justify-end gap-2">
                                @if ($s->lengkap)
                                    <button class="btn btn-sm bg-bps-blue-50 text-bps-blue-700 hover:bg-bps-blue-100" @click="navigator.clipboard.writeText(@js($s->nomor_surat)); s = 'Tersalin'; setTimeout(() => s = 'Salin', 1800)"><x-icon name="clip" class="size-3.5" /> <span x-text="s"></span></button>
                                @endif
                                <button wire:click="bukaUbah({{ $s->id }})" class="btn btn-sm bg-white text-bps-blue-700 ring-[1.5px] ring-bps-blue ring-inset hover:bg-bps-blue-50"><x-icon name="pencil" class="size-3.5" /> Ubah</button>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    @endif

    {{-- ===================== RIWAYAT ===================== --}}
    @if ($tab === 'riwayat')
        <h1 class="text-2xl font-medium">Riwayat nomor</h1>
        <p class="mb-5 text-navy-500">Cari nomor yang sudah terbit. Isian yang salah bisa diubah tanpa menghapus nomornya.</p>
        <section class="card mb-5 p-5 sm:p-6">
            <form wire:submit="cari" class="grid gap-x-5 gap-y-4 sm:grid-cols-3">
                <div class="sm:col-span-3"><label class="label" for="r-kata">Kata kunci</label><input id="r-kata" type="search" wire:model="rKata" class="input" autocomplete="off" placeholder="Nomor, perihal, nama, atau kode"></div>
                <div><label class="label" for="r-jenis">Jenis surat</label>
                    <select id="r-jenis" wire:model.live="rJenis" class="input"><option value="">Semua jenis</option>@foreach ($jenisList as $j => $c)<option value="{{ $j }}">{{ $c['label'] }}</option>@endforeach</select></div>
                <div><label class="label" for="r-dari">Dari tanggal</label><input id="r-dari" type="date" wire:model="rDari" class="input">@error('rDari')<p class="error">{{ $message }}</p>@enderror</div>
                <div><label class="label" for="r-sampai">Sampai tanggal</label><input id="r-sampai" type="date" wire:model="rSampai" class="input"></div>
                <div class="flex flex-wrap items-center gap-3 sm:col-span-3">
                    <button class="btn btn-blue">Cari</button>
                    <button type="button" wire:click="aturUlang" class="px-1 text-sm font-medium text-bps-blue-700 underline">Atur ulang</button>
                    <a href="{{ route('portal.sicatur.export', array_filter($rKriteria)) }}" class="ml-auto text-sm font-semibold text-bps-blue-700"><x-icon name="download" class="inline size-4" /> Unduh Excel</a>
                </div>
            </form>
        </section>
        <section class="card p-5 sm:p-6">
            <h2 class="text-xl font-medium">Hasil pencarian</h2>
            <p class="mt-1 text-[13px] text-navy-500">
                @if ($hasilCari->total()) Menampilkan {{ $hasilCari->firstItem() }}–{{ $hasilCari->lastItem() }} dari {{ $hasilCari->total() }} nomor. @else Tidak ada nomor yang cocok. @endif
            </p>
            <ol class="mt-2">
                @foreach ($hasilCari as $s)
                    @include('livewire.portal.partials.sicatur-hasil', ['s' => $s])
                @endforeach
            </ol>
            @if ($hasilCari->lastPage() > 1)
                <div class="mt-4 flex items-center justify-between gap-3">
                    <button wire:click="previousPage('rh')" @disabled($hasilCari->onFirstPage()) class="btn btn-sm bg-bps-blue-50 text-bps-blue-700 disabled:opacity-50">Sebelumnya</button>
                    <span class="text-[13px] text-navy-500">Halaman {{ $hasilCari->currentPage() }} dari {{ $hasilCari->lastPage() }}</span>
                    <button wire:click="nextPage('rh')" @disabled(! $hasilCari->hasMorePages()) class="btn btn-sm bg-bps-blue-50 text-bps-blue-700 disabled:opacity-50">Berikutnya</button>
                </div>
            @endif
        </section>
    @endif

    {{-- ===================== LINK SURAT (SK) ===================== --}}
    @if ($tab === 'link')
        <h1 class="text-2xl font-medium">Link surat {{ $jenisList[$lk['jenis']]['judul'] }}</h1>
        <p class="mb-5 text-navy-500">Daftar surat yang sudah bernomor dan menunggu link surat. Lihat isinya, lalu tempel link surat yang sudah diunggah. Status berubah menjadi {{ $lk['selesai'] }} otomatis.</p>
        <section class="card mb-5 p-5 sm:p-6">
            <form wire:submit="cariLink" class="grid gap-x-5 gap-y-4 sm:grid-cols-[2fr_1fr]">
                <div><label class="label" for="l-kata">Kata kunci</label><input id="l-kata" type="search" wire:model="lKata" class="input" autocomplete="off" placeholder="Nomor, perihal, atau nama petugas"></div>
                <div><label class="label" for="l-status">Tampilkan</label>
                    <select id="l-status" wire:model.live="lStatus" class="input">
                        <option value="menunggu">Menunggu link ({{ $hitung['menunggu'] }})</option>
                        <option value="selesai">Sudah ada link ({{ $hitung['selesai'] }})</option>
                        <option value="semua">Semua ({{ $hitung['semua'] }})</option>
                    </select></div>
                <div><button class="btn btn-blue">Cari</button></div>
            </form>
        </section>
        <section class="card p-5 sm:p-6">
            <h2 class="text-xl font-medium">Daftar surat</h2>
            <p class="mt-1 text-[13px] text-navy-500">
                @if ($daftarLink->total()) Menampilkan {{ $daftarLink->firstItem() }}–{{ $daftarLink->lastItem() }} dari {{ $daftarLink->total() }} nomor.
                @else {{ $lStatus === 'menunggu' && ! $lKataAktif ? 'Tidak ada SK yang menunggu link surat.' : 'Tidak ada nomor yang cocok.' }} @endif
            </p>
            <ol class="mt-2">
                @foreach ($daftarLink as $s)
                    @php $ks = $s->kunciStatus(); @endphp
                    <li class="grid gap-x-4 gap-y-1 border-t border-line py-3.5 sm:grid-cols-[minmax(0,1fr)_auto]" wire:key="l-{{ $s->id }}">
                        <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1">
                            <span class="text-base font-medium tabular-nums break-all">{{ $s->nomorTampil() }}</span>
                            <span class="rounded-md px-2 py-0.5 text-xs font-medium {{ $ks === 'selesai' ? 'bg-[#dff3e6] text-[#145a32]' : ($ks === 'menunggu' ? 'bg-[#fff3d6] text-[#7a4b00]' : 'bg-navy-50 text-navy-500') }}">
                                {{ $ks === 'selesai' ? $lk['selesai'] : ($ks === 'menunggu' ? 'Menunggu link' : 'Data lama') }}</span>
                        </div>
                        <div class="flex flex-wrap justify-start gap-2 sm:row-span-3 sm:justify-end sm:self-start">
                            @if ($s->link_surat && preg_match('/^https?:\/\//i', $s->link_surat))
                                <a href="{{ $s->link_surat }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm bg-white text-bps-blue-700 ring-[1.5px] ring-bps-blue ring-inset">Buka surat</a>
                            @endif
                            <button wire:click="bukaLink({{ $s->id }})" class="btn btn-sm {{ $ks === 'selesai' ? 'bg-white text-bps-blue-700 ring-[1.5px] ring-bps-blue ring-inset' : 'bg-bps-blue-50 text-bps-blue-700' }}"><x-icon name="link" class="size-3.5" /> {{ $ks === 'selesai' ? 'Lihat / ganti link' : 'Isi link' }}</button>
                        </div>
                        <div class="text-sm text-navy-500">{{ collect([$tgl($s->tanggal), $s->pembuat])->filter()->implode(' · ') }}</div>
                        @include('livewire.portal.partials.sicatur-detail', ['s' => $s])
                    </li>
                @endforeach
            </ol>
            @if ($daftarLink->lastPage() > 1)
                <div class="mt-4 flex items-center justify-between gap-3">
                    <button wire:click="previousPage('lh')" @disabled($daftarLink->onFirstPage()) class="btn btn-sm bg-bps-blue-50 text-bps-blue-700 disabled:opacity-50">Sebelumnya</button>
                    <span class="text-[13px] text-navy-500">Halaman {{ $daftarLink->currentPage() }} dari {{ $daftarLink->lastPage() }}</span>
                    <button wire:click="nextPage('lh')" @disabled(! $daftarLink->hasMorePages()) class="btn btn-sm bg-bps-blue-50 text-bps-blue-700 disabled:opacity-50">Berikutnya</button>
                </div>
            @endif
        </section>
    @endif

    {{-- ===================== PENGATURAN (admin) ===================== --}}
    @if ($tab === 'pengaturan' && auth()->user()->is_admin)
        <h1 class="text-2xl font-medium">Pengaturan SICATUR</h1>
        <p class="mb-5 text-navy-500">Hanya admin. Impor data dari spreadsheet SICATUR lama, buku pedoman, dan webhook SK.</p>
        <div class="grid gap-5 lg:grid-cols-2">
            <form wire:submit="imporSpreadsheet" class="card space-y-3 p-5 sm:p-6">
                <h2 class="text-lg font-medium">Impor dari spreadsheet SICATUR lama</h2>
                <p class="text-sm text-navy-600">Di Google Sheets SICATUR lama: <b>File › Download › Microsoft Excel (.xlsx)</b>, lalu unggah di sini. Yang diimpor:</p>
                <ul class="list-disc pl-5 text-sm text-navy-600">
                    <li>Sheet <b>Klas Teknis</b> & <b>Klas Fasilitatif</b> → daftar kode klasifikasi (kolom A & F, mulai baris 3).</li>
                    <li>Sheet <b>Pegawai</b> → nomor WA ke profil pegawai yang namanya sama (bila HP-nya masih kosong).</li>
                    <li>Sheet <b>{{ collect($jenisList)->pluck('sheet')->implode(', ') }}</b> → semua nomor beserta isiannya (header baris 4).</li>
                </ul>
                <p class="text-sm text-navy-600">Aman diulang: nomor yang sama diperbarui, tidak dobel. Nomor berikutnya otomatis melanjutkan dari data impor.</p>
                <input type="file" wire:model="imporFile" accept=".xlsx" class="input">
                @error('imporFile')<p class="error">{{ $message }}</p>@enderror
                <button class="btn btn-blue" wire:loading.attr="disabled" wire:target="imporSpreadsheet,imporFile">
                    <span wire:loading.remove wire:target="imporSpreadsheet">Impor</span><span wire:loading wire:target="imporSpreadsheet">Mengimpor…</span></button>
                @if ($laporanImpor)<p class="rounded-lg bg-bps-green-50 px-3 py-2 text-sm text-bps-green-700">{{ $laporanImpor }}</p>@endif
                <p class="text-xs text-navy-500">Kode klasifikasi tersimpan: {{ \App\Models\SicaturKlasifikasi::count() }}.</p>
            </form>

            <form wire:submit="simpanPengaturan" class="card space-y-4 p-5 sm:p-6">
                <h2 class="text-lg font-medium">Buku pedoman & webhook</h2>
                <div><label class="label">Tautan buku pedoman</label><input wire:model="pedomanUrl" class="input">@error('pedomanUrl')<p class="error">{{ $message }}</p>@enderror</div>
                <div><label class="label">atau unggah PDF</label><input type="file" wire:model="pedomanFile" accept="application/pdf" class="input">@error('pedomanFile')<p class="error">{{ $message }}</p>@enderror
                    @if (\App\Support\Settings::get('sicatur_pedoman_file'))<p class="hint">PDF tersimpan dan dipakai tombol Buku Pedoman. <button type="button" wire:click="hapusPedomanFile" class="text-rose-600">Hapus PDF</button></p>@endif</div>
                @foreach (['sk' => ['webhookSk', 'Webhook saat nomor SK baru terbit', 'pembuatan_sk'], 'link' => ['webhookSkLink', 'Webhook saat link surat SK diisi', 'upload_link_sk']] as $wj => [$prop, $judul, $event])
                    <div>
                        <label class="label">{{ $judul }} <span class="font-normal text-navy-400">(opsional · event {{ $event }})</span></label>
                        <div class="flex gap-2"><input wire:model="{{ $prop }}" class="input" placeholder="https://…">
                            <button type="button" wire:click="ujiWebhook('{{ $wj }}')" wire:loading.attr="disabled" wire:target="ujiWebhook" class="btn btn-ghost shrink-0">Uji</button></div>
                        @error($prop)<p class="error">{{ $message }}</p>@enderror
                        @if ($hasilUji && $hasilUji['jenis'] === $wj)
                            <p class="mt-1.5 rounded-lg px-3 py-2 text-xs {{ $hasilUji['terkirim'] ? 'bg-bps-green-50 text-bps-green-700' : 'bg-rose-50 text-rose-700' }}">{{ $hasilUji['pesan'] }}@isset($hasilUji['kodeStatus']) (HTTP {{ $hasilUji['kodeStatus'] }})@endisset</p>
                        @endif
                    </div>
                @endforeach
                <p class="hint">Format JSON sama dengan Webhook.gs aplikasi lama. Nomor WA pemohon & pengunggah diambil dari nomor HP di profil pegawai (otomatis terisi dari sheet Pegawai saat impor). Tombol Uji mengirim data contoh “NOMOR 999”.</p>
                <button class="btn btn-blue">Simpan</button>
            </form>
        </div>
    @endif

    {{-- ===================== Modal: ubah data nomor ===================== --}}
    @if ($uSurat)
        @php $uc = Surat::cfg($uSurat->jenis); @endphp
        <x-modal :show="true" close="tutupUbah" title="Ubah data nomor" width="max-w-[720px]">
            <div class="mb-2 flex flex-col gap-0.5 rounded-[10px] bg-[#d8edf8] px-3.5 py-2.5 text-[#0e3a57]">
                <span class="text-[13px]">{{ $uc['judul'] }}</span>
                <span class="text-[17px] font-medium tabular-nums break-all">{{ $uSurat->nomorTampil() }}</span>
            </div>
            <p class="mb-4 text-sm text-navy-500">Nomor urut tidak berubah dan datanya tidak dihapus. Setiap perubahan tercatat di log perubahan.</p>
            <div class="grid gap-x-5 gap-y-4 sm:grid-cols-2">
                @foreach ($uc['form'] as $f)
                    @include('livewire.portal.partials.sicatur-bidang', ['f' => $f, 'model' => 'uIsian', 'utama' => $uc['tanggal'] === $f['attr'], 'mode' => 'ubah',
                        'batas' => $uBatas, 'terkunci' => in_array($f['attr'], $uc['kunciUbah'], true), 'nilai' => $uSurat->nilai($f['attr']), 'jenis' => $uSurat->jenis])
                @endforeach
            </div>
            <div class="mt-5 grid gap-x-5 gap-y-4 border-t border-line pt-4 sm:grid-cols-2">
                <div><p class="label">Diubah oleh</p><div class="rounded-xl bg-paper px-3.5 py-2.5 text-sm">{{ auth()->user()->name ?: auth()->user()->username }}</div></div>
                <div><label class="label" for="u-alasan">Alasan (boleh dikosongkan)</label><input id="u-alasan" wire:model="uAlasan" maxlength="200" class="input" placeholder="Mis. salah pilih kode klasifikasi"></div>
            </div>
            @error('ubah')<p class="mt-3 rounded-[10px] bg-rose-50 px-3 py-2.5 text-sm text-rose-700">{{ $message }}</p>@enderror
            @if ($uKonfirmasi)
                <div class="mt-3.5 rounded-[10px] border border-[#f0d49b] bg-[#fff7e6] px-4 py-3.5">
                    <strong>Nomor akan berubah</strong>
                    <div class="my-2 grid grid-cols-[4.5rem_1fr] gap-x-2.5 gap-y-1 text-sm">
                        <span class="text-navy-500">Dari</span><span class="font-medium break-all tabular-nums">{{ $uKonfirmasi['lama'] ?: '(belum tersusun)' }}</span>
                        <span class="text-navy-500">Menjadi</span><span class="font-medium break-all tabular-nums">{{ $uKonfirmasi['baru'] }}</span>
                    </div>
                    <p class="text-[13px] text-navy-500">Pastikan surat belum dicetak atau dikirim dengan nomor lama.</p>
                    <div class="mt-2.5 flex justify-end gap-2">
                        <button wire:click="$set('uKonfirmasi', null)" class="btn btn-ghost">Kembali</button>
                        <button wire:click="simpanUbah(true)" class="btn btn-blue">Ya, ubah nomor</button>
                    </div>
                </div>
            @endif
            @if ($uSurat->logs->isNotEmpty())
                <details class="mt-4 text-sm">
                    <summary class="cursor-pointer font-medium text-bps-blue-700">Riwayat perubahan ({{ $uSurat->logs->count() }})</summary>
                    <ul class="mt-2 space-y-1.5">
                        @foreach ($uSurat->logs as $lg)
                            <li class="rounded-lg bg-paper px-3 py-2"><b>{{ $lg->kolom }}</b>: <span class="text-navy-500 line-through">{{ $lg->nilai_lama ?: '–' }}</span> → {{ $lg->nilai_baru ?: '–' }}
                                <span class="block text-xs text-navy-500">{{ $lg->created_at->translatedFormat('j M Y H:i') }} · {{ $lg->oleh }}{{ $lg->alasan ? ' · '.$lg->alasan : '' }}</span></li>
                        @endforeach
                    </ul>
                </details>
            @endif
            <x-slot:footer>
                @unless ($uKonfirmasi)
                    <button wire:click="tutupUbah" class="btn btn-ghost">Batal</button>
                    <button wire:click="simpanUbah" class="btn btn-blue" wire:loading.attr="disabled">Simpan perubahan</button>
                @endunless
            </x-slot:footer>
        </x-modal>
    @endif

    {{-- ===================== Modal: link surat ===================== --}}
    @if ($lSurat)
        <x-modal :show="true" close="tutupLink" title="Link surat" width="max-w-[720px]">
            <div class="mb-2 flex flex-col gap-0.5 rounded-[10px] bg-[#d8edf8] px-3.5 py-2.5 text-[#0e3a57]">
                <span class="text-[13px]">{{ $jenisList[$lk['jenis']]['judul'] }} · {{ $lSurat->kunciStatus() === 'selesai' ? $lk['selesai'] : ($lSurat->kunciStatus() === 'menunggu' ? 'Menunggu link' : 'Data lama') }}</span>
                <span class="text-[17px] font-medium tabular-nums break-all">{{ $lSurat->nomorTampil() }}</span>
            </div>
            <p class="mb-3 text-sm text-navy-500">Periksa isi surat di bawah, lalu tempel link surat yang sudah diunggah.</p>
            <div class="rounded-[10px] bg-[#f3f6fa] px-3 py-2.5">
                <dl class="grid gap-0.5 text-sm">
                    <div class="grid gap-x-2.5 sm:grid-cols-[8.5rem_1fr]"><dt class="text-navy-500">Tanggal surat</dt><dd>{{ $tgl($lSurat->tanggal) }}</dd></div>
                    <div class="grid gap-x-2.5 sm:grid-cols-[8.5rem_1fr]"><dt class="text-navy-500">Pembuat</dt><dd>{{ $lSurat->pembuat }}</dd></div>
                </dl>
                @include('livewire.portal.partials.sicatur-detail', ['s' => $lSurat])
            </div>
            @if ($lSurat->link_surat)
                <div class="mt-4 border-t border-line pt-4">
                    <p class="label">Link surat saat ini</p>
                    <div class="rounded-[10px] bg-[#f3f6fa] px-3 py-2.5 text-sm">
                        @if (preg_match('/^https?:\/\//i', $lSurat->link_surat))<a href="{{ $lSurat->link_surat }}" target="_blank" rel="noopener noreferrer" class="font-medium text-bps-blue-700">Buka link saat ini</a>@else{{ $lSurat->link_surat }}@endif
                        @if ($lSurat->link_oleh)<span class="text-navy-500"> · {{ $lSurat->link_oleh }}{{ $lSurat->link_at ? ', '.$lSurat->link_at->translatedFormat('j M Y') : '' }}</span>@endif
                    </div>
                    <p class="hint">Mengisi link baru akan menggantikan link ini. Perubahan tercatat di log perubahan.</p>
                </div>
            @endif
            <div class="mt-4 grid gap-x-5 gap-y-4 border-t border-line pt-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="label" for="l-link">Link Surat</label>
                    <input id="l-link" type="text" inputmode="url" autocomplete="off" spellcheck="false" maxlength="500" wire:model.live.debounce.300ms="linkBaru" class="input" placeholder="https://drive.google.com/...">
                    <p class="hint">Tempel tautan (link), bukan nama.</p>
                    @error('linkBaru')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div><p class="label">Diunggah oleh</p><div class="rounded-xl bg-paper px-3.5 py-2.5 text-sm">{{ auth()->user()->name ?: auth()->user()->username }}</div></div>
            </div>
            @php
                $lv = trim($linkBaru);
                $lOk = $lv !== '' && preg_match(\App\Services\SicaturService::POLA_TAUTAN, $lv) && $lv !== (string) $lSurat->link_surat;
            @endphp
            <p class="mt-2.5 text-[13px] text-navy-500">
                {{ $lv === '' ? 'Tempel link surat untuk menyimpan.' : (! preg_match(\App\Services\SicaturService::POLA_TAUTAN, $lv) ? 'Link harus diawali http:// atau https://.' : ($lv === (string) $lSurat->link_surat ? 'Link ini sama dengan yang tersimpan.' : '')) }}
            </p>
            <x-slot:footer>
                <button wire:click="tutupLink" class="btn btn-ghost">Batal</button>
                <button wire:click="simpanLink" class="btn btn-blue" @disabled(! $lOk) wire:loading.attr="disabled">{{ $lSurat->link_surat ? 'Ganti link surat' : 'Simpan link surat' }}</button>
            </x-slot:footer>
        </x-modal>
    @endif
<script>
    // Dropdown cari kode klasifikasi (sama seperti bidangKlasifikasi di Skrip.html).
    // Daftar diambil dari server per versi data (berubah setiap impor) dan tidak disimpan browser.
    window.__sicaturKlasAmbil = (url) => {
        window.__sicaturKlas ??= {};
        if (!window.__sicaturKlas[url]) {
            window.__sicaturKlas[url] = fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store', credentials: 'same-origin' })
                .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                .catch(e => { delete window.__sicaturKlas[url]; throw e; });
        }
        return window.__sicaturKlas[url];
    };
    window.sicaturKlas = (kode, url) => ({
        kode, url, teks: '', daftar: [], hasil: [], aktif: -1, buka: false, terpilih: null, gagal: false, memuat: true,
        async init() {
            await this.muat();
            this.setel(this.kode);
            this.$watch('kode', (v) => { if (!v) { if (this.terpilih) { this.terpilih = null; this.teks = ''; } } else if (!this.terpilih || this.terpilih.k !== v) this.setel(v); });
        },
        async muat() {
            this.memuat = true;
            try { this.daftar = await window.__sicaturKlasAmbil(this.url); this.gagal = false; }
            catch (e) { this.gagal = true; console.error('Daftar klasifikasi gagal dimuat', e); }
            this.memuat = false;
        },
        setel(k) {
            if (!k) { this.terpilih = null; this.teks = ''; return; }
            this.terpilih = this.daftar.find(x => x.k === k) || { k, u: '(tidak ada di daftar klasifikasi)', s: '' };
            this.teks = this.terpilih.k + ' – ' + this.terpilih.u;
        },
        cari(q) {
            const kata = q.toLowerCase().split(/\s+/).filter(Boolean);
            if (!kata.length) return [];
            const cocok = [];
            this.daftar.forEach((k, i) => {
                const kd = k.k.toLowerCase(), t = kd + ' ' + (k.u || '').toLowerCase();
                if (kata.every(w => t.includes(w))) cocok.push({ k, i, s: kd === kata[0] ? 0 : (kd.startsWith(kata[0]) ? 1 : 2) });
            });
            cocok.sort((a, b) => a.s - b.s || a.i - b.i);
            return cocok.slice(0, 40).map(x => x.k);
        },
        async ketik() {
            if (this.terpilih) { this.terpilih = null; this.kode = ''; }
            if (this.gagal) await this.muat();
            this.hasil = this.cari(this.teks); this.aktif = this.hasil.length ? 0 : -1; this.buka = this.teks.trim() !== '';
        },
        async fokus(e) {
            if (this.gagal) await this.muat();
            if (this.terpilih) { e.target.select(); return; }
            if (this.teks.trim()) { this.hasil = this.cari(this.teks); this.aktif = this.hasil.length ? 0 : -1; this.buka = true; }
        },
        pilih(k) { this.terpilih = k; this.teks = k.k + ' – ' + k.u; this.kode = k.k; this.buka = false; },
        tutup() { this.buka = false; },
        tombol(e) {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                if (this.terpilih) return;
                e.preventDefault();
                if (!this.buka) { this.hasil = this.cari(this.teks); this.aktif = this.hasil.length ? 0 : -1; this.buka = true; return; }
                if (!this.hasil.length) return;
                this.aktif = e.key === 'ArrowDown' ? (this.aktif + 1) % this.hasil.length : (this.aktif - 1 + this.hasil.length) % this.hasil.length;
                this.$nextTick(() => this.$root.querySelectorAll('[role=option]')[this.aktif]?.scrollIntoView({ block: 'nearest' }));
            } else if (e.key === 'Enter') {
                if (this.buka && this.aktif >= 0) { e.preventDefault(); this.pilih(this.hasil[this.aktif]); }
            } else if (e.key === 'Escape') {
                if (this.buka) { e.preventDefault(); e.stopPropagation(); this.tutup(); }
            }
        },
        blur() {
            setTimeout(() => {
                if (!this.terpilih) {
                    const q = this.teks.trim().toLowerCase();
                    const persis = q ? this.daftar.find(k => k.k.toLowerCase() === q) : null;
                    if (persis) { this.pilih(persis); return; }
                }
                this.tutup();
            }, 120);
        },
    });
</script>
</div>
