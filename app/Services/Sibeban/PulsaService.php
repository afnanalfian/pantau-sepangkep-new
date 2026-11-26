<?php

namespace App\Services\Sibeban;

use App\Models\Sibeban\Kegiatan;
use App\Models\Sibeban\Mitra;
use App\Models\Sibeban\Pulsa;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pulsa petugas SIBEBAN.
 *  - Petugas yang bisa dipilih: punya kegiatan SIBEBAN yang berjalan di periode pulsa
 *    dan belum menerima pulsa lain yang periodenya beririsan.
 *  - Pulsa dicatat terpisah dari honor: tidak menambah total honor / tidak memotong sisa limit.
 */
class PulsaService
{
    public static function bulanValid(?string $b): bool
    {
        return (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $b);
    }

    /** [tanggal awal bulan mulai, tanggal akhir bulan selesai] */
    public function rentang(string $mulai, string $selesai): array
    {
        return [
            Carbon::createFromFormat('Y-m-d', $mulai.'-01')->startOfMonth()->toDateString(),
            Carbon::createFromFormat('Y-m-d', $selesai.'-01')->endOfMonth()->toDateString(),
        ];
    }

    /** Kegiatan yang berjalan (beririsan) di periode, per mitra: [mitra_id => [nama kegiatan, …]] */
    public function kegiatanPerMitra(string $mulai, string $selesai, ?array $mitraIds = null): array
    {
        [$awal, $akhir] = $this->rentang($mulai, $selesai);
        $rows = DB::table('sibeban_penugasans')
            ->join('sibeban_kegiatans', 'sibeban_kegiatans.id', '=', 'sibeban_penugasans.kegiatan_id')
            ->whereDate('sibeban_kegiatans.tanggal_mulai', '<=', $akhir)
            ->whereDate('sibeban_kegiatans.tanggal_selesai', '>=', $awal)
            ->when($mitraIds !== null, fn ($q) => $q->whereIn('sibeban_penugasans.mitra_id', $mitraIds))
            ->orderBy('sibeban_kegiatans.tanggal_mulai')
            ->get(['sibeban_penugasans.mitra_id', 'sibeban_kegiatans.nama']);
        $hasil = [];
        foreach ($rows as $r) {
            $hasil[$r->mitra_id][] = $r->nama;
        }

        return array_map(fn ($n) => array_values(array_unique($n)), $hasil);
    }

    /** Mitra yang sudah punya pulsa beririsan: [mitra_id => Pulsa] */
    public function sudahPunya(string $mulai, string $selesai, ?int $kecualiPulsaId = null): array
    {
        $rows = DB::table('sibeban_pulsa_petugas')
            ->join('sibeban_pulsas', 'sibeban_pulsas.id', '=', 'sibeban_pulsa_petugas.pulsa_id')
            ->where('sibeban_pulsas.bulan_mulai', '<=', $selesai)->where('sibeban_pulsas.bulan_selesai', '>=', $mulai)
            ->when($kecualiPulsaId, fn ($q) => $q->where('sibeban_pulsas.id', '!=', $kecualiPulsaId))
            ->get(['sibeban_pulsa_petugas.mitra_id', 'sibeban_pulsas.id']);

        return $rows->pluck('id', 'mitra_id')->all();
    }

    /**
     * Petugas yang bisa dipilih untuk periode ini.
     *
     * @return array<int, array{mitra: Mitra, kegiatan: array<string>}>
     */
    public function kandidat(string $mulai, string $selesai, array $f = [], ?int $pulsaId = null): array
    {
        if (! self::bulanValid($mulai) || ! self::bulanValid($selesai) || $selesai < $mulai) {
            return [];
        }
        $kegiatan = $this->kegiatanPerMitra($mulai, $selesai);
        $punya = $this->sudahPunya($mulai, $selesai);   // termasuk pulsa ini sendiri (sudah terdaftar)
        $ids = array_diff(array_keys($kegiatan), array_keys($punya));
        if (! $ids) {
            return [];
        }
        $cari = trim($f['cari'] ?? '');

        return Mitra::query()->whereIn('id', $ids)
            ->when($cari !== '', fn ($q) => $q->where(fn ($w) => $w->where('nama', 'like', "%{$cari}%")->orWhere('sobat_id', 'like', "%{$cari}%")))
            ->when($f['kecamatan'] ?? '', fn ($q, $v) => $q->where('kecamatan', $v))
            ->when($f['desa'] ?? '', fn ($q, $v) => $q->where('desa', $v))
            ->orderBy('nama')
            ->get(['id', 'sobat_id', 'nama', 'kecamatan', 'desa', 'posisi'])
            ->map(fn ($m) => ['mitra' => $m, 'kegiatan' => $kegiatan[$m->id] ?? []])
            ->all();
    }

