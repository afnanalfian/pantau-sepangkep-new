<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Matriks Kinerja {{ bulan_tahun($month) }}</title>
<style>
    @page { margin: 22px 26px 30px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #0B2A4A; }
    .head { width: 100%; border-bottom: 2px solid #0B2A4A; padding-bottom: 6px; margin-bottom: 4px; }
    .head td { vertical-align: middle; }
    .stripe { height: 3px; }
    .stripe span { display: inline-block; height: 3px; width: 33.2%; }
    h1 { font-size: 14px; margin: 0; }
    .sub { color: #4a6688; font-size: 9px; }
    table.m { width: 100%; border-collapse: collapse; margin-top: 10px; }
    table.m th { background: #0B2A4A; color: #fff; font-weight: bold; padding: 5px 4px; border: 1px solid #0B2A4A; font-size: 8.5px; }
    table.m td { border: 1px solid #D5DEE8; padding: 4px; }
    table.m tr:nth-child(even) td { background: #F4F7FB; }
    .c { text-align: center; } .r { text-align: right; }
    .ok { color: #57841f; font-weight: bold; } .warn { color: #b36306; font-weight: bold; } .bad { color: #c0262d; font-weight: bold; }
    .note { margin-top: 8px; color: #4a6688; font-size: 8.5px; line-height: 1.5; }
    .sign { width: 100%; margin-top: 22px; }
    .sign td { width: 50%; vertical-align: top; }
    .badge { padding: 1px 5px; border-radius: 6px; }
    .draft { color: #b36306; font-weight: bold; }
</style>
</head>
<body>
@php
    $logo = public_path('images/logo-bps-256.png');
    $cls = fn ($v) => $v === null ? '' : ($v >= 100 ? 'ok' : ($v <= 80 ? 'bad' : 'warn'));
@endphp
<table class="head">
    <tr>
        <td style="width:46px">@if (is_file($logo))<img src="{{ $logo }}" style="width:40px">@endif</td>
        <td>
            <h1>Matriks Kinerja &amp; Potongan Tunjangan Kinerja</h1>
            <div class="sub">{{ \App\Support\Settings::get('office_name') }} · Periode {{ bulan_tahun($month) }}
                @unless ($final)<span class="draft"> · SEMENTARA (bulan berjalan)</span>@endunless</div>
        </td>
        <td class="r sub">Dicetak {{ now()->format('d/m/Y H:i') }}<br>PANTAU SEPANGKEP</td>
    </tr>
</table>
<div class="stripe"><span style="background:#00A3E0"></span><span style="background:#8CC63F"></span><span style="background:#F5951F"></span></div>

<table class="m">
    <thead>
        <tr>
            <th rowspan="2">No</th><th rowspan="2">NIP</th><th rowspan="2">Nama</th><th rowspan="2">Jabatan</th>
            <th colspan="2">Penyelesaian tugas ({{ $w['tugas'] + 0 }})</th>
            <th colspan="2">Kehadiran apel ({{ $w['apel'] + 0 }})</th>
            <th colspan="2">Kehadiran rapat ({{ $w['rapat'] + 0 }})</th>
            <th rowspan="2">Nilai akhir</th><th rowspan="2">Potongan TK</th>
        </tr>
        <tr><th>Selesai</th><th>%</th><th>Hadir</th><th>%</th><th>Hadir</th><th>%</th></tr>
    </thead>
    <tbody>
        @foreach ($rows as $i => $r)
            <tr>
                <td class="c">{{ $i + 1 }}</td>
                <td>{{ $r['user']->nip ?: '-' }}</td>
                <td>{{ $r['user']->display_name }}</td>
                <td>{{ $r['user']->jabatan }}</td>
                <td class="c">{{ $r['tugas_selesai'] }}/{{ $r['tugas_total'] }}</td>
                <td class="r {{ $cls($r['tugas_pct']) }}">{{ $fmt($r['tugas_pct']) }}</td>
                <td class="c">{{ $r['apel_hadir'] }}/{{ $r['apel_wajib'] }}</td>
                <td class="r {{ $cls($r['apel_pct']) }}">{{ $fmt($r['apel_pct']) }}</td>
                <td class="c">{{ $r['rapat_hadir'] }}/{{ $r['rapat_wajib'] }}</td>
                <td class="r {{ $cls($r['rapat_pct']) }}">{{ $fmt($r['rapat_pct']) }}</td>
                <td class="r {{ $cls($r['nilai']) }}">{{ $fmt($r['nilai']) }}%</td>
                <td class="c {{ $r['potongan'] <= 0 ? 'ok' : ($r['potongan'] < 2 ? 'warn' : 'bad') }}">{{ $r['potongan'] <= 0 ? '0%' : $fmt($r['potongan']).'%' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="note">
    Nilai akhir = (bobot tugas × % tugas + bobot apel × % apel + bobot rapat × % rapat) ÷ jumlah bobot; komponen tanpa data ("–") tidak ikut dihitung.
    Tugas dihitung selesai bila sudah diverifikasi paling lambat akhir bulan deadline; tugas bulan sebelumnya yang belum selesai ikut dihitung bulan ini.
    Izin, cuti, dan dinas luar tidak dihitung sebagai ketidakhadiran.
    Potongan TK: nilai 100% = 0%; di atas {{ \App\Support\Settings::get('batas_potongan', 80) }}% = {{ \App\Support\Settings::get('potongan_ringan', 1) }}%; {{ \App\Support\Settings::get('batas_potongan', 80) }}% ke bawah = {{ \App\Support\Settings::get('potongan_berat', 2) }}%.
</div>

@isset($detail)
    <h1 style="margin-top:16px;font-size:11px">Rincian tugas yang dihitung</h1>
    <table class="m">
        <thead><tr><th>No</th><th>Kode</th><th>Tugas</th><th>Deadline</th><th>Status</th><th>Keterangan</th></tr></thead>
        <tbody>
            @forelse ($detail['tugas_items'] as $j => $it)
                <tr>
                    <td class="c">{{ $j + 1 }}</td><td>{{ $it['task']->code }}</td><td>{{ $it['task']->title }}</td>
                    <td class="c">{{ \Illuminate\Support\Carbon::parse($it['due'])->format('d/m/Y') }}</td>
                    <td class="c {{ $it['done'] ? 'ok' : ($it['pending'] ? '' : 'bad') }}">{{ $it['done'] ? 'Selesai' : ($it['pending'] ? 'Belum jatuh tempo' : 'Belum selesai') }}</td>
                    <td>{{ $it['carry'] ? 'Tunggakan bulan sebelumnya' : '' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="c">Tidak ada tugas ber-deadline di bulan ini.</td></tr>
            @endforelse
        </tbody>
    </table>
@endisset

<table class="sign">
    <tr>
        <td></td>
        <td class="c">
            Pangkajene, {{ tgl($final ? $month->copy()->endOfMonth() : now(), 'j F Y') }}<br>
            Kepala BPS Kabupaten Pangkajene dan Kepulauan<br><br><br><br><br>
            <b>{{ $pimpinan?->display_name ?? '................................' }}</b><br>
            NIP {{ $pimpinan?->nip ?? '................................' }}
        </td>
    </tr>
</table>
</body>
</html>
