<?php

namespace App\Services;

use App\Models\SicaturKlasifikasi;
use App\Models\Surat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Memindahkan isi spreadsheet SICATUR lama (Google Sheets › File › Download › Microsoft Excel).
 *
 *  - Rumus Google Sheets (IFNA/IFS, ARRAYFORMULA, IMPORTRANGE) TIDAK dihitung ulang:
 *    yang dipakai nilai terakhir yang tersimpan di file.
 *  - Sheet "Klas Teknis" & "Klas Fasilitatif": kode = kolom A (atau B.C / B.D / B.E), uraian = kolom F, mulai baris 3.
 *  - Sheet tiap jenis: header dicari di 10 baris pertama (baris berisi "No Urut").
 *    Judul kolom boleh berbeda dari form: "Tanggal Surat" / "Pembuat Surat" dipakai bila
 *    "Tanggal FP" / "Pembuat FP" dst. tidak ada.
 *  - Baris "Contoh", No Urut 0, atau tanpa tanggal dilewati.
 * Aman diulang: jenis + kelompok + No Urut yang sama diperbarui, tidak dobel.
 */
class SicaturImporter
{
    public function __construct(private SicaturService $svc) {}

    /** @return array{klasifikasi:int, nomor: array<string,int>, dilewati: array<string,int>, sheetHilang: array, contohLewat: array} */
    public function impor(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $buku = $reader->load($path);
        $hasil = ['klasifikasi' => 0, 'nomor' => [], 'dilewati' => [], 'sheetHilang' => [], 'noWa' => 0];

        DB::transaction(function () use ($buku, &$hasil) {
            foreach (['Teknis' => 'Klas Teknis', 'Fasilitatif' => 'Klas Fasilitatif'] as $sumber => $nama) {
                $ws = $this->sheet($buku, $nama);
                if (! $ws) {
                    $hasil['sheetHilang'][] = $nama;

                    continue;
                }
                $hasil['klasifikasi'] += $this->imporKlasifikasi($ws, $sumber);
            }

            if ($ws = $this->sheet($buku, 'Pegawai')) {
                $hasil['noWa'] = $this->imporNoWa($ws);
            }

            foreach (config('sicatur.jenis') as $jenis => $c) {
                if (empty($c['sheet'])) {
                    continue; // jenis baru, tidak ada di spreadsheet lama
                }
                $ws = $this->sheet($buku, $c['sheet']);
                if (! $ws) {
                    $hasil['sheetHilang'][] = $c['sheet'];

                    continue;
                }
                [$n, $lewat] = $this->imporJenis($ws, $jenis, $c);
                $hasil['nomor'][$c['label']] = $n;
                $hasil['dilewati'][$c['label']] = $lewat;
            }
        });

        return $hasil;
    }

    private function sheet($buku, string $nama): ?Worksheet
    {
        $cari = $this->norm($nama);
        foreach ($buku->getWorksheetIterator() as $ws) {
            if ($this->norm($ws->getTitle()) === $cari) {
                return $ws;
            }
        }

        return null;
    }

