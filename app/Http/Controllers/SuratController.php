<?php

namespace App\Http\Controllers;

use App\Models\SicaturKlasifikasi;
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

        return redirect()->away(Settings::get('sicatur_pedoman_url', config('sicatur.url_pedoman')));
    }

    /** Daftar kode klasifikasi untuk dropdown cari: [{k, u, s}] */
    public function klasifikasi()
    {
        $data = SicaturKlasifikasi::query()->orderBy('sumber', 'desc')->orderBy('urutan')->orderBy('kode')
            ->get(['kode', 'uraian', 'sumber'])
            ->map(fn ($k) => ['k' => $k->kode, 'u' => (string) $k->uraian, 's' => $k->sumber]);

        return response()->json($data)->header('Cache-Control', 'no-store, max-age=0');
    }

    /** Ekspor hasil pencarian Riwayat. */
    public function export(Request $request)
    {
        $jenis = (string) $request->input('jenis', '');
        $kata = trim((string) $request->input('kata', ''));
        $q = Surat::query()
            ->when($jenis !== '', fn ($q) => $q->where('jenis', $jenis))
            ->when($request->input('dari'), fn ($q, $v) => $q->whereDate('tanggal', '>=', $v))
            ->when($request->input('sampai'), fn ($q, $v) => $q->whereDate('tanggal', '<=', $v))
            ->when($kata !== '', function ($q) use ($kata) {
                foreach (preg_split('/\s+/', $kata) as $w) {
                    $q->where(fn ($x) => $x->where('nomor_surat', 'like', "%{$w}%")->orWhere('perihal', 'like', "%{$w}%")
                        ->orWhere('tujuan', 'like', "%{$w}%")->orWhere('pembuat', 'like', "%{$w}%")
                        ->orWhere('klasifikasi', 'like', "%{$w}%")->orWhere('nama_petugas', 'like', "%{$w}%"));
                }
            })
            ->orderBy('jenis')->orderBy('tanggal')->orderBy('nomor_urut')->orderBy('sisip');

        $rows = $q->get()->map(fn (Surat $s) => [
            Surat::labelJenis($s->jenis), $s->no_urut ?: $s->nomor_urut, $s->nomorTampil(), $s->tanggal?->format('d/m/Y'),
            $s->klasifikasi, $s->tim_teknis, $s->jenis_fp, $s->tanggal_fp?->format('d/m/Y'),
            $s->pembuat, $s->tujuan, $s->perihal, $s->nama_petugas, $s->honor,
            $s->tanggal_mulai?->format('d/m/Y'), $s->tanggal_selesai?->format('d/m/Y'), $s->status, $s->link_surat,
        ]);

        return Spreadsheet::download(
            'sicatur_'.($jenis ?: 'semua').'_'.now()->format('Ymd').'.xlsx',
            'Riwayat',
            ['Jenis', 'No Urut', 'Format Nomor', 'Tanggal Surat', 'Kode Klasifikasi', 'Tim Teknis', 'Jenis FP', 'Tanggal FP', 'Pembuat', 'Tujuan Surat',
                'Perihal', 'Nama Petugas', 'Honor', 'Mulai Berlaku', 'Selesai Berlaku', 'Status', 'Link Surat'],
            $rows,
            'SICATUR – Riwayat Nomor',
            'BPS Kabupaten Pangkajene dan Kepulauan · dicetak '.now()->format('d/m/Y H:i')
        );
    }
}
