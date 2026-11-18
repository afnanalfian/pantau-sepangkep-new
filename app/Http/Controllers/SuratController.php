<?php

namespace App\Http\Controllers;

use App\Models\Surat;
use App\Support\Settings;
use App\Support\Spreadsheet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SuratController extends Controller
{
    public function file(Surat $surat)
    {
        abort_unless($surat->file_path && Storage::disk('local')->exists($surat->file_path), 404);

        return response()->file(Storage::disk('local')->path($surat->file_path), [
            'Content-Disposition' => 'inline; filename="'.addslashes($surat->file_name ?: 'surat').'"',
        ]);
    }

    public function pedoman()
    {
        $path = Settings::get('sicatur_pedoman_file');
        if ($path && Storage::disk('local')->exists($path)) {
            return response()->file(Storage::disk('local')->path($path), [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="Buku Pedoman SICATUR.pdf"',
            ]);
        }
        abort_unless($url = Settings::get('sicatur_pedoman_url'), 404);

        return redirect()->away($url);
    }

    public function export(Request $request)
    {
        $jenis = (string) $request->input('jenis', '');
        $tahun = (int) ($request->input('tahun') ?: now()->year);
        $rows = Surat::query()->where('tahun', $tahun)
            ->when($jenis !== '', fn ($q) => $q->where('jenis', $jenis))
            ->orderBy('jenis')->orderBy('nomor_urut')->get()
            ->map(fn ($s) => [
                Surat::labelJenis($s->jenis), $s->nomor_urut, $s->nomor_surat, $s->tanggal->format('d/m/Y'), $s->klasifikasi, $s->perihal,
                $s->pegawai ? implode(', ', $s->pegawai) : '',
                $s->tanggal_mulai ? $s->tanggal_mulai->format('d/m/Y').($s->tanggal_selesai ? ' – '.$s->tanggal_selesai->format('d/m/Y') : '') : '',
                $s->tempat, $s->pihak, $s->pembuat, $s->keterangan, $s->link,
            ]);
        $label = $jenis !== '' ? Surat::labelJenis($jenis) : 'Semua jenis';

        return Spreadsheet::download(
            'sicatur_'.($jenis ?: 'semua').'_'.$tahun.'.xlsx',
            'Riwayat',
            ['Jenis', 'Urut', 'Nomor', 'Tanggal', 'Klasifikasi', 'Perihal', 'Pegawai', 'Pelaksanaan', 'Tempat', 'Pihak', 'Pembuat', 'Keterangan', 'Link'],
            $rows,
            'SICATUR – Riwayat Nomor '.$label.' '.$tahun,
            'BPS Kabupaten Pangkajene dan Kepulauan · dicetak '.now()->format('d/m/Y H:i')
        );
    }

    /** Template impor riwayat dari SICATUR lama. */
    public function template()
    {
        $contoh = [];
        foreach (Surat::jenisList() as $kode => $j) {
            $contoh[] = [$j['singkat'], 1, Surat::rangkai($j, 1, now()->startOfYear(), $j['klasifikasi']), now()->startOfYear()->format('d/m/Y'), $j['klasifikasi'], 'Contoh perihal', '', 'Nama Pembuat'];
        }

        return Spreadsheet::download(
            'template-impor-riwayat-sicatur.xlsx',
            'Riwayat',
            ['Jenis', 'Nomor Urut', 'Nomor Surat', 'Tanggal', 'Klasifikasi', 'Perihal', 'Pihak', 'Pembuat'],
            $contoh,
            'Template impor riwayat SICATUR',
            'Jenis diisi: '.implode(' / ', array_map(fn ($j) => $j['singkat'], Surat::jenisList())).'. Hapus baris contoh sebelum impor.'
        );
    }
}
