<?php

declare(strict_types=1);

namespace Modules\Reseller\Support;

use Carbon\CarbonInterface;
use Modules\Reseller\Models\ResellerSetting;
use Modules\Reseller\Models\ResellerSupplier;

final class SupplierScanSchedule
{
    public function frequency(ResellerSupplier $supplier, ResellerSetting $settings): string
    {
        return $supplier->scan_frequency ?: $settings->scan_frequency;
    }

    public function intervalHours(ResellerSupplier $supplier, ResellerSetting $settings): int
    {
        return $this->frequency($supplier, $settings) === 'twice_daily' ? 12 : 24;
    }

    public function isDue(ResellerSupplier $supplier, ResellerSetting $settings, ?CarbonInterface $at = null): bool
    {
        $at ??= now();

        return ! $supplier->last_scanned_at
            || $supplier->last_scanned_at->copy()->addHours($this->intervalHours($supplier, $settings))->lessThanOrEqualTo($at);
    }

    public function nextDispatchAt(ResellerSupplier $supplier, ResellerSetting $settings, ?CarbonInterface $at = null): ?CarbonInterface
    {
        if (! $supplier->is_active) {
            return null;
        }

        $at ??= now();
        $dueAt = $supplier->last_scanned_at
            ? $supplier->last_scanned_at->copy()->addHours($this->intervalHours($supplier, $settings))
            : $at->copy();

        if ($dueAt->lessThanOrEqualTo($at)) {
            return $at->copy()->startOfHour()->addHour();
        }

        $dispatchAt = $dueAt->copy()->startOfHour();

        return $dispatchAt->lessThan($dueAt) ? $dispatchAt->addHour() : $dispatchAt;
    }
}
