<?php

namespace App\Notifications\Channels;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Mengirim Web Push (VAPID) ke semua perangkat yang didaftarkan pengguna.
 * Notifikasi tampil di HP/Laptop walaupun tab aplikasi ditutup.
 */
class WebPushChannel
{
    public static function configured(): bool
    {
        return filled(config('webpush.public_key')) && filled(config('webpush.private_key'));
    }

    public function send(User $notifiable, Notification $notification): void
    {
        if (! self::configured() || ! method_exists($notification, 'toWebPush')) {
            return;
        }
        $subs = $notifiable->pushSubscriptions()->get();
        if ($subs->isEmpty()) {
            return;
        }

        self::deliver($subs, $notification->toWebPush($notifiable));
    }

    /** @param \Illuminate\Support\Collection<int, PushSubscription> $subs */
    public static function deliver($subs, array $payload): array
    {
        $results = ['sent' => 0, 'failed' => 0];
        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => config('webpush.subject'),
                    'publicKey' => config('webpush.public_key'),
                    'privateKey' => config('webpush.private_key'),
                ],
            ], ['TTL' => 86400, 'urgency' => 'high'], logger: Log::channel(config('logging.default')));
            $webPush->setReuseVAPIDHeaders(true);

            foreach ($subs as $sub) {
                $webPush->queueNotification(Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'publicKey' => $sub->public_key,
                    'authToken' => $sub->auth_token,
                    'contentEncoding' => $sub->content_encoding ?: 'aes128gcm',
                ]), json_encode($payload));
            }

            foreach ($webPush->flush() as $report) {
                if ($report->isSuccess()) {
                    $results['sent']++;

                    continue;
                }
                $results['failed']++;
                if ($report->isSubscriptionExpired()) {
                    PushSubscription::where('endpoint_hash', hash('sha256', $report->getEndpoint()))->delete();
                } else {
                    Log::warning('WebPush gagal: '.$report->getReason());
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $results;
    }
}
