{{-- Daftar kegiatan lain yang membebani limit mitra pada periode kegiatan ini.
     Tombol "Lepas" menghapus mitra dari kegiatan tersebut agar bisa diikutkan ke kegiatan ini. --}}
@php use App\Support\Sibeban\Format; use App\Support\Sibeban\Periode; @endphp
<div class="mb-2 font-medium text-slate-800">Rincian honor berjalan {{ $nama }} di periode ini</div>
@if (empty($lain))
    <p class="text-sm text-slate-500">Belum ada honor berjalan maupun kegiatan lain pada bulan-bulan periode ini.</p>
@else
    <table class="w-full text-sm">
        <thead class="text-xs text-slate-500">
            <tr>
                <th class="py-1 text-left font-medium">Kegiatan</th>
                @foreach ($bulans as $b)<th class="px-2 py-1 text-right font-medium">{{ Periode::label($b) }}</th>@endforeach
                <th></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @foreach ($lain as $l)
                <tr wire:key="lain-{{ $l['penugasan_id'] ?? 'rekap' }}" class="{{ $l['penugasan_id'] ? '' : 'bg-amber-50/60' }}">
                    <td class="py-1.5 pr-2">
                        @if ($l['penugasan_id'])
                            <a href="{{ route('sibeban.kegiatan.show', $l['kegiatan_id']) }}" wire:navigate class="hover:text-sky-700">{{ $l['kegiatan'] }}</a>
                            <span class="ml-1 text-xs uppercase text-slate-400">{{ $l['jabatan'] }}</span>
                            <div class="text-xs text-slate-400">ditambahkan lewat aplikasi setelah rekap</div>
                        @else
                            <span class="font-medium text-amber-900">{{ $l['kegiatan'] }}</span>
                            @if ($l['keterangan'])<div class="text-xs text-slate-400">{{ $l['keterangan'] }}</div>@endif
                        @endif
                    </td>
                    @foreach ($bulans as $b)
                        <td class="px-2 py-1.5 text-right tabular text-xs">{{ isset($l['honor'][$b]) ? Format::jt($l['honor'][$b]) : '' }}</td>
                    @endforeach
                    <td class="py-1.5 pl-2 text-right">
                        @if ($l['penugasan_id'])
                        <button wire:click="lepas({{ $l['penugasan_id'] }})"
                                wire:confirm="Lepas {{ $nama }} dari kegiatan &quot;{{ $l['kegiatan'] }}&quot;? Seluruh honor mitra di kegiatan itu akan dihapus."
                                class="whitespace-nowrap rounded-md px-2 py-0.5 text-xs text-red-700 ring-1 ring-red-200 hover:bg-red-50">
                            Lepas
                        </button>
                        @else
                            <span class="text-xs text-slate-400">ubah di panel Atur</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="mt-2 text-xs text-slate-400">Baris kuning = honor berjalan dari rekap Excel terbaru. Kegiatan di bawahnya ditambahkan lewat aplikasi setelah rekap itu, jadi ikut dihitung; tombol Lepas mengeluarkan mitra dari kegiatan tersebut.</p>
@endif
