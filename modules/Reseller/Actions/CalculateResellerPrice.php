<?php

declare(strict_types=1);

namespace Modules\Reseller\Actions;

use InvalidArgumentException;
use Modules\Reseller\Enums\PricingMode;
use Modules\Reseller\Models\ResellerSetting;

final class CalculateResellerPrice
{
    public function execute(int $sourcePriceMinor, ResellerSetting $settings): int
    {
        if ($sourcePriceMinor < 0) {
            throw new InvalidArgumentException('Source price cannot be negative.');
        }

        $percentageAddition = in_array($settings->pricing_mode, [PricingMode::Percentage, PricingMode::Combined], true)
            ? (int) round($sourcePriceMinor * $settings->percentage_markup_basis_points / 10000)
            : 0;
        $fixedAddition = in_array($settings->pricing_mode, [PricingMode::Fixed, PricingMode::Combined], true)
            ? $settings->fixed_markup_minor
            : 0;

        return $sourcePriceMinor + $percentageAddition + $fixedAddition;
    }
}
