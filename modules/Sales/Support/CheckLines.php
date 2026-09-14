<?php

declare(strict_types=1);

namespace Modules\Sales\Support;

use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Models\ProductVariant;
use Modules\Inventory\Support\Quantity;
use Modules\Sales\Models\SalesOrderItem;

/**
 * Small rules about lines on a restaurant check, shared by adding, voiding, splitting
 * and settling so they cannot drift apart.
 */
final class CheckLines
{
    /**
     * Physical goods counted in their own right — a bottle of wine, a can of malt. Not
     * recipe items, which consume ingredients instead, and not services.
     */
    public static function isStockTracked(?ProductVariant $variant): bool
    {
        $product = $variant?->product;

        return $product?->product_type === ProductType::Product
            && (bool) ($product->track_inventory ?? true)
            && ! $product->usesRecipeDepletion();
    }

    /**
     * Carve `$quantity` off a line into a new line on the same check: one of three beers
     * voided, or two of four moved to a split bill. Money and the cost already consumed
     * are shared out in proportion. The original keeps its kitchen-ticket link, so the
     * kitchen is not disturbed by what is really a billing decision.
     */
    public static function splitLine(SalesOrderItem $item, float $quantity): SalesOrderItem
    {
        $total = (float) $item->quantity;
        $quantity = Quantity::round($quantity);
        $share = $quantity / max(0.0001, $total);

        $taxMinor = (int) round((int) $item->tax_minor * $share);
        $lineMinor = (int) round((int) $item->line_total_minor * $share);
        $consumedMinor = (int) round((int) $item->consumed_cost_minor * $share);

        $clone = $item->replicate();
        $clone->fill([
            'quantity' => $quantity,
            'tax_minor' => $taxMinor,
            'line_total_minor' => $lineMinor,
            'consumed_cost_minor' => $consumedMinor,
        ]);
        $clone->save();

        foreach ($item->modifiers()->get() as $modifier) {
            $copy = $modifier->replicate();
            $copy->sales_order_item_id = $clone->id;
            $copy->save();
        }

        $item->update([
            'quantity' => Quantity::round($total - $quantity),
            'tax_minor' => max(0, (int) $item->tax_minor - $taxMinor),
            'line_total_minor' => max(0, (int) $item->line_total_minor - $lineMinor),
            'consumed_cost_minor' => max(0, (int) $item->consumed_cost_minor - $consumedMinor),
        ]);

        return $clone;
    }
}
