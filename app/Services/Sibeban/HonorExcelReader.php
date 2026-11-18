<?php

namespace App\Services\Sibeban;

use App\Models\Sibeban\Kegiatan;
use App\Models\Sibeban\Mitra;
use App\Support\Sibeban\Periode;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Membaca template honor petugas sebuah kegiatan.
 *
 * Kolom (baris judul boleh di 10 baris teratas, urutan bebas):
 *   Sobat ID | Nama | Kecamatan | Desa/Kelurahan | Jabatan | Honor <Bulan Tahun> (satu kolom per bulan kegiatan)
 *
 * - Honor diisi angka rupiah penuh (mis. 1500000). Kosong = 0.
 * - Satu kolom "Honor" saja (tanpa bulan) → dipakai untuk setiap bulan periode kegiatan.
 * - Baris yang jabatan & seluruh honornya kosong dilewati (petugas tetap terpilih, belum diisi).
 */
class HonorExcelReader
{
    private const ALIAS = [
        'sobat_id' => ['sobatid', 'idsobat', 'sobat', 'idmitra', 'kodemitra', 'id'],
        'nama' => ['nama', 'namalengkap', 'namamitra', 'namapetugas'],
        'jabatan' => ['jabatan', 'peran', 'posisidikegiatan', 'tugas'],
        'honor' => ['honor', 'jumlahhonor', 'honorperbulan', 'nominal', 'jumlah'],
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
     *   rows[i] = [baris, mitra_id, sobat_id, nama, jabatan, honor[bulan=>int], terpilih(bool)]
     */
    public function baca(string $path, Kegiatan $kegiatan): array
    {
        $bulans = $kegiatan->bulans();
        $errors = [];
        $warnings = [];

        $book = IOFactory::load($path);
        $data = null;
        $kol = null;
        $header = 0;
        foreach ($book->getWorksheetIterator() as $ws) {
            $isi = $ws->toArray(null, true, false, false);
            foreach (array_slice($isi, 0, 10, true) as $i => $baris) {
                $k = $this->petakan($baris, $bulans);
                if (isset($k['sobat_id'])) {
                    [$data, $kol, $header] = [$isi, $k, $i];
                    break 2;
                }
            }
        }
        if ($data === null) {
            return ['rows' => [], 'errors' => ['Kolom "Sobat ID" tidak ditemukan. Gunakan template yang diunduh dari halaman kegiatan ini.'], 'warnings' => []];
        }
        if (empty($kol['bulan']) && ! isset($kol['honor'])) {
            return ['rows' => [], 'errors' => ['Kolom honor tidak ditemukan (mis. "Honor '.Periode::label($bulans[0] ?? date('Y-m')).'").'], 'warnings' => []];
        }
        if (! empty($kol['bulan']) && count($kol['bulan']) < count($bulans)) {
            $kurang = array_diff($bulans, array_keys($kol['bulan']));
            $warnings[] = 'Kolom honor '.implode(', ', array_map(fn ($b) => Periode::label($b), $kurang)).' tidak ada di file — dianggap 0.';
        }

        // Kumpulkan baris
        $mentah = [];
        $terlihat = [];
        foreach (array_slice($data, $header + 1, null, true) as $i => $b) {
            $no = $i + 1;
            $sobat = $this->sobat($b[$kol['sobat_id']] ?? null);
            if ($sobat === '') {
                continue;
            }
            if (isset($terlihat[$sobat])) {
                $warnings[] = "Baris {$no}: Sobat ID {$sobat} ganda (sudah ada di baris {$terlihat[$sobat]}), dilewati.";

                continue;
            }
            $terlihat[$sobat] = $no;

            $honor = [];
            foreach ($bulans as $bl) {
                if (! empty($kol['bulan'])) {
                    $honor[$bl] = isset($kol['bulan'][$bl]) ? ($this->angka($b[$kol['bulan'][$bl]] ?? null) ?? 0) : 0;
                } else {
                    $honor[$bl] = $this->angka($b[$kol['honor']] ?? null) ?? 0;
                }
            }
            $jabatan = isset($kol['jabatan']) ? trim((string) ($b[$kol['jabatan']] ?? '')) : '';
            if ($jabatan === '' && array_sum($honor) === 0) {
                continue; // belum diisi
            }
            $mentah[] = [
                'baris' => $no,
                'sobat_id' => $sobat,
                'nama' => isset($kol['nama']) ? trim((string) ($b[$kol['nama']] ?? '')) : '',
                'jabatan' => mb_substr($jabatan, 0, 60),
                'honor' => $honor,
            ];
        }

        // Cocokkan dengan master mitra
        $mitra = Mitra::whereIn('sobat_id', array_column($mentah, 'sobat_id'))->get(['id', 'sobat_id', 'nama', 'kecamatan', 'desa'])->keyBy('sobat_id');
        $terpilih = $kegiatan->penugasans()->pluck('mitra_id')->flip();
        $rows = [];
        foreach ($mentah as $r) {
            $m = $mitra[$r['sobat_id']] ?? null;
            if (! $m) {
                $errors[] = "Baris {$r['baris']}: Sobat ID {$r['sobat_id']}".($r['nama'] ? " ({$r['nama']})" : '').' belum terdaftar di database mitra — dilewati. Minta admin menambahkannya.';

                continue;
            }
            if ($r['jabatan'] === '') {
                $warnings[] = "Baris {$r['baris']}: jabatan {$m->nama} kosong.";
            }
            $rows[] = $r + [
                'mitra_id' => $m->id,
                'nama_db' => $m->nama,
                'wilayah' => $m->wilayah(),
                'terpilih' => isset($terpilih[$m->id]),
            ];
        }
        if (empty($rows) && empty($errors)) {
            $errors[] = 'Belum ada baris yang terisi jabatan atau honor.';
        }

        return ['rows' => $rows, 'errors' => $errors, 'warnings' => array_values(array_unique($warnings))];
    }

    private function petakan(array $baris, array $bulans): array
    {
        $hasil = ['bulan' => []];
        foreach ($baris as $idx => $judul) {
            if ($judul === null || $judul === '') {
                continue;
            }
            $bulan = $this->kenaliBulan($judul, $bulans);
            if ($bulan !== null) {
                if (in_array($bulan, $bulans, true)) {
                    $hasil['bulan'][$bulan] = $idx;
                }

                continue;
            }
            $n = preg_replace('/[^a-z0-9]/', '', strtolower((string) $judul));
            foreach (self::ALIAS as $f => $alias) {
                if (! isset($hasil[$f]) && in_array($n, $alias, true)) {
                    $hasil[$f] = $idx;
                    break;
                }
            }
        }

        return $hasil;
    }

    /** "Honor Nov 2026", "Nov 2026", "2026-11", tanggal Excel → 'YYYY-MM' */
    private function kenaliBulan(mixed $judul, array $bulans): ?string
    {
        if (is_numeric($judul) && $judul > 20000 && $judul < 80000) {
            return ExcelDate::excelToDateTimeObject((float) $judul)->format('Y-m');
        }
        if ($judul instanceof \DateTimeInterface) {
            return $judul->format('Y-m');
        }
        $t = strtolower(trim((string) $judul));
        $t = trim(preg_replace('/^(honor|gaji|jumlah honor|honor bulan|bulan)\s*/', '', $t));
        $t = trim(preg_replace('/\(.*?\)/', '', $t));

        if (preg_match('/^(\d{4})[-\/](\d{1,2})$/', $t, $m)) {
            return sprintf('%04d-%02d', $m[1], $m[2]);
        }
        if (preg_match('/^(\d{1,2})[-\/](\d{4})$/', $t, $m)) {
            return sprintf('%04d-%02d', $m[2], $m[1]);
        }
        if (preg_match('/^([a-z]+)\.?[\s\-_]*\'?(\d{2}|\d{4})?$/', $t, $m) && isset(self::BULAN[$m[1]])) {
            $no = self::BULAN[$m[1]];
            if (! empty($m[2])) {
                return sprintf('%04d-%02d', strlen($m[2]) === 2 ? 2000 + (int) $m[2] : (int) $m[2], $no);
            }
            foreach ($bulans as $b) {
                if ((int) substr($b, 5, 2) === $no) {
                    return $b;
                }
            }
        }

        return null;
    }

    public static function sobat(mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        if (is_float($v) || is_int($v)) {
            return $v > 0 ? number_format((float) $v, 0, '', '') : '';
        }
        $s = ltrim(preg_replace('/\s+/', '', (string) $v), "'");

        return in_array($s, ['0', '#N/A', '-'], true) ? '' : $s;
    }

    private function angka(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_int($v) || is_float($v)) {
            return max(0, (int) round($v));
        }
        $s = trim((string) $v);
        // "1.500.000" / "Rp 1.500.000,00" → 1500000
        $s = preg_replace('/,\d{1,2}$/', '', $s);
        $digit = preg_replace('/[^0-9]/', '', $s);

        return $digit === '' ? null : (int) $digit;
    }
}
