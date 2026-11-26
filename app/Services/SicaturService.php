<?php

namespace App\Services;

use App\Models\SicaturKlasifikasi;
use App\Models\Surat;
use App\Models\SuratLog;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Logika penomoran SICATUR, mengikuti aplikasi Apps Script lama (Config.gs, Nomor.gs, Web.gs):
 *  - Nomor urut per kelompok: tahun tanggal surat; Form Permintaan: bulan Tanggal FP + tim teknis.
 *  - Tanggal tidak boleh setelah hari ini. Tanggal sebelum hari ini wajib dicentang "Tanggal mundur";
 *    bila sudah ada nomor yang lebih baru, nomor menjadi sisipan (mis. 010.1). Berlaku untuk semua jenis.
 *  - Nomor yang dihapus tidak dipakai ulang, sehingga nomor lain tidak bergeser.
 *  - Setiap penghapusan & link surat tercatat di log.
 */
class SicaturService
{
    public const POLA_TAUTAN = '/^https?:\/\/[^\s\/.]+(\.[^\s\/.]+)+(\/\S*)?$/i';

    public function hariIni(): string
    {
        return now()->toDateString();
    }

    public function cfg(string $jenis): array
    {
        $c = Surat::cfg($jenis);
        abort_unless($c, 404, 'Jenis tidak dikenal.');

        return $c;
    }

    public function satker(): string
    {
        return (string) config('sicatur.kode_satker');
    }

    public function adaKlasifikasi(): bool
    {
        return SicaturKlasifikasi::query()->exists();
    }

    /** Daftar nama untuk pilihan "Pembuat" (pegawai aktif). */
    public function pembuat(): array
    {
        return User::query()->where('is_active', true)->where('is_pegawai', true)
            ->orderBy('name')->get(['name', 'username'])
            ->map(fn ($u) => trim((string) ($u->name ?: $u->username)))
            ->filter()->unique()->values()->all();
    }

    // =====================================================================
    // Nomor
    // =====================================================================

    public function noUrutTeks(array $cfg, int $nomor, int $sisip): string
    {
        $t = $cfg['digit'] > 0 ? str_pad((string) $nomor, $cfg['digit'], '0', STR_PAD_LEFT) : (string) $nomor;

        return $sisip > 0 ? $t.'.'.$sisip : $t;
    }

    /** Susun Format Nomor (bangunFormatNomor_). null bila data kurang. */
    public function formatNomor(string $jenis, string $noUrut, array $d): ?string
    {
        $tanggal = ! empty($d['tanggal']) ? Carbon::parse($d['tanggal']) : null;
        if ($noUrut === '' || ! $tanggal) {
            return null;
        }
        $tahun = $tanggal->format('Y');
        $satker = $this->satker();

        switch ($jenis) {
            case 'bast':
            case 'bapp':
            case 'ba':
            case 'sp':
                if (empty($d['klasifikasi'])) {
                    return null;
                }
                $awalan = $this->cfg($jenis)['awalan'] ?? strtoupper($jenis);

                return $awalan.'-'.$noUrut.'/'.$satker.'/'.$d['klasifikasi'].'/'.$tahun;
            case 'st':
                $awalan = config('sicatur.sifat')[$d['sifat'] ?? 'Biasa'] ?? null;
                if (! $awalan || empty($d['klasifikasi'])) {
                    return null;
                }

                return $awalan.$noUrut.'/'.$satker.'/'.$d['klasifikasi'].'/'.$tahun;
            case 'sk':
                return 'NOMOR '.$noUrut.' TAHUN '.$tahun;
            case 'fp':
                // Tanpa Jenis FP: No Urut/Satker/Tim Teknis/Bulan FP/Tahun FP
                $tim = config('sicatur.tim_teknis')[$d['tim_teknis'] ?? ''] ?? null;
                if (! $tim) {
                    return null;
                }

                return $noUrut.'/'.$satker.'/'.$tim.'/'.$tanggal->format('m').'/'.$tahun;
        }

        return null;
    }

