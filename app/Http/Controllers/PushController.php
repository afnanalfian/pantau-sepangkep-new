<?php

namespace App\Http\Controllers;

use App\Notifications\Channels\WebPushChannel;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PushController extends Controller
{
    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'endpoint' => 'required|url|max:2000',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
            'contentEncoding' => 'nullable|string|max:20',
        ]);

        $request->user()->pushSubscriptions()->updateOrCreate(
            ['endpoint_hash' => hash('sha256', $data['endpoint'])],
            [
                'endpoint' => $data['endpoint'],
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
                'device' => Str::limit($this->device($request->userAgent()), 190),
            ]
        );

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request)
    {
        $request->validate(['endpoint' => 'required|string']);
        $request->user()->pushSubscriptions()->where('endpoint_hash', hash('sha256', $request->input('endpoint')))->delete();

        return response()->json(['ok' => true]);
    }

    public function test(Request $request)
    {
        $subs = $request->user()->pushSubscriptions()->get();
        if ($subs->isEmpty()) {
            return response()->json(['ok' => false, 'message' => 'Belum ada perangkat terdaftar.'], 422);
        }
        $result = WebPushChannel::deliver($subs, [
            'title' => 'Tes notifikasi berhasil 🎉',
            'body' => 'Notifikasi PANTAU SEPANGKEP akan muncul di perangkat ini.',
            'url' => route('notifications'),
            'tag' => 'pantau-test',
            'icon' => asset('icons/icon-192.png'),
            'badge' => asset('icons/badge-72.png'),
        ]);

        return response()->json(['ok' => $result['sent'] > 0] + $result);
    }

    private function device(?string $ua): string
    {
        $ua = (string) $ua;
        $os = match (true) {
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Perangkat',
        };
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') => 'Opera',
            str_contains($ua, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($ua, 'Firefox') => 'Firefox',
            str_contains($ua, 'Chrome') => 'Chrome',
            str_contains($ua, 'Safari') => 'Safari',
            default => 'Browser',
        };

        return $browser.' · '.$os;
    }
}
