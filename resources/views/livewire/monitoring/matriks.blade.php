@php $rk = $this->ringkasan; @endphp
<div>
    <x-page-header title="Matriks kinerja & potongan TK" subtitle="Penyelesaian tugas, kehadiran apel, dan kehadiran rapat per pegawai. Dasar perhitungan potongan tunjangan kinerja.">
        <x-slot:actions>
            <input type="month" wire:model.live="month" class="input w-auto py-2" aria-label="Bulan">
            <a href="{{ route('matriks.excel', ['bulan' => $month, 'tim' => $team]) }}" class="btn btn-green"><x-icon name="download" class="size-4" /> Excel</a>
            <a href="{{ route('matriks.pdf', ['bulan' => $month, 'tim' => $team]) }}" class="btn btn-primary"><x-icon name="document" class="size-4" /> PDF</a>
        </x-slot:actions>
    </x-page-header>

    @if ($isCurrent)
        <div class="mb-5 flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <x-icon name="clock" class="size-5 shrink-0" />
            <p><b>{{ $monthLabel }} masih berjalan.</b> Angka di bawah bersifat sementara: tugas yang deadline-nya belum tiba belum dihitung (kecuali sudah selesai). Nilai final ditetapkan setelah bulan berakhir.</p>
        </div>
    @endif

    {{-- Ringkasan --}}
    <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="card p-4">
            <p class="text-xs text-navy-500">Rata-rata nilai akhir</p>
            <p class="mt-1 text-3xl font-extrabold">{{ $rk['rata'] !== null ? rtrim(rtrim(number_format($rk['rata'], 2, ',', '.'), '0'), ',').'%' : '–' }}</p>
            <p class="text-xs text-navy-500">{{ $rk['pegawai'] }} pegawai</p>
        </div>
        @foreach ([['0', 'Tidak dipotong', $rk['p0'], 'bg-bps-green'], ['1', 'Potong '.\App\Support\Settings::get('potongan_ringan', 1).'%', $rk['p1'], 'bg-amber-400'], ['2', 'Potong '.\App\Support\Settings::get('potongan_berat', 2).'%', $rk['p2'], 'bg-rose-500']] as [$k, $l, $n, $c])
            <button wire:click="$set('potongan', '{{ $potongan === $k ? '' : $k }}')" class="card flex items-center gap-3 p-4 text-left transition {{ $potongan === $k ? 'ring-2 ring-navy-900' : 'hover:border-navy-300' }}">
                <span class="slant h-10 w-3 {{ $c }}"></span>
                <span><span class="block text-3xl font-extrabold">{{ $n }}</span><span class="text-xs text-navy-500">{{ $l }}</span></span>
            </button>
        @endforeach
    </div>

    <div class="mb-4 flex flex-wrap gap-2">
        <div class="relative min-w-56 flex-1 sm:max-w-sm">
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-navy-300" />
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari nama / NIP" class="input py-2 pl-9">
        </div>
        <select wire:model.live="team" class="input w-auto py-2">
            <option value="">{{ auth()->user()->is_admin || auth()->user()->is_pimpinan || auth()->user()->is_keuangan ? 'Semua tim' : 'Tim saya' }}</option>
            @foreach ($this->teams as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
        </select>
        <p class="ml-auto self-center text-xs text-navy-500">Bobot: tugas {{ $w['tugas'] + 0 }} · apel {{ $w['apel'] + 0 }} · rapat {{ $w['rapat'] + 0 }}</p>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[920px] text-sm">
                <thead class="table-head">
                    <tr>
                        <th class="px-5 py-3">Pegawai</th>
                        <th class="px-3 py-3">Penyelesaian tugas</th>
                        <th class="px-3 py-3 text-right">Apel</th>
                        <th class="px-3 py-3 text-right">Rapat</th>
                        <th class="px-3 py-3 text-right">Nilai akhir</th>
                        <th class="px-5 py-3 text-right">Potongan TK</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($list as $r)
                        <tr class="cursor-pointer hover:bg-paper/70" wire:click="$set('detailId', {{ $r['user']->id }})" wire:key="mx-{{ $r['user']->id }}">
                            <td class="px-5 py-3">
                                <span class="flex items-center gap-3"><x-avatar :user="$r['user']" size="sm" />
                                    <span class="min-w-0"><span class="block truncate font-semibold">{{ $r['user']->display_name }}</span>
                                        <span class="block truncate text-xs text-navy-500">{{ $r['user']->nip ?: '@'.$r['user']->username }}</span></span></span>
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex items-center gap-3">
                                    <x-completion-bar class="w-36" :selesai="$r['tugas_selesai']" :berjalan="$r['tugas_berjalan']" :gagal="$r['tugas_gagal']" />
                                    <x-pct :value="$r['tugas_pct']" :num="$r['tugas_selesai']" :den="$r['tugas_total']" />
                                </div>
                            </td>
                            <td class="px-3 py-3 text-right"><x-pct :value="$r['apel_pct']" :num="$r['apel_hadir']" :den="$r['apel_wajib']" /></td>
                            <td class="px-3 py-3 text-right"><x-pct :value="$r['rapat_pct']" :num="$r['rapat_hadir']" :den="$r['rapat_wajib']" /></td>
                            <td class="px-3 py-3 text-right text-base"><x-pct :value="$r['nilai']" /></td>
                            <td class="px-5 py-3 text-right"><x-potongan :value="$r['potongan']" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty icon="chart" title="Belum ada pegawai" text="Pastikan pegawai sudah aktif dan masuk tim." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="border-t border-line px-5 py-3 text-xs leading-relaxed text-navy-500">
            Tanda “–” berarti tidak ada data pada komponen itu (mis. tidak ada rapat), sehingga komponen tersebut tidak ikut dihitung.
            Potongan: nilai 100% tidak dipotong · di atas {{ \App\Support\Settings::get('batas_potongan', 80) }}% dipotong {{ \App\Support\Settings::get('potongan_ringan', 1) }}% · {{ \App\Support\Settings::get('batas_potongan', 80) }}% ke bawah dipotong {{ \App\Support\Settings::get('potongan_berat', 2) }}%. Klik baris untuk melihat rinciannya.
        </p>
    </div>

    {{-- Rincian pegawai --}}
    <x-modal :show="(bool) $detail" close="$set('detailId', null)" width="max-w-2xl" :title="$detail['user']->display_name ?? ''" :subtitle="'Rincian matriks '.$monthLabel">
        @if ($detail)
            <div class="grid grid-cols-3 gap-3 text-center">
                <div class="rounded-2xl bg-paper p-3"><x-ring class="mx-auto" :pct="$detail['tugas_pct']" color="text-bps-blue" /><p class="mt-1 text-xs font-semibold">Tugas</p></div>
                <div class="rounded-2xl bg-paper p-3"><x-ring class="mx-auto" :pct="$detail['apel_pct']" color="text-bps-orange" /><p class="mt-1 text-xs font-semibold">Apel</p></div>
                <div class="rounded-2xl bg-paper p-3"><x-ring class="mx-auto" :pct="$detail['rapat_pct']" color="text-bps-green" /><p class="mt-1 text-xs font-semibold">Rapat</p></div>
            </div>
            <div class="mt-4 flex items-center justify-between rounded-2xl bg-navy-900 px-5 py-4 text-white">
                <span><span class="block text-xs text-navy-100/70">Nilai akhir</span><span class="text-3xl font-extrabold">{{ rtrim(rtrim(number_format($detail['nilai'], 2, ',', '.'), '0'), ',') }}%</span></span>
                <x-potongan :value="$detail['potongan']" class="text-sm" />
            </div>
            <h3 class="mt-5 mb-2 text-sm font-bold">Tugas tanggungan bulan ini ({{ $detail['tugas_semua'] }})</h3>
            <ul class="divide-y divide-line rounded-2xl border border-line">
                @forelse ($detail['tugas_items'] as $it)
                    <li class="flex items-center gap-3 px-4 py-2.5 text-sm">
                        <span class="grid size-6 shrink-0 place-items-center rounded-full {{ $it['done'] ? 'bg-bps-green text-white' : ($it['pending'] ? 'bg-bps-blue-50 text-bps-blue-700' : 'bg-rose-50 text-rose-600') }}" title="{{ $it['done'] ? 'Selesai' : ($it['pending'] ? 'Belum jatuh tempo' : 'Belum selesai, lewat deadline') }}"><x-icon :name="$it['done'] ? 'check' : ($it['pending'] ? 'clock' : 'x')" class="size-3.5" /></span>
                        <a href="{{ route('tasks.show', $it['task']->id) }}" wire:navigate class="min-w-0 flex-1 truncate hover:text-bps-blue-700">{{ $it['task']->title }}</a>
                        @if ($it['carry'])<span class="chip bg-rose-50 text-rose-700 ring-rose-600/20">Tunggakan</span>@endif
                        <span class="text-xs whitespace-nowrap text-navy-500">{{ tgl($it['due'], 'j M') }}</span>
                    </li>
                @empty
                    <li class="px-4 py-3 text-sm text-navy-500">Tidak ada tugas ber-deadline di bulan ini.</li>
                @endforelse
            </ul>
            <p class="mt-3 text-xs text-navy-500">Apel: hadir {{ $detail['apel_hadir'] }} dari {{ $detail['apel_wajib'] }}{{ $detail['apel_izin'] ? ' ('.$detail['apel_izin'].' izin)' : '' }} · Rapat: hadir {{ $detail['rapat_hadir'] }} dari {{ $detail['rapat_wajib'] }}{{ $detail['rapat_izin'] ? ' ('.$detail['rapat_izin'].' izin)' : '' }}</p>
        @endif
    </x-modal>
</div>
