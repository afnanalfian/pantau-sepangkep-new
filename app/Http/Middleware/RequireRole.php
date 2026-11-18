<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    /** Pemakaian: ->middleware('role:admin') | 'role:monitor' | 'role:pimpinan' */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $u = $request->user();
        $ok = match ($role) {
            'admin' => $u?->is_admin,
            'pimpinan' => $u?->is_pimpinan || $u?->is_admin,
            'monitor' => $u?->canMonitor(),
            'presensi' => $u?->canPresensi(),
            'matriks' => $u?->canMatriks(),
            default => false,
        };
        $who = ['monitor' => 'pimpinan / ketua tim', 'presensi' => 'petugas presensi', 'matriks' => 'pimpinan, ketua tim, dan keuangan'][$role] ?? $role;
        abort_unless($ok, 403, 'Halaman ini hanya untuk '.$who.'.');

        return $next($request);
    }
}
