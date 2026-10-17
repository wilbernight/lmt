<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Site-wide settings managed from the admin panel. */
class Setting extends Model
{
    public const DEFAULTS = [
        'registration_open' => true,
        'default_storage_limit' => 2 * 1024 * 1024 * 1024,
        'default_max_workspaces' => 5,
        'max_upload_mb' => 20,
        // Letterhead printed on official letters.
        'company_name' => 'WilberNight',
        'company_tagline' => 'خدمات میزبانی وب و زیرساخت ابری',
        'company_address' => '',
        'company_phone' => '',
        'company_email' => '',
        'company_website' => 'wilbernight.net',
    ];

    public const LETTERHEAD_KEYS = ['company_name', 'company_tagline', 'company_address', 'company_phone', 'company_email', 'company_website'];

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    public static function get(string $key): mixed
    {
        return static::values()[$key] ?? self::DEFAULTS[$key] ?? null;
    }

    public static function values(): array
    {
        return Cache::rememberForever('site-settings', fn () => array_merge(
            self::DEFAULTS,
            static::query()->pluck('value', 'key')->all(),
        ));
    }

    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            if (array_key_exists($key, self::DEFAULTS)) {
                static::updateOrCreate(['key' => $key], ['value' => $value]);
            }
        }

        Cache::forget('site-settings');
    }
}
