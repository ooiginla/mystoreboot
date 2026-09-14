<?php

declare(strict_types=1);

namespace Modules\Inventory\Support;

use InvalidArgumentException;
use Modules\Inventory\Models\UnitOfMeasure;

/**
 * The single source of truth for turning a quantity expressed in one unit into
 * another. Universal conversions (kg↔g, L↔ml) go through each unit's
 * `to_base_factor`; product-specific packs (a carton that means 24 of this item)
 * use the variant's own factor via {@see purchaseToBase()}.
 *
 * Quantities are rounded to 4 decimal places at the boundary — the project-wide
 * rounding discipline for physical quantities (money is handled separately, in
 * minor units).
 */
final class UnitConverter
{
    public const QTY_SCALE = 4;

    /**
     * Convert a quantity between two units of the same dimension.
     */
    public function convert(float $quantity, UnitOfMeasure|int $from, UnitOfMeasure|int $to): float
    {
        $from = $this->resolve($from);
        $to = $this->resolve($to);

        if ($from->id === $to->id) {
            return $this->round($quantity);
        }

        if ($from->dimension !== $to->dimension) {
            throw new InvalidArgumentException(
                "Cannot convert {$from->code} to {$to->code}: different dimensions.",
            );
        }

        if (! $from->isConvertible() || ! $to->isConvertible()) {
            throw new InvalidArgumentException(
                "Cannot convert {$from->code} to {$to->code}: one is a product-specific unit (use a per-product pack size).",
            );
        }

        $inBase = $quantity * (float) $from->to_base_factor;

        return $this->round($inBase / (float) $to->to_base_factor);
    }

    /**
     * Express a quantity in a unit's base unit (e.g. 1.5 kg → 1500 g).
     */
    public function toBase(float $quantity, UnitOfMeasure|int $unit): float
    {
        $unit = $this->resolve($unit);

        if (! $unit->isConvertible()) {
            throw new InvalidArgumentException(
                "Unit {$unit->code} has no universal base factor; use a per-product pack size.",
            );
        }

        return $this->round($quantity * (float) $unit->to_base_factor);
    }

    /**
     * Convert a product-specific purchase quantity into base units using the
     * variant's own pack factor (e.g. 2 cartons × 24 = 48 base units).
     */
    public function purchaseToBase(float $purchaseQuantity, float $purchaseToBaseFactor): float
    {
        return $this->round($purchaseQuantity * $purchaseToBaseFactor);
    }

    public function round(float $quantity): float
    {
        return round($quantity, self::QTY_SCALE);
    }

    private function resolve(UnitOfMeasure|int $unit): UnitOfMeasure
    {
        return $unit instanceof UnitOfMeasure
            ? $unit
            : UnitOfMeasure::query()->findOrFail($unit);
    }
}
