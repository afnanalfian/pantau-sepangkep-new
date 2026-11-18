<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\MatriksExportController;
use App\Http\Controllers\PushController;
use App\Http\Controllers\Sibeban\TemplateController as SibebanTemplate;
use App\Http\Controllers\SuratController;
use App\Livewire;
use Illuminate\Support\Facades\Route;

/* ----------------------------------------------------------------- Tamu */
Route::middleware('guest')->group(function () {
    Route::get('/login', Livewire\Auth\Login::class)->name('login');
});

/* ----------------------------------------------------------------- Terautentikasi */
Route::middleware(['auth', 'account'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/ganti-password', Livewire\Auth\ChangePassword::class)->name('password.change');

    Route::get('/', Livewire\Dashboard::class)->name('dashboard');

    // Tugas
    Route::get('/tugas', Livewire\Tasks\Board::class)->name('tasks.index');
    Route::get('/tugas/{task}', Livewire\Tasks\Show::class)->name('tasks.show')->withTrashed();
    Route::get('/persetujuan', Livewire\Tasks\Approvals::class)->name('approvals');
    Route::get('/kalender', Livewire\Tasks\Calendar::class)->name('calendar');
    Route::get('/tim', Livewire\Teams\Directory::class)->name('teams.index');

    // Notifikasi & profil
    Route::get('/notifikasi', Livewire\Notifications\Index::class)->name('notifications');
    Route::get('/profil', Livewire\Profile::class)->name('profile');
    Route::get('/profil/matriks/{bulan}', [MatriksExportController::class, 'pribadi'])->name('profile.matriks')
        ->where('bulan', '\d{4}-\d{2}');
    Route::get('/portofolio/export', [ExportController::class, 'portfolio'])->name('portfolio.export');

    // Lampiran & Web Push
    Route::get('/lampiran/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');
    Route::post('/push/subscribe', [PushController::class, 'subscribe'])->name('push.subscribe');
    Route::post('/push/unsubscribe', [PushController::class, 'unsubscribe'])->name('push.unsubscribe');
    Route::post('/push/test', [PushController::class, 'test'])->name('push.test');

    // Presensi apel & rapat (admin, pimpinan, petugas presensi)
    Route::middleware('role:presensi')->group(function () {
        Route::get('/presensi', Livewire\Presensi\Index::class)->name('presensi.index');
        Route::get('/presensi/{kegiatan}', Livewire\Presensi\Checklist::class)->name('presensi.show');
    });

    // Pemantauan
    Route::get('/beban-kerja', Livewire\Monitoring\Workload::class)->name('workload')->middleware('role:monitor');
    Route::middleware('role:matriks')->group(function () {
        Route::get('/matriks', Livewire\Monitoring\Matriks::class)->name('matriks');
        Route::get('/matriks/excel', [MatriksExportController::class, 'excel'])->name('matriks.excel');
        Route::get('/matriks/pdf', [MatriksExportController::class, 'pdf'])->name('matriks.pdf');
    });

    // Portal Inovasi
    Route::prefix('portal')->name('portal.')->group(function () {
        Route::get('/', Livewire\Portal\Home::class)->name('home');
        Route::get('/sicatur', Livewire\Portal\Sicatur::class)->name('sicatur');
        Route::get('/sicatur/lampiran/{surat}', [SuratController::class, 'file'])->name('sicatur.file');
        Route::get('/sicatur/export', [SuratController::class, 'export'])->name('sicatur.export');
        Route::get('/siku', Livewire\Portal\Siku::class)->name('siku');
        Route::get('/kepegawaian', Livewire\Portal\Kepegawaian::class)->name('kepegawaian');
    });

    // SIBEBAN – monitoring honor mitra (bagian Portal Inovasi)
    Route::prefix('portal/sibeban')->name('sibeban.')->group(function () {
        Route::get('/', Livewire\Sibeban\Monitoring::class)->name('monitoring');
        Route::get('/kegiatan', Livewire\Sibeban\Kegiatan\Index::class)->name('kegiatan.index');
        Route::get('/kegiatan/{kegiatan}', Livewire\Sibeban\Kegiatan\Show::class)->name('kegiatan.show');
        Route::get('/kegiatan/{kegiatan}/template', [SibebanTemplate::class, 'penugasan'])->name('kegiatan.template');
        Route::get('/mitra', Livewire\Sibeban\Mitra\Index::class)->name('mitra.index');
        Route::get('/mitra/template', [SibebanTemplate::class, 'mitra'])->name('mitra.template');
        Route::get('/mitra/{mitra}', Livewire\Sibeban\Mitra\Show::class)->name('mitra.show');
        Route::get('/pengaturan', Livewire\Sibeban\PengaturanLimit::class)->name('pengaturan')->middleware('role:admin');
    });

    // Administrator
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/pengguna', Livewire\Admin\Users::class)->name('users');
        Route::get('/tim', Livewire\Admin\Teams::class)->name('teams');
        Route::get('/cuti', Livewire\Leaves::class)->name('leaves');
        Route::get('/pengaturan', Livewire\Admin\Settings::class)->name('settings');
        Route::get('/template/{type}', [ExportController::class, 'template'])->name('template')
            ->whereIn('type', ['akun', 'biodata']);
        Route::get('/google/hubungkan', [GoogleAuthController::class, 'redirect'])->name('google.connect');
        Route::get('/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');
        Route::post('/google/putuskan', [GoogleAuthController::class, 'disconnect'])->name('google.disconnect');
    });
});
