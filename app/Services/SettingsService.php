<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class SettingsService
{
    public const CACHE_KEY = 'settings.rows';

    /**
     * Every known setting with its group, default value and whether it is stored encrypted.
     *
     * @return array<string, array{group: string, default: string, encrypted: bool}>
     */
    public static function definitions(): array
    {
        return [
            'store.name' => ['group' => 'store', 'default' => 'Our Store', 'encrypted' => false],
            'store.email' => ['group' => 'store', 'default' => '', 'encrypted' => false],
            'store.phone' => ['group' => 'store', 'default' => '', 'encrypted' => false],
            'store.address' => ['group' => 'store', 'default' => '', 'encrypted' => false],
            'store.gstin' => ['group' => 'store', 'default' => '', 'encrypted' => false],
            'store.state' => ['group' => 'store', 'default' => 'Maharashtra', 'encrypted' => false],
            'shipping.flat_rate_paise' => ['group' => 'shipping', 'default' => '4900', 'encrypted' => false],
            'shipping.free_threshold_paise' => ['group' => 'shipping', 'default' => '49900', 'encrypted' => false],
            'shipping.standard_days' => ['group' => 'shipping', 'default' => '5', 'encrypted' => false],
            'cod.enabled' => ['group' => 'cod', 'default' => '1', 'encrypted' => false],
            'cod.max_order_paise' => ['group' => 'cod', 'default' => '500000', 'encrypted' => false],
            'cod.products_eligible_by_default' => ['group' => 'cod', 'default' => '1', 'encrypted' => false],
            'razorpay.mode' => ['group' => 'razorpay', 'default' => 'test', 'encrypted' => false],
            'razorpay.key_id' => ['group' => 'razorpay', 'default' => '', 'encrypted' => false],
            'razorpay.key_secret' => ['group' => 'razorpay', 'default' => '', 'encrypted' => true],
            'razorpay.webhook_secret' => ['group' => 'razorpay', 'default' => '', 'encrypted' => true],
            'appearance.dark_mode' => ['group' => 'appearance', 'default' => '1', 'encrypted' => false],
            'auth.require_email_verification' => ['group' => 'auth', 'default' => '0', 'encrypted' => false],
            'activity.enabled' => ['group' => 'activity', 'default' => '0', 'encrypted' => false],
            'checkout.require_verified_email' => ['group' => 'checkout', 'default' => '0', 'encrypted' => false],
            'email.from_name' => ['group' => 'email', 'default' => 'Our Store', 'encrypted' => false],
            'email.from_address' => ['group' => 'email', 'default' => 'hello@example.com', 'encrypted' => false],
        ];
    }

    /**
     * Read a setting, decrypting it when needed. Falls back to the declared default.
     */
    public function get(string $key, ?string $default = null): ?string
    {
        $rows = $this->rows();

        if (! array_key_exists($key, $rows)) {
            return self::definitions()[$key]['default'] ?? $default;
        }

        $row = $rows[$key];

        return $row['encrypted'] && $row['value'] !== null
            ? Crypt::decryptString($row['value'])
            : $row['value'];
    }

    /**
     * Read a setting as an integer.
     */
    public function int(string $key): int
    {
        return (int) $this->get($key);
    }

    /**
     * Read a setting as a boolean.
     */
    public function bool(string $key): bool
    {
        return $this->get($key) === '1';
    }

    /**
     * Store a setting. Encrypted settings are encrypted before they reach the database.
     */
    public function set(string $key, ?string $value): void
    {
        $definition = self::definitions()[$key] ?? throw new \InvalidArgumentException("Unknown setting [{$key}].");

        Setting::query()->updateOrCreate(
            ['key' => $key],
            [
                'group' => $definition['group'],
                'is_encrypted' => $definition['encrypted'],
                'value' => $value,
            ],
        );

        $this->forget();
    }

    /**
     * Drop the cached settings so the next read hits the database.
     */
    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Cache raw (possibly encrypted) rows so plaintext secrets never sit in the cache table.
     *
     * @return array<string, array{value: ?string, encrypted: bool}>
     */
    private function rows(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            return Setting::query()
                ->get(['key', 'value', 'is_encrypted'])
                ->mapWithKeys(fn (Setting $setting): array => [
                    $setting->key => [
                        'value' => $setting->getRawOriginal('value'),
                        'encrypted' => (bool) $setting->is_encrypted,
                    ],
                ])
                ->all();
        });
    }
}
