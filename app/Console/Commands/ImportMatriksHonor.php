<?php

namespace App\Console\Commands;

use App\Models\Sibeban\HonorBerjalan;
use App\Models\Sibeban\Kegiatan;
use App\Models\Sibeban\Mitra;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Mengimpor data awal dari "Matrix Honor Mitra BPS 7309 2026.xlsx":
 *   - sheet Petugas   → master mitra
 *   - sheet JAN … DES → rekap HONOR BERJALAN per mitra per bulan
 *                       (jumlah kolom "Honor yang Dibayarkan" untuk setiap ID di bulan itu)
 *
 * Contoh: php -d memory_limit=1G artisan honor:import-matriks Matrix_Honor_Mitra_BPS_7309_2026.xlsx --tahun=2026 --fresh
 */
class ImportMatriksHonor extends Command
{
    protected $signature = 'honor:import-matriks
        {file : Path file Excel matriks honor}
        {--tahun=2026 : Tahun data}
        {--fresh : Hapus kegiatan hasil impor versi lama (sumber import-matriks)}';

    protected $description = 'Impor master mitra & rekap honor berjalan dari Matrix Honor Mitra (xlsx)';

    private const SHEET_BULAN = [
        'JAN' => 1, 'FEB' => 2, 'MAR' => 3, 'APR' => 4, 'MEI' => 5, 'JUN' => 6,
        'JUL' => 7, 'AGU' => 8, 'SEP' => 9, 'OKT' => 10, 'NOV' => 11, 'DES' => 12,
    ];

    public function handle(): int
    {
        $file = $this->argument('file');
        if (! is_file($file)) {
            $this->error("File tidak ditemukan: {$file}");

            return self::FAILURE;
        }
        $tahun = (int) $this->option('tahun');

        $this->info('Membaca file… (bisa memakan waktu ±1 menit)');
        $reader = IOFactory::createReaderForFile($file);
        $reader->setReadDataOnly(true);
        $book = $reader->load($file);

        // ---- 1. Master mitra --------------------------------------------------------
        $mitraData = [];
        if ($ws = $book->getSheetByName('Petugas')) {
            $max = $ws->getHighestDataRow();
            for ($r = 7; $r <= $max; $r++) {
                $id = $this->sobat($this->nilai($ws, 'B', $r));
                $nama = trim((string) $this->nilai($ws, 'C', $r));
                if ($id === '' || $nama === '') {
                    continue;
                }
                $mitraData[$id] = [
                    'nama' => $nama,
                    'posisi' => $this->teks($this->nilai($ws, 'F', $r)),
                    'email' => $this->teks($this->nilai($ws, 'A', $r)),
                ];
            }
        }

        // ---- 2. Rekap honor per mitra per bulan --------------------------------------
        $rekap = [];   // sobat => [bulan => jumlah]
        $namaDariBulan = [];
        $baris = 0;
        foreach (self::SHEET_BULAN as $sheet => $no) {
            $ws = $book->getSheetByName($sheet);
            if (! $ws) {
                continue;
            }
            $bulan = sprintf('%04d-%02d', $tahun, $no);
            $max = $ws->getHighestDataRow();
            for ($r = 2; $r <= $max; $r++) {
                $id = $this->sobat($this->nilai($ws, 'C', $r));
                if ($id === '') {
                    continue;
                }
                $honor = $this->angka($this->nilai($ws, 'Q', $r));
                $rekap[$id][$bulan] = ($rekap[$id][$bulan] ?? 0) + $honor;
                $namaDariBulan[$id] ??= $this->teks($this->nilai($ws, 'B', $r));
                $baris++;
            }
        }

        // ---- 3. Simpan ---------------------------------------------------------------
        $sumber = 'Impor matriks '.basename($file);
        DB::transaction(function () use ($mitraData, $rekap, $namaDariBulan, $sumber) {
            if ($this->option('fresh')) {
                Kegiatan::where('sumber', 'import-matriks')->delete();
            }
            foreach ($mitraData as $id => $m) {
                Mitra::updateOrCreate(['sobat_id' => $id], $m);
            }
            foreach ($rekap as $id => $perBulan) {
                $mitra = Mitra::firstOrCreate(['sobat_id' => $id], ['nama' => $namaDariBulan[$id] ?? '(nama belum diisi)']);
                foreach ($perBulan as $bulan => $jumlah) {
                    HonorBerjalan::rekam($mitra->id, $bulan, $jumlah, null, $sumber);
                }
            }
        });

        $this->table(['Data', 'Jumlah'], [
            ['Mitra (sheet Petugas)', count($mitraData)],
            ['Baris honor dibaca', $baris],
            ['Mitra dengan honor berjalan', count($rekap)],
            ['Rekap mitra-bulan', array_sum(array_map('count', $rekap))],
        ]);
        $this->info('Impor selesai.');

        return self::SUCCESS;
    }

    /** Ambil nilai sel; untuk sel rumus pakai nilai cache hasil Excel. */
    private function nilai(Worksheet $ws, string $kol, int $baris): mixed
    {
        $cell = $ws->getCell($kol.$baris);
        $v = $cell->getValue();
        if ($cell->getDataType() === DataType::TYPE_FORMULA || (is_string($v) && str_starts_with($v, '='))) {
            $v = $cell->getOldCalculatedValue();
        }
        if (is_string($v) && str_starts_with($v, '#')) {
            return null;
        }

        return $v;
    }

    private function teks(mixed $v): ?string
    {
        $s = trim((string) ($v ?? ''));

        return $s === '' ? null : $s;
    }

    private function sobat(mixed $v): string
    {
        if (is_int($v) || is_float($v)) {
            return $v > 0 ? number_format((float) $v, 0, '', '') : '';
        }
        $s = preg_replace('/\s+/', '', (string) ($v ?? ''));

        return ctype_digit($s) && (int) $s > 0 ? $s : '';
    }

    private function angka(mixed $v): int
    {
        return is_numeric($v) ? max(0, (int) round((float) $v)) : 0;
    }
}
