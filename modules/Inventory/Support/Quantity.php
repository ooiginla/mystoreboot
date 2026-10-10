<?php

declare(strict_types=1);

namespace Modules\Inventory\Support;

use Illuminate\Support\Str;
use Modules\Inventory\Models\UnitOfMeasure;

/**
 * Physical-quantity helpers. Quantities are decimals rounded to 4 dp (the
 * project-wide discipline); money stays in integer minor units and is rounded
 * separately at the cost boundary. Use {@see format()} for display so whole numbers
 * show as "12" and fractional ones as "1.5" — never "12.0000".
 */
final class Quantity
{
    public const SCALE = 4;

    public static function round(float|int|string $quantity): float
    {
        return round((float) $quantity, self::SCALE);
    }

    public static function format(float|int|string $quantity): string
    {
        $formatted = number_format((float) $quantity, self::SCALE, '.', ',');

        return str_contains($formatted, '.')
            ? rtrim(rtrim($formatted, '0'), '.')
            : $formatted;
    }

    /**
     * Describe a base-unit quantity using the largest convertible units first.
     *
     * For example, units of kg=1, hb=50 and bag=100 render 375 kg as
     * "3 bags, 1 hb, 25 kg".
     *
     * @param  iterable<int, UnitOfMeasure>  $units
     */
    public static function describeInUnits(float|int|string $quantity, iterable $units, ?UnitOfMeasure $baseUnit): string
    {
        $baseCode = self::displayCode($baseUnit?->code ?? 'pc');
        $fallback = self::format($quantity).' '.$baseCode;

        if (! $baseUnit?->isConvertible() || (float) $baseUnit->to_base_factor <= 0) {
            return $fallback;
        }

        $convertible = collect($units)
            ->filter(fn (mixed $unit): bool => $unit instanceof UnitOfMeasure
                && $unit->isConvertible()
                && (float) $unit->to_base_factor > 0
                && $unit->unit_category_id === $baseUnit->unit_category_id
                && $unit->dimension === $baseUnit->dimension)
            ->sortByDesc(fn (UnitOfMeasure $unit): float => (float) $unit->to_base_factor)
            ->unique(fn (UnitOfMeasure $unit): string => (string) $unit->to_base_factor)
            ->values();

        if ($convertible->isEmpty()) {
            return $fallback;
        }

        $negative = (float) $quantity < 0;
        $remaining = abs((float) $quantity) * (float) $baseUnit->to_base_factor;
        $parts = [];

        foreach ($convertible as $index => $unit) {
            $factor = (float) $unit->to_base_factor;
            $isSmallest = $index === $convertible->count() - 1;
            $amount = $isSmallest
                ? self::round($remaining / $factor)
                : floor(($remaining + 0.0000001) / $factor);

            if ($amount <= 0 && ! ($isSmallest && $parts === [])) {
                continue;
            }

            $code = self::displayCode($unit->code);
            if ($amount !== 1.0 && strlen($code) > 2) {
                $code = Str::plural($code);
            }

            $parts[] = self::format($amount).' '.$code;
            $remaining = max(0.0, self::round($remaining - ($amount * $factor)));
        }

        $description = implode(', ', $parts);

        return $negative ? '-'.$description : $description;
    }

    private static function displayCode(string $code): string
    {
        return $code === 'ea' ? 'pc' : $code;
    }
}
