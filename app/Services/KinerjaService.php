<?php

namespace App\Services;

use App\Models\Leave;
use App\Models\PresensiKegiatan;
use App\Models\PresensiKehadiran;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use App\Support\Settings;
use App\Support\WorkingDays;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Matriks kinerja bulanan (dasar pertimbangan Tunjangan Kinerja).
 *
 *  1. Penyelesaian tugas  = tugas selesai ÷ tugas yang menjadi tanggungan bulan itu.
 *     Tanggungan bulan M  = tugas ber-deadline di bulan M
 *                         + tugas bulan sebelumnya yang belum selesai saat bulan M dimulai (carry-over).
 *     Dihitung selesai bila sudah DIVERIFIKASI (status "selesai") paling lambat akhir bulan M.
 *  2. Kehadiran apel      = apel hadir ÷ apel yang wajib diikuti (izin/cuti/DL dikecualikan).
 *  3. Kehadiran rapat     = idem untuk rapat.
 *
 *  Nilai akhir = Σ(bobot × persentase) ÷ Σ bobot   (komponen tanpa data tidak ikut dihitung)
 *  Potongan TK : 100% → 0% · > batas (80%) → 1% · ≤ batas → 2%
 */
class KinerjaService
{
    public const BEBAN = [
        'kosong' => ['label' => 'Tanpa tugas', 'class' => 'bg-slate-300', 'soft' => 'bg-slate-100 text-slate-600 ring-slate-400/30'],
        'ringan' => ['label' => 'Ringan', 'class' => 'bg-amber-400', 'soft' => 'bg-amber-50 text-amber-700 ring-amber-600/20'],
        'normal' => ['label' => 'Normal', 'class' => 'bg-bps-green', 'soft' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'],
        'padat' => ['label' => 'Padat', 'class' => 'bg-rose-500', 'soft' => 'bg-rose-50 text-rose-700 ring-rose-600/20'],
    ];

    public const ACTIVITY = [
        'aktif' => ['label' => 'Aktif', 'dot' => 'bg-emerald-500'],
        'pasif' => ['label' => 'Pasif', 'dot' => 'bg-amber-400'],
        'dorman' => ['label' => 'Dorman', 'dot' => 'bg-rose-500'],
    ];

    /** Bobot komponen dari pengaturan. */
    public static function weights(): array
    {
        return [
            'tugas' => (float) Settings::get('bobot_tugas', 80),
            'apel' => (float) Settings::get('bobot_apel', 20),
            'rapat' => (float) Settings::get('bobot_rapat', 10),
        ];
    }

    public static function potongan(float $nilai): float
    {
        $batas = (float) Settings::get('batas_potongan', 80);
        if (round($nilai, 2) >= 100) {
            return 0;
        }

        return round($nilai, 2) > $batas
            ? (float) Settings::get('potongan_ringan', 1)
            : (float) Settings::get('potongan_berat', 2);
    }

    /**
     * @param  Collection<int, User>  $users
     * @return Collection<int, array> keyed by user id
     */
    public function matriks(Collection $users, ?Carbon $month = null): Collection
    {
        $month = ($month ?? now())->copy()->startOfMonth();
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $ids = $users->pluck('id')->all();
        if (! $ids) {
            return collect();
        }

        $tugas = $this->tugas($ids, $start, $end, $month->isSameMonth(now()));
        $presensi = $this->presensi($users, $start, $end);
        $aktif = $this->bebanAktif($ids);
        $lastActivity = TaskActivity::query()->whereIn('user_id', $ids)
            ->selectRaw('user_id, MAX(created_at) as last_at')->groupBy('user_id')->pluck('last_at', 'user_id');
        $w = self::weights();
        $isCurrent = $month->isSameMonth(now());

        return $users->mapWithKeys(function (User $u) use ($tugas, $presensi, $aktif, $lastActivity, $w, $isCurrent) {
            $t = $tugas[$u->id] ?? ['total' => 0, 'selesai' => 0, 'carry' => 0, 'gagal' => 0, 'berjalan' => 0, 'items' => collect()];
            $a = $presensi[$u->id]['apel'] ?? ['wajib' => 0, 'hadir' => 0, 'izin' => 0];
            $r = $presensi[$u->id]['rapat'] ?? ['wajib' => 0, 'hadir' => 0, 'izin' => 0];

            $pct = fn ($num, $den) => $den > 0 ? round($num / $den * 100, 2) : null;
            $pTugas = $pct($t['selesai'], $t['total']);
            $pApel = $pct($a['hadir'], $a['wajib']);
            $pRapat = $pct($r['hadir'], $r['wajib']);

            $sumW = 0;
            $sum = 0;
            foreach (['tugas' => $pTugas, 'apel' => $pApel, 'rapat' => $pRapat] as $k => $p) {
                if ($p !== null && $w[$k] > 0) {
                    $sum += $w[$k] * $p;
                    $sumW += $w[$k];
                }
            }
            $nilai = $sumW > 0 ? round($sum / $sumW, 2) : 100.0;

            $act = $aktif[$u->id] ?? ['aktif' => 0, 'terlambat' => 0];
            $min = (int) Settings::get('beban_ringan', 2);
            $max = (int) Settings::get('beban_padat', 8);
            $beban = match (true) {
                $act['aktif'] === 0 => 'kosong',
                $act['aktif'] < $min => 'ringan',
                $act['aktif'] > $max => 'padat',
                default => 'normal',
            };

            $last = $lastActivity[$u->id] ?? null;
            $lastAt = $last ? Carbon::parse($last) : null;
            $idle = $lastAt ? WorkingDays::between($lastAt->copy()->addDay(), now()) : 99;

            return [$u->id => [
                'user' => $u,
                'tugas_total' => $t['total'],          // penyebut (bulan berjalan: yang deadline-nya sudah tiba / sudah selesai)
                'tugas_semua' => $t['items']->count(),  // seluruh tugas tanggungan bulan ini
                'tugas_selesai' => $t['selesai'],
                'tugas_carry' => $t['carry'],
                'tugas_gagal' => $t['gagal'],       // deadline sudah lewat & belum selesai
                'tugas_berjalan' => $t['berjalan'], // deadline belum lewat & belum selesai
                'tugas_pct' => $pTugas,
                'tugas_items' => $t['items'],
                'apel_wajib' => $a['wajib'], 'apel_hadir' => $a['hadir'], 'apel_izin' => $a['izin'], 'apel_pct' => $pApel,
                'rapat_wajib' => $r['wajib'], 'rapat_hadir' => $r['hadir'], 'rapat_izin' => $r['izin'], 'rapat_pct' => $pRapat,
                'nilai' => $nilai,
                'potongan' => self::potongan($nilai),
                'final' => ! $isCurrent,
                'aktif' => $act['aktif'],
                'terlambat' => $act['terlambat'],
                'beban' => $beban,
                'activity' => $idle <= 1 ? 'aktif' : ($idle <= 4 ? 'pasif' : 'dorman'),
                'last_activity_at' => $lastAt,
            ]];
        });
    }

    public function forUser(User $user, ?Carbon $month = null): array
    {
        return $this->matriks(collect([$user]), $month)->first();
    }

    /** Riwayat matriks beberapa bulan terakhir untuk satu pegawai. */
    public function riwayat(User $user, int $bulan = 12): Collection
    {
        $first = now()->subMonths($bulan - 1)->startOfMonth();
        if ($user->created_at && $user->created_at->copy()->startOfMonth()->gt($first)) {
            $first = $user->created_at->copy()->startOfMonth();
        }
        $out = collect();
        for ($m = now()->copy()->startOfMonth(); $m->gte($first); $m->subMonth()) {
            $out->push(['bulan' => $m->copy()] + $this->forUser($user, $m->copy()));
        }

        return $out;
    }

    /* ================================================================ Tugas */

    /**
     * Bulan berjalan: tugas yang deadline-nya belum tiba & belum selesai dicatat "berjalan"
     * dan BELUM masuk penyebut, agar persentase sementara tidak menghukum tugas yang masih punya waktu.
     */
    private function tugas(array $ids, Carbon $start, Carbon $end, bool $berjalan = false): array
    {
        $s = $start->toDateString();
        $e = $end->toDateString();
        $today = now()->toDateString();

        $rows = Task::query()
            ->whereIn('assignee_id', $ids)
            ->whereNotIn('status', ['batal', 'ditolak', 'pengajuan'])
            ->whereNotNull('due_date')
            ->whereRaw('COALESCE(extension_until, due_date) <= ?', [$e])
            ->where(function ($q) use ($s, $start) {
                $q->whereRaw('COALESCE(extension_until, due_date) >= ?', [$s])
                    // carry-over: belum selesai saat bulan dimulai
                    ->orWhere(fn ($w) => $w->where('status', '!=', 'selesai')->orWhere('completed_at', '>=', $start));
            })
            ->get(['id', 'code', 'title', 'assignee_id', 'status', 'due_date', 'extension_until', 'completed_at']);

        $out = [];
        foreach ($rows as $t) {
            $due = ($t->extension_until ?? $t->due_date)->toDateString();
            $done = $t->status === 'selesai' && $t->completed_at && $t->completed_at->lte($end);
            $carry = $due < $s;
            $o = &$out[$t->assignee_id];
            $o ??= ['total' => 0, 'selesai' => 0, 'carry' => 0, 'gagal' => 0, 'berjalan' => 0, 'items' => collect()];
            $pending = ! $done && $berjalan && $due >= $today;
            if (! $pending) {
                $o['total']++;
            }
            $o['selesai'] += $done ? 1 : 0;
            $o['carry'] += $carry ? 1 : 0;
            if (! $done) {
                $due < $today ? $o['gagal']++ : $o['berjalan']++;
            }
            $o['items']->push(['task' => $t, 'done' => $done, 'carry' => $carry, 'due' => $due, 'pending' => $pending]);
            unset($o);
        }

        return $out;
    }

    /** Jumlah tugas aktif & terlambat saat ini (untuk peta beban kerja). */
    private function bebanAktif(array $ids): array
    {
        $today = now()->toDateString();

        return Task::query()->whereIn('assignee_id', $ids)->whereIn('status', Task::ACTIVE_STATUSES)
            ->get(['assignee_id', 'status', 'due_date', 'extension_until'])
            ->groupBy('assignee_id')
            ->map(fn ($g) => [
                'aktif' => $g->count(),
                'terlambat' => $g->filter(fn ($t) => $t->status !== 'review' && ($d = $t->extension_until ?? $t->due_date) && $d->toDateString() < $today)->count(),
            ])->all();
    }

    /* ================================================================ Presensi */

    private function presensi(Collection $users, Carbon $start, Carbon $end): array
    {
        $events = PresensiKegiatan::whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->whereDate('tanggal', '<=', now())
            ->get(['id', 'jenis', 'tanggal']);
        if ($events->isEmpty()) {
            return [];
        }
        $ids = $users->pluck('id')->all();
        $marks = PresensiKehadiran::whereIn('presensi_kegiatan_id', $events->pluck('id'))->whereIn('user_id', $ids)
            ->get(['presensi_kegiatan_id', 'user_id', 'status'])
            ->groupBy('user_id')->map(fn ($g) => $g->pluck('status', 'presensi_kegiatan_id'));
        $leaves = Leave::whereIn('user_id', $ids)->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start)
            ->get()->groupBy('user_id');

        $out = [];
        foreach ($users as $u) {
            $my = $marks->get($u->id, collect());
            foreach ($events as $ev) {
                // Pegawai baru: kegiatan sebelum akunnya dibuat tidak dihitung
                if ($u->created_at && $ev->tanggal->lt($u->created_at->copy()->startOfDay())) {
                    continue;
                }
                $status = $my[$ev->id] ?? null;
                $onLeave = $leaves->get($u->id, collect())->contains(fn ($l) => $ev->tanggal->between($l->start_date, $l->end_date));
                $slot = &$out[$u->id][$ev->jenis];
                $slot ??= ['wajib' => 0, 'hadir' => 0, 'izin' => 0];
                if ($status === 'hadir') {
                    $slot['wajib']++;
                    $slot['hadir']++;
                } elseif ($status === 'izin' || ($status === null && $onLeave)) {
                    $slot['izin']++;
                } else {
                    $slot['wajib']++;
                }
                unset($slot);
            }
        }

        return $out;
    }
}
