<?php

return [
    // Buat kunci dengan: php artisan pantau:vapid
    'public_key' => env('VAPID_PUBLIC_KEY'),
    'private_key' => env('VAPID_PRIVATE_KEY'),
    'subject' => env('VAPID_SUBJECT', 'mailto:ipds7310@bps.go.id'),
];
