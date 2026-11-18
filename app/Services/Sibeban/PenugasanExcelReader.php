<?php

namespace App\Services\Sibeban;

use App\Models\Sibeban\Kegiatan;
use App\Support\Sibeban\Periode;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Membaca file Excel petugas yang akan ditugaskan pada sebuah kegiatan.
 *
 * Isi file = daftar calon petugas + HONOR BERJALAN mereka (honor yang sedang diterima
 * dari kegiatan lain), bukan honor kegiatan baru. Honor kegiatan baru diambil dari
 * isian honor PPL/PML pada form kegiatan.
 *
 * Format (baris pertama = judul kolom, urutan bebas):
 *   sobat_id | nama (opsional) | jabatan (ppl/pml) | Jan 2026 | Feb 2026 | ... | Des 2026
 *
 * - Hanya kolom bulan yang masuk periode kegiatan yang diambil; sisanya diabaikan.
 * - Sel bulan kosong = honor berjalan 0.
 * - Alternatif: satu kolom "jumlah" → dianggap honor berjalan yang sama untuk setiap bulan periode.
 */
class PenugasanExcelReader
{
    private const ALIAS = [
        'sobat_id' => ['sobatid', 'idsobat', 'sobat', 'id', 'idmitra', 'kodemitra'],
        'nama' => ['nama', 'namalengkap', 'namamitra', 'namapetugas'],
        'jabatan' => ['jabatan', 'peran', 'pplpml', 'tugas'],
        'jumlah' => ['jumlah', 'gaji', 'honor', 'jumlahgaji', 'jumlahhonor', 'nominal', 'honorperbulan', 'gajiperbulan', 'honorberjalan', 'totalhonor', 'bayar'],
    ];

    private const BULAN = [
        'jan' => 1, 'januari' => 1, 'feb' => 2, 'februari' => 2, 'peb' => 2, 'mar' => 3, 'maret' => 3,
        'apr' => 4, 'april' => 4, 'mei' => 5, 'may' => 5, 'jun' => 6, 'juni' => 6, 'jul' => 7, 'juli' => 7,
        'agu' => 8, 'agt' => 8, 'agus' => 8, 'agustus' => 8, 'aug' => 8, 'sep' => 9, 'sept' => 9, 'september' => 9,
        'okt' => 10, 'oktober' => 10, 'oct' => 10, 'nov' => 11, 'nop' => 11, 'november' => 11,
        'des' => 12, 'desember' => 12, 'dec' => 12,
    ];

    /**
     * @return array{rows: array, errors: array, warnings: array}
     *   rows[i] = [baris, sobat_id, nama, jabatan, berjalan => [bulan => jumlah]]
     */
    public function baca(string $path, Kegiatan $kegiatan): array
    {
        $bulans = $kegiatan->bulans();
        $errors = [];
        $warnings = [];
        $rows = [];

        $spreadsheet = IOFactory::load($path);

        // Cari sheet pertama yang punya kolom sobat_id di 10 baris teratas
        $data = null;
        $kolom = null;
        $barisHeader = 0;
        foreach ($spreadsheet->getWorksheetIterator() as $ws) {
            $isi = $ws->toArray(null, true, false, false);
            foreach (array_slice($isi, 0, 10, true) as $i => $baris) {
                $k = $this->petakanKolom($baris, $bulans, $warnings);
                if (isset($k['sobat_id'])) {
                    $data = $isi;
                    $kolom = $k;
                    $barisHeader = $i;
                    break 2;
                }
            }
        }

        if ($data === null) {
            return ['rows' => [], 'errors' => ['Kolom "sobat_id" tidak ditemukan. Pastikan baris judul memuat sobat_id, jabatan, dan jumlah/kolom bulan.'], 'warnings' => []];
        }
        if (! isset($kolom['jabatan'])) {
            $errors[] = 'Kolom "jabatan" (ppl/pml) tidak ditemukan.';
        }

        $adaBulan = ! empty($kolom['bulan']);
        $adaJumlah = isset($kolom['jumlah']);
        if (! $adaBulan && ! $adaJumlah) {
            $warnings[] = 'Tidak ada kolom bulan maupun kolom jumlah — honor berjalan semua petugas dianggap 0.';
        } elseif ($adaBulan && count($kolom['bulan']) < count($bulans)) {
            $kurang = array_diff($bulans, array_keys($kolom['bulan']));
            $warnings[] = 'Kolom bulan '.implode(', ', array_map(fn ($b) => Periode::label($b), $kurang)).' tidak ada di file — honor berjalan bulan tersebut dianggap 0.';
        }

        $terlihat = [];
        foreach (array_slice($data, $barisHeader + 1, null, true) as $i => $baris) {
            $nomor = $i + 1;
            $sobat = $this->normalSobat($baris[$kolom['sobat_id']] ?? null);
            if ($sobat === '') {
                continue; // baris kosong
            }
            if (isset($terlihat[$sobat])) {
                $warnings[] = "Baris {$nomor}: sobat_id {$sobat} duplikat (sudah ada di baris {$terlihat[$sobat]}), dilewati.";

                continue;
            }

            $jabatan = isset($kolom['jabatan']) ? $this->normalJabatan($baris[$kolom['jabatan']] ?? null) : null;
            if (! $jabatan) {
                $errors[] = "Baris {$nomor}: jabatan untuk {$sobat} tidak dikenali (isi ppl atau pml).";

                continue;
            }

            $berjalan = [];
            if ($adaBulan) {
                foreach ($bulans as $b) {
                    $berjalan[$b] = isset($kolom['bulan'][$b])
                        ? ($this->normalAngka($baris[$kolom['bulan'][$b]] ?? null) ?? 0)
                        : 0;
                }
            } else {
                $jml = $adaJumlah ? ($this->normalAngka($baris[$kolom['jumlah']] ?? null) ?? 0) : 0;
                $berjalan = array_fill_keys($bulans, $jml);
            }

            $terlihat[$sobat] = $nomor;
            $rows[] = [
                'baris' => $nomor,
                'sobat_id' => $sobat,
                'nama' => isset($kolom['nama']) ? trim((string) ($baris[$kolom['nama']] ?? '')) : '',
                'jabatan' => $jabatan,
                'berjalan' => $berjalan,
            ];
        }

        if (empty($rows) && empty($errors)) {
            $errors[] = 'Tidak ada baris petugas yang terbaca.';
        }

        return ['rows' => $rows, 'errors' => $errors, 'warnings' => array_values(array_unique($warnings))];
    }

