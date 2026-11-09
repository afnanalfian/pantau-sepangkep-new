<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\PushController;
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

    // Tim & kehadiran
    Route::get('/tim', Livewire\Teams\Directory::class)->name('teams.index');
    Route::get('/cuti-dinas', Livewire\Leaves::class)->name('leaves');

    // Notifikasi & profil
    Route::get('/notifikasi', Livewire\Notifications\Index::class)->name('notifications');
    Route::get('/profil', Livewire\Profile::class)->name('profile');

    // Lampiran (stream dari lokal / Google Drive)
    Route::get('/lampiran/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');

    // Web Push
    Route::post('/push/subscribe', [PushController::class, 'subscribe'])->name('push.subscribe');
    Route::post('/push/unsubscribe', [PushController::class, 'unsubscribe'])->name('push.unsubscribe');
    Route::post('/push/test', [PushController::class, 'test'])->name('push.test');

    // Pemantauan (pimpinan & ketua tim)
    Route::middleware('role:monitor')->group(function () {
        Route::get('/beban-kerja', Livewire\Monitoring\Workload::class)->name('workload');
        Route::get('/laporan', Livewire\Monitoring\Reports::class)->name('reports');
        Route::get('/laporan/export', [ExportController::class, 'monthly'])->name('reports.export');
    });

    // Portofolio pribadi (KIPPAPP)
    Route::get('/portofolio/export', [ExportController::class, 'portfolio'])->name('portfolio.export');

    // Administrator
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/pengguna', Livewire\Admin\Users::class)->name('users');
        Route::get('/tim', Livewire\Admin\Teams::class)->name('teams');
        Route::get('/katalog', Livewire\Admin\Catalog::class)->name('catalog');
        Route::get('/pengaturan', Livewire\Admin\Settings::class)->name('settings');
        Route::get('/template/{type}', [ExportController::class, 'template'])->name('template')
            ->whereIn('type', ['akun', 'biodata']);
        Route::get('/google/hubungkan', [GoogleAuthController::class, 'redirect'])->name('google.connect');
        Route::get('/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');
        Route::post('/google/putuskan', [GoogleAuthController::class, 'disconnect'])->name('google.disconnect');
    });
});
