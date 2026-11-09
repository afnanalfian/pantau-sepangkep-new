<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function logout(Request $request)
    {
        // Hapus langganan push perangkat ini agar tidak menerima notifikasi setelah logout
        if ($endpoint = $request->input('endpoint')) {
            $request->user()?->pushSubscriptions()->where('endpoint_hash', hash('sha256', $endpoint))->delete();
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
