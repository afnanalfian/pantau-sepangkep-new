<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateVapidKeys extends Command
{
    protected $signature = 'pantau:vapid {--write : Tulis langsung ke file .env}';

    protected $description = 'Membuat pasangan kunci VAPID untuk Web Push (notifikasi HP)';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();
        $lines = "VAPID_PUBLIC_KEY={$keys['publicKey']}\nVAPID_PRIVATE_KEY={$keys['privateKey']}";

        if ($this->option('write')) {
            $env = base_path('.env');
            $content = file_exists($env) ? file_get_contents($env) : '';
            if (preg_match('/^VAPID_PUBLIC_KEY=.+$/m', $content)) {
                $this->warn('VAPID sudah ada di .env – tidak ditimpa (mengganti kunci akan memutus langganan push yang ada).');

                return self::SUCCESS;
            }
            $content = preg_replace('/^VAPID_(PUBLIC|PRIVATE)_KEY=.*\n?/m', '', $content);
            file_put_contents($env, rtrim($content)."\n\n".$lines."\n");
            $this->info('Kunci VAPID ditulis ke .env');
        } else {
            $this->line($lines);
            $this->comment('Salin dua baris di atas ke file .env, lalu jalankan: php artisan config:cache');
        }

        return self::SUCCESS;
    }
}
