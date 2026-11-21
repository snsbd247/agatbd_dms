<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Key/value settings edited from the app (Settings → Email & SMS).
 * Keys listed in SECRET are stored encrypted with APP_KEY.
 */
class AppSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public const SECRET = ['mail_password', 'sms_api_key'];

    /** @var array<string, string|null>|null */
    private static ?array $cache = null;

    /**
     * @return array<string, string|null>
     */
    public static function cached(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        try {
            $rows = static::query()->pluck('value', 'key')->all();
        } catch (\Throwable) {
            return self::$cache = [];   // table not migrated yet
        }

        foreach (self::SECRET as $key) {
            if (! empty($rows[$key])) {
                try {
                    $rows[$key] = Crypt::decryptString($rows[$key]);
                } catch (\Throwable) {
                    $rows[$key] = null;  // APP_KEY changed; secret must be entered again
                }
            }
        }

        return self::$cache = $rows;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = self::cached()[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }

    public static function flag(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        return $value === null ? $default : $value === '1';
    }

    /**
     * @param  array<string, string|bool|int|null>  $values
     */
    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }

            if (in_array($key, self::SECRET, true) && $value !== null && $value !== '') {
                $value = Crypt::encryptString((string) $value);
            }

            static::updateOrCreate(['key' => $key], ['value' => $value === null ? null : (string) $value]);
        }

        self::$cache = null;
    }
}