    /** Periksa ulang saat menyimpan (bisa saja sudah diambil orang lain). */
    private function pastikanBoleh(string $mulai, string $selesai, array $mitraIds, ?int $pulsaId): void
    {
        $kegiatan = $this->kegiatanPerMitra($mulai, $selesai, $mitraIds);
        $punya = $this->sudahPunya($mulai, $selesai, $pulsaId);
        $nama = Mitra::whereIn('id', $mitraIds)->pluck('nama', 'id');
        $tanpaKegiatan = array_values(array_filter($mitraIds, fn ($id) => empty($kegiatan[$id])));
        $bentrok = array_values(array_filter($mitraIds, fn ($id) => isset($punya[$id])));
        $pesan = [];
        if ($bentrok) {
            $pesan[] = 'Sudah menerima pulsa lain di periode ini: '.collect($bentrok)->map(fn ($i) => $nama[$i] ?? $i)->implode(', ').'.';
        }
        if ($tanpaKegiatan) {
            $pesan[] = 'Tidak punya kegiatan di periode ini: '.collect($tanpaKegiatan)->map(fn ($i) => $nama[$i] ?? $i)->implode(', ').'.';
        }
        if ($pesan) {
            throw ValidationException::withMessages(['pDipilih' => implode(' ', $pesan)]);
        }
    }

    public function buat(array $data, array $mitraIds, User $u): Pulsa
    {
        $mitraIds = array_values(array_unique(array_map('intval', $mitraIds)));
        if (! $mitraIds) {
            throw ValidationException::withMessages(['pDipilih' => 'Pilih minimal satu petugas.']);
        }

        return Cache::lock('sibeban-pulsa', 20)->block(15, function () use ($data, $mitraIds, $u) {
            return DB::transaction(function () use ($data, $mitraIds, $u) {
                $this->pastikanBoleh($data['bulan_mulai'], $data['bulan_selesai'], $mitraIds, null);
                $p = Pulsa::create($data + ['user_id' => $u->id, 'pembuat' => $u->name ?: $u->username]);
                $p->mitras()->attach($mitraIds);

                return $p;
            });
        });
    }

    public function tambahPetugas(Pulsa $p, array $mitraIds): int
    {
        $mitraIds = array_values(array_diff(array_unique(array_map('intval', $mitraIds)), $p->mitras()->pluck('sibeban_mitras.id')->all()));
        if (! $mitraIds) {
            throw ValidationException::withMessages(['pDipilih' => 'Pilih minimal satu petugas.']);
        }

        return Cache::lock('sibeban-pulsa', 20)->block(15, function () use ($p, $mitraIds) {
            return DB::transaction(function () use ($p, $mitraIds) {
                $this->pastikanBoleh($p->bulan_mulai, $p->bulan_selesai, $mitraIds, $p->id);
                $p->mitras()->attach($mitraIds);

                return count($mitraIds);
            });
        });
    }

    /** Pulsa per mitra yang mencakup bulan tertentu: [mitra_id => Pulsa] (untuk ketersediaan & detail mitra). */
    public function perMitraBulan(array $mitraIds, string $bulan): array
    {
        if (! $mitraIds) {
            return [];
        }
        $hasil = [];
        foreach (Pulsa::beririsan($bulan, $bulan)->whereHas('mitras', fn ($q) => $q->whereIn('sibeban_mitras.id', $mitraIds))
            ->with(['mitras' => fn ($q) => $q->whereIn('sibeban_mitras.id', $mitraIds)->select('sibeban_mitras.id')])->get() as $p) {
            foreach ($p->mitras as $m) {
                $hasil[$m->id] = $p;
            }
        }

        return $hasil;
    }
}
