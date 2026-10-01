<?php

namespace App\Services;

use App\Models\MarketplaceSetting;

class SettingsService
{
    public function get(string $key, mixed $default = null): mixed
    {
        $setting = MarketplaceSetting::query()->where('key', $key)->first();
        if (! $setting) {
            return $default;
        }

        return match ($setting->type) {
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $setting->value,
            'json' => json_decode((string) $setting->value, true),
            default => $setting->value,
        };
    }

    public function set(string $key, mixed $value, string $type = 'string', bool $isPublic = false): MarketplaceSetting
    {
        $stored = $type === 'json' ? json_encode($value, JSON_THROW_ON_ERROR) : (string) $value;

        return MarketplaceSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $stored, 'type' => $type, 'is_public' => $isPublic],
        );
    }

    public function minimumBidCents(): int
    {
        return max(100, (int) $this->get('minimum_bid_cents', config('marketplace.minimum_bid_cents')));
    }

    public function minimumTopUpCents(): int
    {
        return max(100, (int) config('marketplace.minimum_top_up_cents'));
    }

    public function marketOpen(): bool
    {
        return (bool) config('marketplace.legal_ready') && (bool) config('marketplace.market_open') && (bool) $this->get('market_open', false);
    }
}
