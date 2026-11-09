<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet as PhpSpreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Spreadsheet
{
    /**
     * Buat file Excel bergaya (header biru BPS) dan kirim sebagai unduhan.
     *
     * @param  array<int, string>  $headers
     * @param  iterable<array>  $rows
     */
    public static function download(string $filename, string $sheetTitle, array $headers, iterable $rows, ?string $title = null, ?string $note = null): StreamedResponse
    {
        $book = new PhpSpreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle(mb_substr($sheetTitle, 0, 31));
        $r = 1;

        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        if ($title) {
            $sheet->setCellValue('A1', $title);
            $sheet->mergeCells("A1:{$lastCol}1");
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('0B2A4A');
            $r = 2;
            if ($note) {
                $sheet->setCellValue('A2', $note);
                $sheet->mergeCells("A2:{$lastCol}2");
                $sheet->getStyle('A2')->getFont()->setItalic(true)->getColor()->setRGB('5B6B80');
                $r = 3;
            }
            $r++;
        }

        $headerRow = $r;
        foreach ($headers as $i => $h) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1).$r, $h);
        }
        $sheet->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0090C8']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension($r)->setRowHeight(24);

        foreach ($rows as $row) {
            $r++;
            foreach (array_values($row) as $i => $v) {
                $coord = Coordinate::stringFromColumnIndex($i + 1).$r;
                if (is_string($v) && preg_match('/^\d{9,}$/', $v)) {
                    $sheet->setCellValueExplicit($coord, $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValue($coord, $v);
                }
            }
        }

        if ($r > $headerRow) {
            $sheet->getStyle("A{$headerRow}:{$lastCol}{$r}")->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D5DEE8');
        }
        foreach (range(1, count($headers)) as $i) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }
        $sheet->freezePane('A'.($headerRow + 1));

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * Baca file Excel/CSV menjadi array baris asosiatif berdasarkan header (huruf kecil, spasi → _).
     *
     * @return array<int, array<string, string>>
     */
    public static function read(string $path): array
    {
        $book = IOFactory::load($path);
        $rows = $book->getActiveSheet()->toArray(null, true, false, false);
        $rows = array_values(array_filter($rows, fn ($r) => count(array_filter($r, fn ($v) => trim((string) $v) !== '')) > 0));
        if (! $rows) {
            return [];
        }

        $header = array_map(fn ($h) => self::clean(strtolower(str_replace([' ', '-'], '_', trim((string) $h)))), array_shift($rows));

        return array_map(function ($r) use ($header) {
            $out = [];
            foreach ($header as $i => $key) {
                if ($key !== '') {
                    $out[$key] = self::clean((string) ($r[$i] ?? ''));
                }
            }

            return $out;
        }, $rows);
    }

    /** Hapus karakter tak terlihat (zero-width, word joiner, NBSP) & spasi tepi. */
    public static function clean(string $v): string
    {
        $v = preg_replace('/[\x{200B}-\x{200F}\x{2060}-\x{2064}\x{FEFF}\x{00AD}]/u', '', $v);
        $v = str_replace("\u{00A0}", ' ', $v);

        return trim($v);
    }
}
