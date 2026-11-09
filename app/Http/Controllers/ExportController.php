<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Services\WorkloadService;
use App\Support\Spreadsheet;
use App\Support\WorkingDays;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ExportController extends Controller
{
    /** Template import akun / biodata. */
    public function template(string $type)
    {
        if ($type === 'akun') {
            return Spreadsheet::download('template_import_akun.xlsx', 'Akun', ['username', 'password'], [
                ['contoh.pegawai', 'PasswordAwal123'],
            ]);
        }

        $rows = User::orderBy('username')->get()->map(fn ($u) => [$u->username, $u->name, $u->nip, $u->jabatan]);

        return Spreadsheet::download('template_import_biodata.xlsx', 'Biodata', ['username', 'nama', 'nip', 'jabatan'], $rows);
    }

    /** Rekap kinerja bulanan seluruh pegawai (dasar perhitungan Tukin komponen kinerja 70%). */
    public function monthly(Request $request, WorkloadService $workload)
    {
        $month = Carbon::parse($request->input('bulan', now()->format('Y-m')).'-01');
        $viewer = $request->user();
        $users = User::pegawai()->with('teams')->orderBy('name')->get();
        if (! $viewer->is_pimpinan && ! $viewer->is_admin) {
            $teamIds = $viewer->ledTeamIds();
            $users = $users->filter(fn ($u) => $u->teams->pluck('id')->intersect($teamIds)->isNotEmpty());
        }
        $sum = $workload->summarize($users, $month);

        $i = 0;
        $rows = $sum->map(function ($s) use (&$i) {
            $u = $s['user'];
            $capaian = min($s['achievement_pct'], 100);

            return [
                ++$i, $u->nip, $u->display_name, $u->jabatan, $u->teams->pluck('name')->join(', '),
                $s['target'], $s['verified'], $s['committed'], $s['frozen'],
                $s['achievement_pct'], $s['projection_pct'],
                $s['done_count'], $s['active_count'], $s['overdue_count'],
                \App\Services\WorkloadService::LEVELS[$s['level']]['label'],
                round($capaian * 0.7, 2),
                max($s['achievement_pct'] - 100, 0) > 0 ? round($s['achievement_pct'] - 100, 1).'% (prestasi tambahan)' : '',
            ];
        });

        $response = Spreadsheet::download(
            'rekap_kinerja_'.$month->format('Y_m').'.xlsx',
            'Rekap '.$month->format('m-Y'),
            ['No', 'NIP', 'Nama', 'Jabatan', 'Tim', 'Target Poin', 'Poin Terverifikasi', 'Poin Berjalan', 'Poin Dibekukan (Overdue)',
                'Capaian Kinerja (%)', 'Proyeksi (%)', 'Tugas Selesai', 'Tugas Aktif', 'Terlambat', 'Status Beban', 'Komponen Kinerja Tukin (%, maks 70)', 'Catatan'],
            $rows,
            'Rekap Capaian Kinerja Pegawai – '.WorkingDays::monthName($month),
            'PANTAU SEPANGKEP · '.config('app.name').' · dicetak '.now()->format('d/m/Y H:i').' · Capaian = poin terverifikasi / target (prorata cuti/DL)'
        );

        return $response;
    }

    /** Portofolio realisasi kinerja (bahan KIPPAPP) – per bulan atau triwulan. */
    public function portfolio(Request $request)
    {
        $user = $request->user();
        if ($request->filled('user') && ($user->is_pimpinan || $user->is_admin)) {
            $user = User::findOrFail($request->integer('user'));
        }

        $period = $request->input('periode', 'bulan');
        $ref = Carbon::parse($request->input('bulan', now()->format('Y-m')).'-01');
        [$start, $end, $label] = $period === 'triwulan'
            ? [$ref->copy()->firstOfQuarter(), $ref->copy()->lastOfQuarter()->endOfDay(), 'Triwulan '.$ref->quarter.' '.$ref->year]
            : [$ref->copy()->startOfMonth(), $ref->copy()->endOfMonth(), WorkingDays::monthName($ref)];

        $tasks = Task::with(['catalog', 'attachments', 'approver'])
            ->where('assignee_id', $user->id)->where('status', 'selesai')
            ->whereBetween('completed_at', [$start, $end])->orderBy('completed_at')->get();

        $i = 0;
        $rows = $tasks->map(fn (Task $t) => [
            ++$i,
            $t->completed_at->format('d/m/Y'),
            $t->code,
            $t->title,
            $t->catalog?->rhk ?? '-',
            $t->catalog?->unit ?? 'kegiatan',
            $t->quantity,
            $t->points,
            $t->attachments->where('category', 'output')->map(fn ($a) => $a->kind === 'link' || $a->url ? ($a->url ?: $a->openUrl()) : $a->openUrl())->join("\n") ?: $t->url(),
            $t->approver?->display_name ?? 'Otomatis',
        ]);

        return Spreadsheet::download(
            'portofolio_'.$user->username.'_'.str_replace(' ', '_', strtolower($label)).'.xlsx',
            'Portofolio',
            ['No', 'Tanggal Selesai', 'Kode', 'Kegiatan / Uraian Tugas', 'Butir RHK', 'Satuan', 'Volume', 'Poin', 'Bukti Dukung', 'Diverifikasi Oleh'],
            $rows,
            'Portofolio Realisasi Kinerja – '.$user->display_name,
            'NIP '.($user->nip ?: '-').' · '.$label.' · Total '.poin($tasks->sum('points')).' poin dari '.$tasks->count().' tugas terverifikasi'
        );
    }
}
