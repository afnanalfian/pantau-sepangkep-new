<?php

namespace App\Http\Controllers;

use App\Models\Surat;
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

    public function export(Request $request)
    {
        $jenis = $request->input('jenis') === 'masuk' ? 'masuk' : 'keluar';
        $tahun = (int) ($request->input('tahun') ?: now()->year);
        $rows = Surat::where('jenis', $jenis)->where('tahun', $tahun)->orderBy('nomor_urut')->get()
            ->map(fn ($s) => [
                $s->nomor_urut, $s->nomor_surat, $s->tanggal->format('d/m/Y'), $s->sifat ? Surat::SIFAT[$s->sifat] ?? $s->sifat : '',
                $s->klasifikasi, $s->perihal, $s->pihak, $s->nomor_asal, $s->pembuat, $s->keterangan, $s->link,
            ]);

        return Spreadsheet::download(
            'sicatur_surat_'.$jenis.'_'.$tahun.'.xlsx',
            'Surat '.ucfirst($jenis),
            ['Urut', 'Nomor Surat', 'Tanggal', 'Sifat', 'Klasifikasi', 'Perihal', $jenis === 'keluar' ? 'Tujuan' : 'Asal', 'Nomor Asal', 'Pembuat', 'Keterangan', 'Link'],
            $rows,
            'SICATUR – Register Surat '.($jenis === 'keluar' ? 'Keluar' : 'Masuk').' '.$tahun,
            'BPS Kabupaten Pangkajene dan Kepulauan · dicetak '.now()->format('d/m/Y H:i')
        );
    }
}
