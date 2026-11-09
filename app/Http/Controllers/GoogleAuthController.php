<?php

namespace App\Http\Controllers;

use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Menghubungkan akun Google kantor (OAuth) untuk penyimpanan Google Drive.
 * Refresh token disimpan terenkripsi di tabel settings – tidak perlu menyalin token manual.
 */
class GoogleAuthController extends Controller
{
    public function redirect(Request $request)
    {
        $clientId = config('services.google_drive.client_id');
        abort_unless($clientId && config('services.google_drive.client_secret'), 422,
            'Isi GOOGLE_DRIVE_CLIENT_ID dan GOOGLE_DRIVE_CLIENT_SECRET di file .env terlebih dahulu.');

        $state = Str::random(40);
        $request->session()->put('google_oauth_state', $state);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => route('admin.google.callback'),
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/drive',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]));
    }

    public function callback(Request $request)
    {
        abort_unless($request->input('state') === $request->session()->pull('google_oauth_state'), 419, 'Sesi otorisasi kedaluwarsa, ulangi.');

        if ($request->filled('error')) {
            return redirect()->route('admin.settings')->with('toast', 'Otorisasi Google dibatalkan: '.$request->input('error'));
        }

        $res = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $request->input('code'),
            'client_id' => config('services.google_drive.client_id'),
            'client_secret' => config('services.google_drive.client_secret'),
            'redirect_uri' => route('admin.google.callback'),
            'grant_type' => 'authorization_code',
        ]);

        if (! $res->successful() || ! $res->json('refresh_token')) {
            return redirect()->route('admin.settings')->with('toast', 'Gagal mendapatkan token Google: '.Str::limit($res->body(), 200));
        }

        Settings::set('google_refresh_token', encrypt($res->json('refresh_token')));
        Cache::forget('gdrive_access_token');

        return redirect()->route('admin.settings')->with('toast', 'Akun Google berhasil terhubung. Lampiran baru akan tersimpan ke Google Drive.');
    }

    public function disconnect()
    {
        Settings::set('google_refresh_token', null);
        Cache::forget('gdrive_access_token');

        return redirect()->route('admin.settings')->with('toast', 'Akun Google diputus.');
    }
}
