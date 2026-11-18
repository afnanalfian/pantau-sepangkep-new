<?php

namespace App\Http\Controllers\Sibeban;

use App\Http\Controllers\Controller;

use App\Models\Sibeban\Kegiatan;
use App\Services\Sibeban\PenugasanExcelReader;
use App\Support\Sibeban\Format;
use App\Support\Sibeban\Periode;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TemplateController
{
    /** Template upload penugasan, kolom bulan menyesuaikan periode kegiatan. */
    public function penugasan(Kegiatan $kegiatan): StreamedResponse
    {
        $ss = new Spreadsheet();
        $ws = $ss->getActiveSheet()->setTitle('Penugasan');
        $judul = PenugasanExcelReader::judulTemplate($kegiatan);
        $ws->fromArray($judul, null, 'A1');
        $ws->getStyle('A1:'.$ws->getHighestColumn().'1')->getFont()->setBold(true);
        $ws->getStyle('A:A')->getNumberFormat()->setFormatCode('@');
        foreach (range(1, count($judul)) as $i) {
            $ws->getColumnDimensionByColumn($i)->setWidth($i === 2 ? 32 : 16);
        }
        $ws->freezePane('A2');

        $p = $ss->createSheet()->setTitle('Petunjuk');
        $baris = [
            ['Template penugasan: '.$kegiatan->nama],
            ['Periode: '.$kegiatan->tanggal_mulai->format('d/m/Y').' s.d. '.$kegiatan->tanggal_selesai->format('d/m/Y')
                .' ('.implode(', ', array_map(fn ($b) => Periode::label($b), $kegiatan->bulans())).')'],
            [''],
            ['1. Isi satu baris per calon petugas di sheet "Penugasan". Kolom sobat_id diisi ID SOBAT (format teks).'],
            ['2. Kolom jabatan diisi "ppl" atau "pml" — jabatan petugas di kegiatan ini.'],
            ['3. Kolom bulan diisi HONOR BERJALAN petugas (honor yang sedang diterima dari kegiatan lain) pada bulan itu. Kosong = 0.'],
            ['4. Honor kegiatan ini tidak perlu ditulis; sistem menambahkan otomatis:'],
            ['     PPL = '.Format::rp($kegiatan->honor_ppl).' / bulan,  PML = '.Format::rp($kegiatan->honor_pml).' / bulan'],
            ['5. Status = (honor berjalan + honor kegiatan ini) dibandingkan limit per bulan.'],
            ['6. Kolom nama opsional; dipakai bila ID SOBAT belum terdaftar di master mitra.'],
        ];
        $p->fromArray($baris, null, 'A1');
        $p->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $p->getColumnDimension('A')->setWidth(110);
        $ss->setActiveSheetIndex(0);

        return $this->unduh($ss, 'template-penugasan-'.Str::slug($kegiatan->nama).'.xlsx');
    }

    /** Template impor master mitra. */
    public function mitra(): StreamedResponse
    {
        $ss = new Spreadsheet();
        $ws = $ss->getActiveSheet()->setTitle('Mitra');
        $ws->fromArray(['sobat_id', 'nama', 'posisi', 'email'], null, 'A1');
        $ws->getStyle('A1:D1')->getFont()->setBold(true);
        $ws->getStyle('A:A')->getNumberFormat()->setFormatCode('@');
        foreach (['A' => 18, 'B' => 34, 'C' => 32, 'D' => 30] as $k => $w) {
            $ws->getColumnDimension($k)->setWidth($w);
        }

        return $this->unduh($ss, 'template-master-mitra.xlsx');
    }

    private function unduh(Spreadsheet $ss, string $nama): StreamedResponse
    {
        return response()->streamDownload(function () use ($ss) {
            (new Xlsx($ss))->save('php://output');
        }, $nama, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
