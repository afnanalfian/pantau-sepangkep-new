{{-- Rincian isian sebuah nomor ($s). Nilai tautan tampil sebagai "Buka tautan". --}}
@php
    $c = \App\Models\Surat::cfg($s->jenis);
    $baris = [];
    if ($c) {
        foreach ($c['form'] as $f) {
            if ($f['tipe'] === 'pembuat' || $f['attr'] === $c['tanggal']) continue;
            $v = $s->nilai($f['attr']);
            if ($v === '') continue;
            $baris[] = [$f['kolom'], $v, $f['tipe']];
        }
        if ($s->jenis === config('sicatur.lengkapi.jenis') && $s->link_surat) {
            $baris[] = ['Link Surat', $s->link_surat, 'tautan'];
        }
    } else {
        foreach (['Perihal' => $s->perihal, 'Pihak' => $s->pihak, 'Klasifikasi' => $s->klasifikasi] as $l => $v) {
            if ($v) $baris[] = [$l, $v, 'teks'];
        }
    }
@endphp
@if ($baris)
    <dl class="mt-1 grid gap-0.5 text-sm">
        @foreach ($baris as [$label, $v, $tipe])
            <div class="grid gap-x-2.5 sm:grid-cols-[8.5rem_1fr]">
                <dt class="text-navy-500">{{ $label }}</dt>
                <dd class="min-w-0 [overflow-wrap:anywhere]">
                    @if ($tipe === 'tautan' && preg_match('/^https?:\/\//i', $v))
                        <a href="{{ $v }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-bps-blue-700 hover:underline">Buka tautan</a>
                    @elseif ($tipe === 'tanggal')
                        {{ \Illuminate\Support\Carbon::parse($v)->translatedFormat('j M Y') }}
                    @else
                        {{ $v }}
                    @endif
                </dd>
            </div>
        @endforeach
    </dl>
@endif
