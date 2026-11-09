<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * - Akun nonaktif / terhapus langsung dikeluarkan.
 * - Akun baru wajib mengganti password sebelum memakai aplikasi.
 * - Mencatat "terakhir dilihat" (maks. 1x per 5 menit).
 */
class EnsureAccountReady
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        if (! $user->is_active || $user->trashed()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'Akun Anda sudah dinonaktifkan. Hubungi administrator.');
        }

        if ($user->must_change_password && ! $request->routeIs('password.change', 'logout', 'livewire.*')) {
            return redirect()->route('password.change');
        }

        if (! $user->last_seen_at || $user->last_seen_at->lt(now()->subMinutes(5))) {
            $user->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
