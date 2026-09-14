<?php

declare(strict_types=1);

namespace Modules\Inventory\Support;

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
}
