<?php

declare(strict_types=1);

namespace Modules\Sales\Support;

use Modules\Tenancy\Models\Tenant;

/**
 * Per-tenant dine-in behaviour, stored in the tenant's existing `settings` JSON rather
 * than new columns — these are preferences, not relational data.
 */
final class DineInSettings
{
    /** Ingredients leave the store when the kitchen is sent the order. */
    public const DEPLETE_AT_FIRE = 'fire';

    /** Ingredients leave the store when the guest pays, matching counter sales. */
    public const DEPLETE_AT_SETTLE = 'settle';

    /**
     * When a dine-in order consumes its ingredients.
     *
     * Defaults to fire: stock is then true *during* service, so a manager asking "do we
     * have enough chicken?" at 8pm gets a real answer, and a table that walks out without
     * paying still records the food as used — because it was.
     */
    public static function depletionTiming(Tenant $tenant): string
    {
        $value = data_get($tenant->settings, 'dine_in.depletion', self::DEPLETE_AT_FIRE);

        return in_array($value, [self::DEPLETE_AT_FIRE, self::DEPLETE_AT_SETTLE], true)
            ? $value
            : self::DEPLETE_AT_FIRE;
    }

    public static function depletesAtFire(Tenant $tenant): bool
    {
        return self::depletionTiming($tenant) === self::DEPLETE_AT_FIRE;
    }

    public static function serviceChargeRate(Tenant $tenant): float
    {
        return (float) data_get($tenant->settings, 'dine_in.service_charge_rate', 0);
    }

    /**
     * Minutes after firing at which a kitchen ticket turns amber, then red. Generous
     * defaults; 6f exposes them in the UI.
     *
     * @return array{warn: int, late: int}
     */
    public static function kdsThresholds(Tenant $tenant): array
    {
        return [
            'warn' => (int) data_get($tenant->settings, 'dine_in.kds_warn_minutes', 8),
            'late' => (int) data_get($tenant->settings, 'dine_in.kds_late_minutes', 15),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function merge(Tenant $tenant, array $values): array
    {
        $settings = $tenant->settings ?? [];
        $settings['dine_in'] = array_merge($settings['dine_in'] ?? [], $values);

        return $settings;
    }
}
