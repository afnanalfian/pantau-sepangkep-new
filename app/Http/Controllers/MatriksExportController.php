<?php

namespace App\Http\Controllers;

use App\Livewire\Monitoring\Matriks;
use App\Models\User;
use App\Services\KinerjaService;
use App\Support\Settings;
use App\Support\Spreadsheet;
use App\Support\WorkingDays;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MatriksExportController extends Controller
{
    private function data(Request $request): array
    {
        $month = Carbon::parse(($request->input('bulan') ?: now()->format('Y-m')).'-01');
        $rows = app(KinerjaService::class)->matriks(Matriks::usersFor($request->user(), $request->input('tim')), $month)->values();

        return [$month, $rows];
    }

    private static function fmt(?float $v): string
    {
        return $v === null ? '–' : rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
    }

    public function excel(Request $request, KinerjaService $svc)
    {
        [$month, $rows] = $this->data($request);
        $w = KinerjaService::weights();

        $i = 0;
        $data = $rows->map(fn ($r) => [
            ++$i,
            (string) $r['user']->nip,
            $r['user']->display_name,
            $r['user']->jabatan,
            $r['user']->teams->pluck('name')->join(', '),
            $r['tugas_selesai'], $r['tugas_total'], $r['tugas_pct'] ?? '–',
            $r['apel_hadir'], $r['apel_wajib'], $r['apel_pct'] ?? '–',
            $r['rapat_hadir'], $r['rapat_wajib'], $r['rapat_pct'] ?? '–',
            $r['nilai'],
            $r['potongan'],
        ]);

        return Spreadsheet::download(
            'matriks_tk_'.$month->format('Y_m').'.xlsx',
            'Matriks '.$month->format('m-Y'),
            ['No', 'NIP', 'Nama', 'Jabatan', 'Tim', 'Tugas Selesai', 'Tugas Total', 'Penyelesaian Tugas (%)',
                'Apel Hadir', 'Apel Wajib', 'Kehadiran Apel (%)', 'Rapat Hadir', 'Rapat Wajib', 'Kehadiran Rapat (%)',
                'Nilai Akhir (%)', 'Potongan TK (%)'],
            $data,
            'Matriks Kinerja & Potongan Tunjangan Kinerja – '.WorkingDays::monthName($month),
            Settings::get('office_name').' · Bobot tugas '.$w['tugas'].', apel '.$w['apel'].', rapat '.$w['rapat'].
            ' · Potongan: 100% = 0%, >'.Settings::get('batas_potongan', 80).'% = '.Settings::get('potongan_ringan', 1).'%, ≤'.Settings::get('batas_potongan', 80).'% = '.Settings::get('potongan_berat', 2).'%'
        );
    }

    public function pdf(Request $request)
    {
        [$month, $rows] = $this->data($request);
        $pimpinan = User::primaryPimpinan();

        $pdf = Pdf::loadView('pdf.matriks', [
            'rows' => $rows,
            'month' => $month,
            'w' => KinerjaService::weights(),
            'pimpinan' => $pimpinan,
            'fmt' => fn ($v) => self::fmt($v),
            'final' => ! $month->isSameMonth(now()),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('matriks_tk_'.$month->format('Y_m').'.pdf');
    }

    /** Matriks pribadi (dari halaman profil). */
    public function pribadi(Request $request, string $bulan)
    {
        $month = Carbon::parse($bulan.'-01');
        $r = app(KinerjaService::class)->forUser($request->user(), $month);

        $pdf = Pdf::loadView('pdf.matriks', [
            'rows' => collect([$r]),
            'month' => $month,
            'w' => KinerjaService::weights(),
            'pimpinan' => User::primaryPimpinan(),
            'fmt' => fn ($v) => self::fmt($v),
            'final' => ! $month->isSameMonth(now()),
            'detail' => $r,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('matriks_'.$request->user()->username.'_'.$month->format('Y_m').'.pdf');
    }
}
