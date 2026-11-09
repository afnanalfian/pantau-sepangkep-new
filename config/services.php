<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Google Drive – penyimpanan lampiran tugas.
    | auth: oauth (refresh token akun kantor) | service_account
    */
    'google_drive' => [
        'enabled' => (bool) env('GOOGLE_DRIVE_ENABLED', false),
        'auth' => env('GOOGLE_DRIVE_AUTH', 'oauth'),
        'client_id' => env('GOOGLE_DRIVE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
        'refresh_token' => env('GOOGLE_DRIVE_REFRESH_TOKEN'),
        'service_account_json' => env('GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON') ? base_path(env('GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON')) : null,
        'impersonate' => env('GOOGLE_DRIVE_IMPERSONATE'),
        'parent_folder_id' => env('GOOGLE_DRIVE_PARENT_FOLDER_ID'),
        'root_folder_name' => env('GOOGLE_DRIVE_ROOT_FOLDER', 'PANTAU SEPANGKEP'),
        'share' => env('GOOGLE_DRIVE_SHARE', 'none'), // none | domain | anyone
        'share_domain' => env('GOOGLE_DRIVE_SHARE_DOMAIN'),
        'keep_local_copy' => (bool) env('GOOGLE_DRIVE_KEEP_LOCAL', false),
    ],

];
