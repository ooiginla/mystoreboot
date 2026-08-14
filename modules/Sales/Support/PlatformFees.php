<?php

declare(strict_types=1);

namespace Modules\Sales\Support;

use Illuminate\Support\Facades\DB;

/**
 * Resolves Storeboot's configurable fees from `global_configs` (tenant override falling back
 * to the platform default). Each fee is a percentage of the amount plus a fixed amount, in
 * minor units — the same shape as PAYMENT_GATEWAY_CHARGE.
 */
final class PlatformFees
{
    /** Storeboot's own fee charged on a wallet withdrawal (default zero). */
    public function transferFeeMinor(string $tenantId, int $amountMinor): int
    {
        return $this->feeMinor('STOREBOOT_TRANSFER_FEE', $tenantId, $amountMinor);
    }

    /** The Storeboot platform tenant (its own books), or null when not designated. */
    public function platformTenantId(): ?string
    {
        $raw = DB::table('global_configs')
            ->whereNull('tenant_id')
            ->where('key', 'PLATFORM_TENANT_ID')
            ->value('value');

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        $id = is_string($decoded) ? trim($decoded) : '';

        return $id !== '' ? $id : null;
    }

    private function feeMinor(string $key, string $tenantId, int $amountMinor): int
    {
        if ($amountMinor <= 0) {
            return 0;
        }

        $config = DB::table('global_configs')
            ->where('key', $key)
            ->where('tenant_id', $tenantId)
            ->value('value');

        $config ??= DB::table('global_configs')
            ->where('key', $key)
            ->whereNull('tenant_id')
            ->value('value');

        $values = is_string($config) && $config !== ''
            ? json_decode($config, true)
            : [];

        if (! is_array($values)) {
            $values = [];
        }

        $percentageRate = (float) ($values['percentage_rate'] ?? 0);
        $fixedAmountMinor = (int) ($values['fixed_amount_minor'] ?? 0);

        return max(0, (int) ceil($amountMinor * ($percentageRate / 100)) + $fixedAmountMinor);
    }
}
