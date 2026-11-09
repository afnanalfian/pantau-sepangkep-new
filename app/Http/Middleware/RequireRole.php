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
            default => false,
        };
        abort_unless($ok, 403, 'Halaman ini hanya untuk '.($role === 'monitor' ? 'pimpinan / ketua tim' : $role).'.');

        return $next($request);
    }
}