    /** Kelompok penomoran: '2026' atau (FP) '2026-01-04'. */
    public function grup(string $jenis, string $tanggal, ?string $timTeknis = null): string
    {
        $c = $this->cfg($jenis);
        $t = Carbon::parse($tanggal);
        if ($c['urutan'] === 'bulan_tim') {
            return $t->format('Y-m').'-'.(config('sicatur.tim_teknis')[$timTeknis] ?? 'xx');
        }

        return $t->format('Y');
    }

    // =====================================================================
    // Validasi isian
    // =====================================================================

    /**
     * @param  array  $isian  [attr => nilai]
     * @param  array|null  $hanya  attr yang diperiksa (null = semua)
     */
    public function periksa(string $jenis, array $isian, ?array $hanya = null): void
    {
        $c = $this->cfg($jenis);
        $galat = [];
        foreach ($c['form'] as $f) {
            $a = $f['attr'];
            if ($hanya !== null && ! in_array($a, $hanya, true)) {
                continue;
            }
            $v = trim((string) ($isian[$a] ?? ''));
            $kunci = 'isian.'.$jenis.'.'.$a;
            if ($v === '') {
                $galat[$kunci] = $f['kolom'].' wajib diisi.';

                continue;
            }
            switch ($f['tipe']) {
                case 'klasifikasi':
                    if ($this->adaKlasifikasi() && ! SicaturKlasifikasi::where('kode', $v)->exists()) {
                        $galat[$kunci] = $f['kolom'].': pilih dari daftar klasifikasi.';
                    }
                    break;
                case 'pilihan':
                    if (! in_array($v, $f['opsi'], true)) {
                        $galat[$kunci] = $f['kolom'].' tidak dikenal.';
                    }
                    break;
                case 'tautan':
                    if (mb_strlen($v) > 500 || ! preg_match(self::POLA_TAUTAN, $v)) {
                        $galat[$kunci] = 'Harus berupa tautan yang diawali http:// atau https:// tanpa spasi.';
                    }
                    break;
                case 'tanggal':
                    if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
                        $galat[$kunci] = $f['kolom'].' tidak valid.';
                    }
                    break;
                case 'deskripsi':
                    if (mb_strlen($v) > 300) {
                        $galat[$kunci] = $f['kolom'].' maksimal 300 karakter.';
                    }
                    break;
            }
        }
        if ($galat) {
            throw ValidationException::withMessages($galat);
        }
    }

    private function bersih(array $c, array $isian): array
    {
        $d = [];
        foreach ($c['form'] as $f) {
            $v = $isian[$f['attr']] ?? '';
            $d[$f['attr']] = $f['tipe'] === 'deskripsi' ? trim(preg_replace('/\s+/', ' ', (string) $v)) : trim((string) $v);
        }

        return $d;
    }

    // =====================================================================
    // Terbitkan
    // =====================================================================

    /**
     * @return array{surat: Surat, sisipan: bool, induk: ?string, urutanTerjaga: bool}
     */
    public function terbitkan(string $jenis, array $isian, bool $mundur, User $user): array
    {
        $c = $this->cfg($jenis);
        $this->periksa($jenis, $isian);
        $d = $this->bersih($c, $isian);
        $hariIni = $this->hariIni();

        // Tanggal utama (dasar nomor & sisipan). FP: Tanggal FP.
        $tanggal = $c['tanggal'] ? $d[$c['tanggal']] : $hariIni;
        $kunciTgl = 'isian.'.$jenis.'.'.($c['tanggal'] ?? 'tanggal');
        if ($tanggal > $hariIni) {
            throw ValidationException::withMessages([$kunciTgl => 'Tanggal tidak boleh setelah hari ini.']);
        }
        if ($tanggal < $hariIni && ! $mundur) {
            throw ValidationException::withMessages([$kunciTgl => 'Tanggal sebelum hari ini: centang “Tanggal mundur”.']);
        }

        return Cache::lock('sicatur-terbit-'.$jenis, 20)->block(15, function () use ($jenis, $c, $d, $tanggal, $mundur, $user) {
            return DB::transaction(function () use ($jenis, $c, $d, $tanggal, $mundur, $user) {
                ['grup' => $grup, 'nomor' => $nomor, 'sisip' => $sisip, 'sisipan' => $sisipan, 'terjaga' => $terjaga] = $this->hitungNomor($jenis, $d, $tanggal, $mundur);

                $noUrut = $this->noUrutTeks($c, $nomor, $sisip);
                $data = $d + ['tanggal' => $tanggal, 'sifat' => 'Biasa'];
                $format = $this->formatNomor($jenis, $noUrut, $data);
                abort_if($format === null, 422, 'Format nomor tidak bisa disusun. Periksa isian.');

                $isi = [];
                foreach ($c['form'] as $f) {
                    $isi[$f['attr']] = $d[$f['attr']] !== '' ? $d[$f['attr']] : null;
                }
                $surat = Surat::create($isi + [
                    'jenis' => $jenis,
                    'tanggal' => $tanggal,
                    'tahun' => (int) substr($tanggal, 0, 4),
                    'grup' => $grup,
                    'nomor_urut' => $nomor,
                    'sisip' => $sisip,
                    'no_urut' => $noUrut,
                    'nomor_surat' => $format,
                    'lengkap' => true,
                    'sifat' => $jenis === 'st' ? 'Biasa' : null,
                    'status' => $jenis === config('sicatur.lengkapi.jenis') ? config('sicatur.lengkapi.proses') : null,
                    'user_id' => $user->id,
                    'pembuat' => $d['pembuat'] ?? ($user->name ?: $user->username),
                ]);

                if ($jenis === 'sk') {
                    $mentah = [];
                    foreach ($c['form'] as $f) {
                        $mentah[$f['kolom']] = (string) ($d[$f['attr']] ?? '');
                    }
                    $this->kirimWebhookSk($surat, [
                        'noUrut' => $noUrut, 'formatNomor' => $format, 'baris' => $surat->id,
                        'sisipan' => $sisipan, 'induk' => $sisipan ? $this->noUrutTeks($c, $nomor, 0) : null, 'urutanTerjaga' => $terjaga,
                    ], $mentah);
                }

                return [
                    'surat' => $surat,
                    'sisipan' => $sisipan,
                    'induk' => $sisipan ? $this->noUrutTeks($c, $nomor, 0) : null,
                    'urutanTerjaga' => $terjaga,
                ];
            });
        });
    }

    /**
     * Nomor urut berikutnya untuk sebuah tanggal.
     * Bila tanggal mundur & sudah ada nomor yang lebih baru di kelompoknya, nomor menjadi sisipan
     * dari nomor terakhir yang tanggalnya ≤ tanggal itu. Contoh: 100 (5 Nov), 101 (10 Nov) → surat 7 Nov = 100.1.
     *
     * @return array{grup:string, nomor:int, sisip:int, sisipan:bool, terjaga:bool}
     */
    public function hitungNomor(string $jenis, array $d, string $tanggal, bool $mundur): array
    {
        $grup = $this->grup($jenis, $tanggal, $d['tim_teknis'] ?? null);
        $baris = Surat::withTrashed()->where('jenis', $jenis)->where('grup', $grup)->get(['nomor_urut', 'sisip', 'tanggal']);
        $nomor = (int) $baris->max('nomor_urut') + 1;
        $sisip = 0;
        $sisipan = false;
        $terjaga = true;
        if ($mundur && $baris->contains(fn ($b) => $b->tanggal->toDateString() > $tanggal)) {
            $induk = (int) $baris->filter(fn ($b) => $b->tanggal->toDateString() <= $tanggal)->max('nomor_urut');
            if ($induk > 0) {
                $nomor = $induk;
                $sisip = (int) $baris->where('nomor_urut', $induk)->max('sisip') + 1;
                $sisipan = true;
            } else {
                $terjaga = false; // belum ada nomor sebelum tanggal ini
            }
        }

        return compact('grup', 'nomor', 'sisip', 'sisipan', 'terjaga');
    }

    /**
     * Perkiraan nomor yang akan terbit (tanpa menyimpan), untuk ditampilkan di form.
     *
     * @return array{nomor:string, noUrut:string, sisipan:bool, induk:?string, terjaga:bool}|null
     */
    public function pratinjau(string $jenis, array $isian, bool $mundur): ?array
    {
        $c = $this->cfg($jenis);
        $hariIni = $this->hariIni();
        $tanggal = $c['tanggal'] ? trim((string) ($isian[$c['tanggal']] ?? '')) : $hariIni;
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) || $tanggal > $hariIni || ($tanggal < $hariIni && ! $mundur)) {
            return null;
        }
        if ($c['urutan'] === 'bulan_tim' && empty($isian['tim_teknis'])) {
            return null;
        }
        $h = $this->hitungNomor($jenis, $isian, $tanggal, $mundur);
        $noUrut = $this->noUrutTeks($c, $h['nomor'], $h['sisip']);
        $format = $this->formatNomor($jenis, $noUrut, $isian + ['tanggal' => $tanggal, 'sifat' => 'Biasa']);

        return [
            'nomor' => $format ?? '',
            'noUrut' => $noUrut,
            'sisipan' => $h['sisipan'],
            'induk' => $h['sisipan'] ? $this->noUrutTeks($c, $h['nomor'], 0) : null,
            'terjaga' => $h['terjaga'],
        ];
    }

    // =====================================================================
    // Hapus
    // =====================================================================

    public function bolehHapus(Surat $s, User $user): bool
    {
        return $user->is_admin || ($s->user_id && $s->user_id === $user->id);
    }

    /**
     * Hapus sebuah nomor (soft delete). Nomor lain tidak berubah, dan nomor yang dihapus
     * tidak dipakai lagi (penomoran menghitung baris terhapus), jadi tidak ada nomor kembar.
     */
    public function hapus(Surat $s, string $alasan, User $user): void
    {
        abort_unless($this->bolehHapus($s, $user), 403, 'Hanya pembuat nomor atau admin yang bisa menghapus.');
        $alasan = trim(preg_replace('/\s+/', ' ', $alasan));
        if (mb_strlen($alasan) < 3) {
            throw ValidationException::withMessages(['hAlasan' => 'Tulis alasan penghapusan.']);
        }
        DB::transaction(function () use ($s, $alasan, $user) {
            SuratLog::create(['surat_id' => $s->id, 'kolom' => 'Dihapus', 'nilai_lama' => $s->nomorTampil(), 'nilai_baru' => null,
                'user_id' => $user->id, 'oleh' => $user->name ?: $user->username, 'alasan' => mb_substr($alasan, 0, 200)]);
            $s->delete();
        });
    }

    // =====================================================================
    // Link surat (SK)
    // =====================================================================

    public function simpanLink(Surat $s, string $link, User $user): void
    {
        abort_unless($s->jenis === config('sicatur.lengkapi.jenis'), 404);
        $link = trim($link);
        if (mb_strlen($link) > 500 || ! preg_match(self::POLA_TAUTAN, $link)) {
            throw ValidationException::withMessages(['linkBaru' => 'Link harus diawali http:// atau https:// tanpa spasi.']);
        }
        if ($link === (string) $s->link_surat) {
            throw ValidationException::withMessages(['linkBaru' => 'Link ini sama dengan yang tersimpan.']);
        }
        $oleh = $user->name ?: $user->username;
        $diganti = (bool) $s->link_surat;
        $alasan = $diganti ? 'Ganti link surat' : 'Unggah link surat';
        $selesai = config('sicatur.lengkapi.selesai');
        DB::transaction(function () use ($s, $link, $user, $oleh, $alasan, $selesai) {
            SuratLog::create(['surat_id' => $s->id, 'kolom' => 'Link Surat', 'nilai_lama' => $s->link_surat, 'nilai_baru' => $link,
                'user_id' => $user->id, 'oleh' => $oleh, 'alasan' => $alasan]);
            if ($s->status !== $selesai) {
                SuratLog::create(['surat_id' => $s->id, 'kolom' => 'Status', 'nilai_lama' => $s->status, 'nilai_baru' => $selesai,
                    'user_id' => $user->id, 'oleh' => $oleh, 'alasan' => $alasan]);
            }
            $s->update(['link_surat' => $link, 'status' => $selesai, 'link_oleh' => $oleh, 'link_at' => now()]);
        });
        $this->kirimWebhookSkLink($s->fresh(), $diganti, $oleh, $alasan);
    }

    // =====================================================================
    // Webhook SK — sama dengan Webhook.gs aplikasi lama
    // =====================================================================

    private function waktu(): string
    {
        return now(config('sicatur.zona_waktu', 'Asia/Makassar'))->format('Y-m-d H:i:s');
    }

    /** Nilai SK berlabel kolom spreadsheet (Tanggal Surat, Pembuat Surat, Perihal SK, …). */
    private function nilaiSk(Surat $s): array
    {
        $n = [];
        foreach ($this->cfg('sk')['form'] as $f) {
            $n[$f['kolom']] = $s->nilai($f['attr']);
        }

        return $n;
    }

    /** Payload event "pembuatan_sk" (kirimWebhookSK_). */
    public function payloadSk(Surat $s, array $hasil, array $mentah): array
    {
        $n = $this->nilaiSk($s);

        return [
            'event' => 'pembuatan_sk',
            'waktu' => $this->waktu(),
            'instansi' => config('sicatur.nama_instansi'),
            'satker' => $this->satker(),
            'nomor' => [
                'noUrut' => $hasil['noUrut'],
                'formatNomor' => $hasil['formatNomor'],
                'baris' => $hasil['baris'],
                'sisipan' => (bool) $hasil['sisipan'],
                'induk' => $hasil['induk'] ?: null,
                'urutanTerjaga' => (bool) $hasil['urutanTerjaga'],
            ],
            'data' => [
                'tanggalSurat' => $n['Tanggal Surat'],
                'pembuatSurat' => $n['Pembuat Surat'],
                'perihalSK' => $n['Perihal SK'],
                'namaPetugas' => $n['Nama Petugas'],
                'honor' => $n['Honor'],
                'mulaiBerlaku' => $n['Mulai Berlaku'],
                'selesaiBerlaku' => $n['Selesai Berlaku'],
                'status' => config('sicatur.lengkapi.proses'),
                'linkSurat' => '',
            ],
            'raw' => $mentah + ['noUrut' => $hasil['noUrut'], 'formatNomor' => $hasil['formatNomor'], 'baris' => $hasil['baris']],
        ];
    }

    /** Payload event "upload_link_sk" (kirimWebhookSkLink_). */
    public function payloadSkLink(Surat $s, bool $diganti, string $oleh, string $alasan): array
    {
        $n = $this->nilaiSk($s);
        $pemohon = $n['Pembuat Surat'];
        $waPemohon = $this->cariNoWaPegawai($pemohon);

        return [
            'event' => 'upload_link_sk',
            'waktu' => $this->waktu(),
            'instansi' => config('sicatur.nama_instansi'),
            'satker' => $this->satker(),
            'nomor' => [
                'noUrut' => $s->no_urut ?: (string) $s->nomor_urut,
                'formatNomor' => $s->lengkap && $s->nomor_surat ? $s->nomor_surat : 'NOMOR '.($s->no_urut ?: $s->nomor_urut),
                'baris' => $s->id,
                'status' => $s->status,
            ],
            'pemohon' => ['nama' => $pemohon, 'nomorWa' => $waPemohon],
            'pengunggah' => ['nama' => $oleh, 'nomorWa' => $this->cariNoWaPegawai($oleh)],
            'data' => [
                'linkSurat' => $s->link_surat,
                'diganti' => $diganti,
                'alasan' => $alasan,
                'tanggalSurat' => $n['Tanggal Surat'],
                'perihalSK' => $n['Perihal SK'],
                'namaPetugas' => $n['Nama Petugas'],
                'honor' => $n['Honor'],
                'mulaiBerlaku' => $n['Mulai Berlaku'],
                'selesaiBerlaku' => $n['Selesai Berlaku'],
            ],
            'raw' => $n + [
                'linkSurat' => $s->link_surat,
                'status' => $s->status,
                'nomorWaPemohon' => $waPemohon,
                'diunggahOleh' => $oleh,
            ],
        ];
    }

    private function kirimWebhookSk(Surat $s, array $hasil, array $mentah): void
    {
        if ($url = Settings::get('sicatur_webhook_sk')) {
            $payload = $this->payloadSk($s, $hasil, $mentah);
            app()->terminating(fn () => $this->kirim($url, $payload));
        }
    }

    private function kirimWebhookSkLink(Surat $s, bool $diganti, string $oleh, string $alasan): void
    {
        if ($url = Settings::get('sicatur_webhook_sk_link')) {
            $payload = $this->payloadSkLink($s, $diganti, $oleh, $alasan);
            app()->terminating(fn () => $this->kirim($url, $payload));
        }
    }

    /**
     * Kirim POST JSON. Mengembalikan status seperti di Webhook.gs.
     *
     * @return array{terkirim:bool, status:string, kodeStatus?:int, pesan:string, respon?:string}
     */
    public function kirim(string $url, array $payload): array
    {
        try {
            $r = Http::timeout(15)->withHeaders(['Content-Type' => 'application/json; charset=utf-8'])
                ->withBody(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'application/json; charset=utf-8')
                ->post($url);
            $ok = $r->successful();
            Log::info('Webhook SICATUR '.$payload['event'].' → '.$url.' [HTTP '.$r->status().']');

            return [
                'terkirim' => $ok,
                'status' => $ok ? 'sukses' : 'gagal_http',
                'kodeStatus' => $r->status(),
                'pesan' => $ok ? 'Berhasil dikirim ke webhook.' : 'Server webhook mengembalikan status HTTP '.$r->status(),
                'respon' => mb_substr($r->body(), 0, 500),
            ];
        } catch (\Throwable $e) {
            Log::warning('Webhook SICATUR gagal ('.$url.'): '.$e->getMessage());

            return ['terkirim' => false, 'status' => 'gagal_koneksi', 'pesan' => 'Gagal terhubung ke webhook: '.$e->getMessage()];
        }
    }

    /** Uji webhook dengan data contoh (ujiKirimWebhookSK / ujiKirimWebhookSkLink). */
    public function uji(string $jenisWebhook, string $url): array
    {
        $hari = $this->hariIni();
        $s = new Surat([
            'jenis' => 'sk', 'tanggal' => $hari, 'tanggal_mulai' => $hari, 'tanggal_selesai' => $hari,
            'pembuat' => 'Uji Webhook', 'perihal' => 'Uji Coba Pengiriman Data SK ke Webhook SICATUR',
            'nama_petugas' => 'https://drive.google.com/file/d/contoh-link-petugas/view', 'honor' => 'Sesuai Ketentuan SBM '.substr($hari, 0, 4),
            'no_urut' => '999', 'nomor_urut' => 999, 'nomor_surat' => 'NOMOR 999 TAHUN '.substr($hari, 0, 4), 'lengkap' => true,
        ]);
        $s->id = 99;
        if ($jenisWebhook === 'link') {
            $s->link_surat = 'https://drive.google.com/file/d/contoh-link-sk-selesai/view';
            $s->status = config('sicatur.lengkapi.selesai');
            $payload = $this->payloadSkLink($s, false, 'Uji Webhook', 'Unggah berkas SK yang sudah ditandatangani');
        } else {
            $mentah = $this->nilaiSk($s);
            $payload = $this->payloadSk($s, ['noUrut' => '999', 'formatNomor' => $s->nomor_surat, 'baris' => 99, 'sisipan' => false, 'induk' => null, 'urutanTerjaga' => true], $mentah);
        }

        return $this->kirim($url, $payload);
    }

    /** Nomor WA pegawai dari profil PANTAU (kolom HP), dicocokkan dengan nama. */
    public function cariNoWaPegawai(?string $nama): string
    {
        $kunci = mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $nama)));
        if ($kunci === '') {
            return '';
        }
        $u = User::query()->whereRaw('LOWER(TRIM(name)) = ?', [$kunci])->whereNotNull('phone')->first();

        return $this->noWa($u?->phone);
    }

    /** 08xx / 8xx → 628xx */
    public function noWa(?string $n): string
    {
        $s = preg_replace('/\D/', '', (string) $n);
        if ($s === '') {
            return '';
        }
        if (str_starts_with($s, '08')) {
            return '62'.substr($s, 1);
        }
        if (str_starts_with($s, '8')) {
            return '62'.$s;
        }

        return $s;
    }
}
