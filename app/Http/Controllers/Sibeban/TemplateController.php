<?php

namespace App\Http\Controllers\Sibeban;

use App\Livewire\Sibeban\Monitoring;
use App\Models\Sibeban\Kegiatan;
use App\Models\Sibeban\Mitra;
use App\Services\Sibeban\HonorService;
use App\Support\Sibeban\Periode;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TemplateController
{
    private const RUPIAH = '"Rp "#,##0';

    /**
     * Template honor kegiatan: Sobat ID, nama & wilayah petugas terpilih sudah terisi.
     * Yang perlu diisi: Jabatan dan Honor per bulan.
     */
    public function penugasan(Kegiatan $kegiatan): StreamedResponse
    {
        $bulans = $kegiatan->bulans();
        $penugasans = $kegiatan->penugasans()->with(['mitra', 'honors'])->get()
            ->sortBy(fn ($p) => [(string) $p->mitra->kecamatan, (string) $p->mitra->desa, $p->mitra->nama])->values();

        $ss = new Spreadsheet();
        $ws = $ss->getActiveSheet()->setTitle('Honor Petugas');
        $judul = array_merge(['No', 'Sobat ID', 'Nama', 'Kecamatan', 'Desa/Kelurahan', 'Jabatan'], array_map(fn ($b) => 'Honor '.Periode::label($b), $bulans));
        $ws->fromArray($judul, null, 'A1');
        $akhir = Coordinate::stringFromColumnIndex(count($judul));

        foreach ($penugasans as $i => $p) {
            $r = $i + 2;
            $ws->setCellValue("A{$r}", $i + 1);
            $ws->setCellValueExplicit("B{$r}", $p->mitra->sobat_id, DataType::TYPE_STRING);
            $ws->setCellValue("C{$r}", $p->mitra->nama);
            $ws->setCellValue("D{$r}", $p->mitra->kecamatan);
            $ws->setCellValue("E{$r}", $p->mitra->desa);
            $ws->setCellValue("F{$r}", $p->jabatan);
            $honor = $p->honorPerBulan();
            foreach ($bulans as $j => $b) {
                if (! empty($honor[$b])) {
                    $ws->setCellValue(Coordinate::stringFromColumnIndex(7 + $j).$r, $honor[$b]);
                }
            }
        }
        $last = max(2, $penugasans->count() + 1);

        $this->gayaJudul($ws, "A1:{$akhir}1");
        // Kolom yang diisi pengguna diberi warna
        $ws->getStyle("F2:{$akhir}{$last}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF7E6');
        $ws->getStyle("G2:{$akhir}{$last}")->getNumberFormat()->setFormatCode(self::RUPIAH);
        $ws->getStyle("A2:E{$last}")->getFont()->getColor()->setRGB('475569');
        foreach (['A' => 5, 'B' => 18, 'C' => 32, 'D' => 18, 'E' => 22, 'F' => 16] as $k => $w) {
            $ws->getColumnDimension($k)->setWidth($w);
        }
        foreach ($bulans as $j => $b) {
            $ws->getColumnDimension(Coordinate::stringFromColumnIndex(7 + $j))->setWidth(18);
        }
        $ws->freezePane('D2');

        // Honor harus angka ≥ 0
        $v = $ws->getCell('G2')->getDataValidation();
        $v->setType(DataValidation::TYPE_WHOLE)->setOperator(DataValidation::OPERATOR_GREATERTHANOREQUAL)->setFormula1('0')
            ->setAllowBlank(true)->setShowErrorMessage(true)->setErrorTitle('Honor')->setError('Isi angka rupiah penuh tanpa titik, mis. 1500000');
        $ws->setDataValidation("G2:{$akhir}".($last + 200), clone $v);

        $limit = app(HonorService::class)->limit();
        $p = $ss->createSheet()->setTitle('Petunjuk');
        $p->fromArray([
            ['Template honor petugas — '.$kegiatan->nama],
            ['Periode: '.$kegiatan->tanggal_mulai->format('d/m/Y').' s.d. '.$kegiatan->tanggal_selesai->format('d/m/Y')],
            [''],
            ['1. Sobat ID, Nama, Kecamatan, dan Desa sudah terisi dari petugas yang dipilih di SIBEBAN. Jangan ubah kolom Sobat ID.'],
            ['2. Isi kolom Jabatan (mis. PPL, PML, Pengolah) dan Honor per bulan (kolom berwarna).'],
            ['3. Honor ditulis angka rupiah penuh, mis. 1500000. Kosong = 0. Honor tiap petugas boleh berbeda.'],
            ['4. Boleh menambah baris petugas lain asalkan Sobat ID-nya sudah ada di database mitra.'],
            ['5. Hapus baris petugas yang batal dipakai, atau keluarkan dari halaman kegiatan.'],
            ['6. Simpan, lalu unggah lewat tombol "Impor honor" di halaman kegiatan.'],
            [''],
            ['Limit honor per mitra per bulan saat ini: Rp '.number_format($limit, 0, ',', '.')],
        ], null, 'A1');
        $p->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $p->getColumnDimension('A')->setWidth(110);
        $ss->setActiveSheetIndex(0);

        return $this->unduh($ss, 'template-honor-'.Str::slug($kegiatan->nama).'.xlsx');
    }

    /** Template impor database mitra (admin). */
    public function mitra(): StreamedResponse
    {
        $ss = new Spreadsheet();
        $ws = $ss->getActiveSheet()->setTitle('Mitra');
        $ws->fromArray(['Sobat ID', 'Nama', 'Kecamatan', 'Desa/Kelurahan', 'Posisi', 'Email'], null, 'A1');
        $ws->setCellValueExplicit('A2', '730900000001', DataType::TYPE_STRING);
        $ws->fromArray(['Contoh Nama Mitra', 'Pangkajene', 'Mappasaile', 'Mitra Pendataan', 'contoh@gmail.com'], null, 'B2');
        $ws->getStyle('A2:F2')->getFont()->setItalic(true)->getColor()->setRGB('94A3B8');
        $this->gayaJudul($ws, 'A1:F1');
        $ws->getStyle('A:A')->getNumberFormat()->setFormatCode('@');
        foreach (['A' => 18, 'B' => 34, 'C' => 20, 'D' => 24, 'E' => 24, 'F' => 30] as $k => $w) {
            $ws->getColumnDimension($k)->setWidth($w);
        }
        $ws->freezePane('A2');

        return $this->unduh($ss, 'template-database-mitra.xlsx');
    }

    /** Daftar database mitra lengkap (admin). */
    public function ekspor(): StreamedResponse
    {
        $ss = new Spreadsheet();
        $ws = $ss->getActiveSheet()->setTitle('Mitra');
        $ws->fromArray(['Sobat ID', 'Nama', 'Kecamatan', 'Desa/Kelurahan', 'Posisi', 'Email'], null, 'A1');
        $r = 2;
        foreach (Mitra::orderBy('kecamatan')->orderBy('desa')->orderBy('nama')->cursor() as $m) {
            $ws->setCellValueExplicit("A{$r}", $m->sobat_id, DataType::TYPE_STRING);
            $ws->fromArray([$m->nama, $m->kecamatan, $m->desa, $m->posisi, $m->email], null, "B{$r}");
            $r++;
        }
        $this->gayaJudul($ws, 'A1:F1');
        foreach (['A' => 18, 'B' => 34, 'C' => 20, 'D' => 24, 'E' => 24, 'F' => 30] as $k => $w) {
            $ws->getColumnDimension($k)->setWidth($w);
        }
        $ws->freezePane('A2');
        $ws->setAutoFilter('A1:F'.max(1, $r - 1));

        return $this->unduh($ss, 'database-mitra-'.now()->format('Ymd').'.xlsx');
    }

    /** Ekspor ketersediaan mitra satu bulan sesuai filter di halaman monitoring. */
    public function monitoring(Request $request): StreamedResponse
    {
        $bulan = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('bulan')) ? $request->query('bulan') : now()->format('Y-m');
        $f = $request->only(['cari', 'kecamatan', 'desa', 'posisi', 'ketersediaan', 'urut']) + ['ketersediaan' => 'tersedia'];
        $hasil = Monitoring::data($bulan, $f);

        $ss = new Spreadsheet();
        $ws = $ss->getActiveSheet()->setTitle('Ketersediaan');
        $ws->setCellValue('A1', 'Ketersediaan mitra — '.Periode::label($bulan, false));
        $ws->setCellValue('A2', 'Limit honor per mitra per bulan: Rp '.number_format($hasil['limit'], 0, ',', '.').' · dicetak '.now()->format('d/m/Y H:i'));
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $ws->fromArray(['No', 'Sobat ID', 'Nama', 'Kecamatan', 'Desa/Kelurahan', 'Posisi', 'Honor bulan ini', 'Sisa limit', 'Persen', 'Kegiatan'], null, 'A4');
        $r = 5;
        foreach ($hasil['baris'] as $i => $b) {
            $m = $b['mitra'];
            $ws->setCellValue("A{$r}", $i + 1);
            $ws->setCellValueExplicit("B{$r}", $m->sobat_id, DataType::TYPE_STRING);
            $ws->fromArray([$m->nama, $m->kecamatan, $m->desa, $m->posisi, $b['total'], $b['sisa'], $b['persen'] / 100, implode('; ', $b['kegiatan'])], null, "C{$r}");
            $r++;
        }
        $this->gayaJudul($ws, 'A4:J4');
        $ws->getStyle("G5:H{$r}")->getNumberFormat()->setFormatCode(self::RUPIAH);
        $ws->getStyle("I5:I{$r}")->getNumberFormat()->setFormatCode('0.0%');
        foreach (['A' => 5, 'B' => 18, 'C' => 32, 'D' => 18, 'E' => 22, 'F' => 20, 'G' => 18, 'H' => 18, 'I' => 9, 'J' => 60] as $k => $w) {
            $ws->getColumnDimension($k)->setWidth($w);
        }
        $ws->freezePane('D5');

        return $this->unduh($ss, 'ketersediaan-mitra-'.$bulan.'.xlsx');
    }

    private function gayaJudul(Worksheet $ws, string $range): void
    {
        $s = $ws->getStyle($range);
        $s->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $s->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0B2545');
    }

    private function unduh(Spreadsheet $ss, string $nama): StreamedResponse
    {
        return response()->streamDownload(function () use ($ss) {
            (new Xlsx($ss))->save('php://output');
        }, $nama, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
