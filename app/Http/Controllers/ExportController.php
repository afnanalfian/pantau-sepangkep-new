<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
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

    /** Portofolio realisasi kerja (bahan KIPPAPP) – per bulan atau triwulan. */
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

        $tasks = Task::with(['attachments', 'approver', 'team'])
            ->where('assignee_id', $user->id)->where('status', 'selesai')
            ->whereBetween('completed_at', [$start, $end])->orderBy('completed_at')->get();

        $i = 0;
        $rows = $tasks->map(fn (Task $t) => [
            ++$i,
            $t->completed_at->format('d/m/Y'),
            $t->code,
            $t->title,
            $t->team?->name ?? '-',
            $t->effectiveDueDate()?->format('d/m/Y') ?? '-',
            $t->attachments->where('category', 'output')->map(fn ($a) => $a->url ?: $a->openUrl())->join("\n") ?: $t->url(),
            $t->approver?->display_name ?? 'Otomatis',
        ]);

        return Spreadsheet::download(
            'portofolio_'.$user->username.'_'.str_replace(' ', '_', strtolower($label)).'.xlsx',
            'Portofolio',
            ['No', 'Tanggal Selesai', 'Kode', 'Kegiatan / Uraian Tugas', 'Tim', 'Deadline', 'Bukti Dukung', 'Diverifikasi Oleh'],
            $rows,
            'Portofolio Realisasi Kerja – '.$user->display_name,
            'NIP '.($user->nip ?: '-').' · '.$label.' · '.$tasks->count().' tugas terverifikasi'
        );
    }
}