    /** @return array{sobat_id?: int|string, nama?: int|string, jabatan?: int|string, jumlah?: int|string, bulan: array<string, int|string>} */
    private function petakanKolom(array $baris, array $bulans, array &$warnings): array
    {
        $hasil = ['bulan' => []];
        $diLuar = [];
        foreach ($baris as $idx => $judul) {
            if ($judul === null || $judul === '') {
                continue;
            }
            $bulan = $this->kenaliBulan($judul, $bulans);
            if ($bulan !== null) {
                if (in_array($bulan, $bulans, true)) {
                    $hasil['bulan'][$bulan] = $idx;
                } else {
                    $diLuar[] = is_string($judul) ? $judul : $bulan;
                }

                continue;
            }
            $n = $this->normalTeks($judul);
            foreach (self::ALIAS as $field => $alias) {
                if (! isset($hasil[$field]) && in_array($n, $alias, true)) {
                    $hasil[$field] = $idx;
                    break;
                }
            }
        }
        if (isset($hasil['sobat_id']) && $diLuar) {
            $warnings[] = 'Kolom bulan di luar periode kegiatan diabaikan: '.implode(', ', $diLuar).'.';
        }

        return $hasil;
    }

    /** Mengenali judul kolom bulan → 'YYYY-MM', atau null. */
    private function kenaliBulan(mixed $judul, array $bulans): ?string
    {
        // Judul berupa tanggal Excel (serial)
        if (is_numeric($judul) && $judul > 20000 && $judul < 80000) {
            return ExcelDate::excelToDateTimeObject((float) $judul)->format('Y-m');
        }
        if ($judul instanceof \DateTimeInterface) {
            return $judul->format('Y-m');
        }
        $t = strtolower(trim((string) $judul));

        if (preg_match('/^(\d{4})[-\/](\d{1,2})$/', $t, $m)) {
            return sprintf('%04d-%02d', $m[1], $m[2]);
        }
        if (preg_match('/^(\d{1,2})[-\/](\d{4})$/', $t, $m)) {
            return sprintf('%04d-%02d', $m[2], $m[1]);
        }
        if (preg_match('/^([a-z]+)\.?[\s\-_]*\'?(\d{2}|\d{4})?$/', $t, $m) && isset(self::BULAN[$m[1]])) {
            $no = self::BULAN[$m[1]];
            if (! empty($m[2])) {
                $th = strlen($m[2]) === 2 ? 2000 + (int) $m[2] : (int) $m[2];

                return sprintf('%04d-%02d', $th, $no);
            }
            // Tanpa tahun: cocokkan dengan bulan di periode kegiatan
            foreach ($bulans as $b) {
                if ((int) substr($b, 5, 2) === $no) {
                    return $b;
                }
            }

            return sprintf('%04d-%02d', (int) substr($bulans[0] ?? date('Y'), 0, 4), $no);
        }

        return null;
    }

    private function normalTeks(mixed $v): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower((string) $v));
    }

    private function normalSobat(mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        if (is_float($v) || is_int($v)) {
            return $v > 0 ? number_format((float) $v, 0, '', '') : '';
        }
        $s = preg_replace('/\s+/', '', (string) $v);
        // buang awalan apostrof yang kadang dipakai agar Excel memperlakukan sebagai teks
        $s = ltrim($s, "'");

        return in_array($s, ['0', '#N/A', '-'], true) ? '' : $s;
    }

    private function normalJabatan(mixed $v): ?string
    {
        $t = strtolower(trim((string) $v));
        if ($t === '') {
            return null;
        }
        if (str_contains($t, 'pml') || str_contains($t, 'pemeriksa') || str_contains($t, 'pengawas')) {
            return 'pml';
        }
        if (str_contains($t, 'ppl') || str_contains($t, 'pencacah') || str_contains($t, 'pendata') || str_contains($t, 'petugas')) {
            return 'ppl';
        }

        return null;
    }

    private function normalAngka(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_int($v) || is_float($v)) {
            return max(0, (int) round($v));
        }
        $digit = preg_replace('/[^0-9]/', '', (string) $v);

        return $digit === '' ? null : (int) $digit;
    }

    /** Kolom template sesuai periode kegiatan. */
    public static function judulTemplate(Kegiatan $kegiatan): array
    {
        return array_merge(['sobat_id', 'nama', 'jabatan'], array_map(fn ($b) => Periode::label($b), $kegiatan->bulans()));
    }
}
