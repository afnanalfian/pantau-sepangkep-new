<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class Settings
{
    public const DEFAULTS = [
        'default_monthly_target' => 100,   // poin / bulan (1 poin = 1 Jam Kerja Efektif)
        'sla_hours' => 48,                 // batas review ketua tim (jam)
        'sla_action' => 'escalate',        // escalate | auto_approve
        'reminder_hours' => 24,            // pengingat H-1 deadline
        'primary_pimpinan_id' => null,
        'office_name' => 'BPS Kabupaten Pangkajene dan Kepulauan',
        'require_output_on_submit' => true,
        'google_refresh_token' => null,
    ];

    public static function all(): array
    {
        return Cache::rememberForever('app_settings', function () {
            try {
                if (! Schema::hasTable('settings')) {
                    return self::DEFAULTS;
                }

                return array_merge(self::DEFAULTS, Setting::pluck('value', 'key')->all());
            } catch (\Throwable) {
                return self::DEFAULTS;
            }
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function set(string $key, mixed $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => is_bool($value) ? (int) $value : $value]);
        Cache::forget('app_settings');
    }
}