    private function norm(mixed $v): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower((string) $v));
    }

    /** Nilai sel: untuk rumus, ambil nilai tersimpan (tidak dihitung ulang). */
    private function nilai(Worksheet $ws, int $kol, int $baris): mixed
    {
        if (! $ws->cellExists([$kol, $baris])) {
            return null;
        }
        $c = $ws->getCell([$kol, $baris]);
        $v = $c->isFormula() ? $c->getOldCalculatedValue() : $c->getValue();
        if ($v instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
            $v = $v->getPlainText();
        }
        if (is_string($v) && str_starts_with($v, '#')) {
            return null; // #N/A, #REF!, dst.
        }

        return $v;
    }

    /** Teks sel; angka utuh ditulis tanpa ".0" */
    private function teks(mixed $v): string
    {
        if (is_float($v) && floor($v) == $v) {
            return (string) (int) $v;
        }

        return trim((string) ($v ?? ''));
    }

    private function barisTerakhir(Worksheet $ws): int
    {
        return (int) $ws->getHighestDataRow();
    }

    // ---------------------------------------------------------------------

    public function imporKlasifikasi(Worksheet $ws, string $sumber): int
    {
        $n = 0;
        $akhir = $this->barisTerakhir($ws);
        for ($r = 3; $r <= $akhir; $r++) {
            $kode = $this->teks($this->nilai($ws, 1, $r));
            if ($kode === '' || strtoupper($kode) === 'CEK') {
                // Hitung seperti rumus kolom A: B.C | B.D | B.E
                $b = $this->teks($this->nilai($ws, 2, $r));
                foreach ([3, 4, 5] as $k) {
                    $sub = $this->nilai($ws, $k, $r);
                    if ($sub !== null && $sub !== '') {
                        $sub = is_numeric($sub) ? str_pad((string) (int) $sub, 3, '0', STR_PAD_LEFT) : trim((string) $sub);
                        $kode = $b !== '' ? $b.'.'.$sub : '';
                        break;
                    }
                }
            }
            if ($kode === '' || strtoupper($kode) === 'CEK') {
                continue;
            }
            $uraian = trim(preg_replace('/\s+/', ' ', $this->teks($this->nilai($ws, 6, $r))));
            SicaturKlasifikasi::updateOrCreate(['sumber' => $sumber, 'kode' => $kode], ['uraian' => $uraian ?: null, 'urutan' => $r]);
            $n++;
        }

        return $n;
    }

    /**
     * Sheet Pegawai (Nama | NoWA): isi nomor HP pegawai PANTAU yang namanya sama dan HP-nya masih kosong.
     * Dipakai untuk nomor WA pemohon/pengunggah di webhook SK.
     */
    private function imporNoWa(Worksheet $ws): int
    {
        $n = 0;
        $akhir = $this->barisTerakhir($ws);
        for ($r = 2; $r <= $akhir; $r++) {
            $nama = mb_strtolower(trim(preg_replace('/\s+/', ' ', $this->teks($this->nilai($ws, 1, $r)))));
            $wa = $this->nilai($ws, 2, $r);
            $wa = is_numeric($wa) ? number_format((float) $wa, 0, '', '') : preg_replace('/\D/', '', (string) $wa);
            if ($nama === '' || $wa === '') {
                continue;
            }
            $n += \App\Models\User::query()->whereRaw('LOWER(TRIM(name)) = ?', [$nama])
                ->where(fn ($q) => $q->whereNull('phone')->orWhere('phone', ''))
                ->update(['phone' => $wa]);
        }

        return $n;
    }

    private function imporJenis(Worksheet $ws, string $jenis, array $c): array
    {
        // Cari baris header
        $kol = null;
        $barisHeader = 0;
        $kolomMaks = Coordinate::columnIndexFromString($ws->getHighestDataColumn());
        for ($r = 1; $r <= 10; $r++) {
            $peta = [];
            for ($k = 1; $k <= $kolomMaks; $k++) {
                $n = $this->norm($this->nilai($ws, $k, $r));
                if ($n !== '' && ! isset($peta[$n])) {
                    $peta[$n] = $k;
                }
            }
            if (isset($peta['nourut'])) {
                [$kol, $barisHeader] = [$peta, $r];
                break;
            }
        }
        if (! $kol) {
            return [0, 0];
        }

        // Kolom per isian: nama di form, lalu nama alternatif menurut tipe
        $alternatif = ['tanggal' => ['Tanggal Surat'], 'pembuat' => ['Pembuat Surat', 'Pembuat'], 'deskripsi' => [], 'pilihan' => [], 'tautan' => [], 'klasifikasi' => ['Klasifikasi']];
        $kolIsian = [];
        foreach ($c['form'] as $f) {
            $calon = array_merge([$f['kolom']], $alternatif[$f['tipe']] ?? []);
            foreach ($calon as $nama) {
                if (isset($kol[$this->norm($nama)])) {
                    $kolIsian[$f['attr']] = $kol[$this->norm($nama)];
                    break;
                }
            }
        }
        $kolDari = fn (string $nama) => $kol[$this->norm($nama)] ?? null;
        $kolTglSurat = $kolDari('Tanggal Surat');

        $n = 0;
        $lewat = 0;
        $akhir = $this->barisTerakhir($ws);
        for ($r = $barisHeader + 1; $r <= $akhir; $r++) {
            $no = $this->teks($this->nilai($ws, $kol['nourut'], $r));
            if ($no === '') {
                continue;
            }
            if (! preg_match('/^0*(\d+)(?:[.,](\d+))?$/', $no, $m) || (int) $m[1] === 0) {
                $lewat++; // "Contoh", 0, atau teks lain

                continue;
            }
            $nomor = (int) $m[1];
            $sisip = isset($m[2]) ? (int) $m[2] : 0;

            $d = [];
            foreach ($c['form'] as $f) {
                $k = $kolIsian[$f['attr']] ?? null;
                $v = $k ? $this->nilai($ws, $k, $r) : null;
                $d[$f['attr']] = match ($f['tipe']) {
                    'tanggal' => $this->tanggal($v),
                    'tautan' => $this->tautan($ws, $k, $r, $this->teks($v)),
                    default => trim(preg_replace('/\s+/', ' ', $this->teks($v))),
                };
            }

            // Tanggal yang ditampilkan: kolom tanggal utama (FP: Tanggal FP).
            // Kelompok nomor FP lama mengikuti Tanggal Surat (tanggal terbit) di spreadsheet lama.
            $tglSurat = $kolTglSurat ? $this->tanggal($this->nilai($ws, $kolTglSurat, $r)) : null;
            $tanggal = ($c['tanggal'] ? ($d[$c['tanggal']] ?? null) : null) ?? $tglSurat;
            $tglGrup = $jenis === 'fp' ? ($tglSurat ?? $tanggal) : $tanggal;
            if (! $tanggal) {
                $lewat++;

                continue;
            }

            $format = $this->teks($kolDari('Format Nomor') ? $this->nilai($ws, $kolDari('Format Nomor'), $r) : '');
            $lengkap = $format !== '' && ! preg_match('/^cek/i', $format);
            $status = $kolDari('Status') ? $this->teks($this->nilai($ws, $kolDari('Status'), $r)) : '';
            $link = $kolDari('Link Surat') ? $this->tautan($ws, $kolDari('Link Surat'), $r, $this->teks($this->nilai($ws, $kolDari('Link Surat'), $r))) : '';
            $sifat = $kolDari('Sifat') ? $this->teks($this->nilai($ws, $kolDari('Sifat'), $r)) : '';

            $isi = [];
            foreach ($c['form'] as $f) {
                $v = $d[$f['attr']] ?? null;
                $isi[$f['attr']] = ($v === '' || $v === null) ? null : (is_string($v) ? mb_substr($v, 0, $f['tipe'] === 'tautan' ? 1000 : 2000) : $v);
            }
            $isi['pembuat'] = ($isi['pembuat'] ?? null) ?: 'Tidak tercatat';
            $isi['perihal'] = ($isi['perihal'] ?? null) ?: '-';
            if ($jenis === 'fp' && $kolDari('Jenis FP')) {
                $isi['jenis_fp'] = $this->teks($this->nilai($ws, $kolDari('Jenis FP'), $r)) ?: null; // data lama
            }

            $grup = $this->svc->grup($jenis, $tglGrup, $isi['tim_teknis'] ?? null);
            Surat::withTrashed()->updateOrCreate(
                ['jenis' => $jenis, 'grup' => $grup, 'nomor_urut' => $nomor, 'sisip' => $sisip],
                $isi + [
                    'tanggal' => $tanggal,
                    'tahun' => (int) substr($tanggal, 0, 4),
                    'no_urut' => $no,
                    'nomor_surat' => $lengkap ? $format : '',
                    'lengkap' => $lengkap,
                    'sifat' => $jenis === 'st' ? ($sifat ?: 'Biasa') : null,
                    'link_surat' => $link !== '' ? mb_substr($link, 0, 1000) : null,
                    'status' => $status !== '' ? $status : null,
                    'keterangan' => 'Diimpor dari SICATUR lama',
                    'deleted_at' => null,
                ]
            );
            $n++;
        }

        return [$n, $lewat];
    }

    /** Teks tautan; bila sel berisi teks biasa dengan hyperlink, ambil URL-nya. */
    private function tautan(Worksheet $ws, ?int $kol, int $r, string $teks): string
    {
        if ($kol === null || preg_match('/^https?:\/\//i', $teks)) {
            return $teks;
        }
        if ($ws->cellExists([$kol, $r])) {
            $url = $ws->getCell([$kol, $r])->getHyperlink()->getUrl();
            if ($url) {
                return $url;
            }
        }

        return $teks;
    }

    private function tanggal(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        try {
            if ($v instanceof \DateTimeInterface) {
                return Carbon::instance($v)->toDateString();
            }
            if (is_numeric($v)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $v))->toDateString();
            }
            $s = trim((string) $v);
            if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $s, $m)) {
                return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
            }

            return Carbon::parse($s)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
