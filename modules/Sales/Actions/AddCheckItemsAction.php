<?php

declare(strict_types=1);

namespace Modules\Sales\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Models\ProductVariant;
use Modules\Inventory\Support\LocationResolver;
use Modules\Inventory\Support\Quantity;
use Modules\Sales\Models\ModifierOption;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Support\CheckLines;

/**
 * Adds a round of items to an open check. Items land on the pad unfired — nothing has
 * reached the kitchen, so they can still be changed or removed freely.
 *
 * Prices come from the catalogue, not from the caller: a server should never be able to
 * type their own price into a bill.
 */
final class AddCheckItemsAction
{
    public function __construct(
        private readonly RecalculateCheckTotalsAction $totals,
        private readonly LocationResolver $locations,
    ) {}

    /**
     * @param  array<int, array{product_variant_id: int|string, quantity: float|int|string, course?: int, seat_number?: int|null, modifiers?: array<int, int|string>}>  $lines
     */
    public function execute(SalesOrder $check, array $lines): SalesOrder
    {
        if (! $check->isCheck() || ! $check->check_status?->isOpen()) {
            throw ValidationException::withMessages([
                'items' => 'This check is no longer open.',
            ]);
        }

        return DB::transaction(function () use ($check, $lines): SalesOrder {
            foreach ($lines as $line) {
                $quantity = Quantity::round((float) ($line['quantity'] ?? 0));

                if ($quantity <= 0) {
                    continue;
                }

                $variant = ProductVariant::query()
                    ->with(['product.modifierGroups' => fn ($q) => $q->where('status', 'active')->with('options')])
                    ->where('tenant_id', $check->tenant_id)
                    ->findOrFail((int) $line['product_variant_id']);

                $type = $variant->product?->product_type;

                if ($type instanceof ProductType && ! $type->sellable()) {
                    throw ValidationException::withMessages([
                        'items' => ($variant->product?->name ?? 'That item').' cannot be sold.',
                    ]);
                }

                $chosen = $this->resolveModifiers($variant, (array) ($line['modifiers'] ?? []));
                // The price is the catalogue price plus what the choices add or take off —
                // still never typed in by the server.
                $unitPriceMinor = max(0, (int) ($variant->discount_price_minor ?: $variant->selling_price_minor ?: 0)
                    + (int) $chosen->sum('price_delta_minor'));
                $modifierKey = $chosen->isEmpty() ? null : $chosen->pluck('id')->sort()->implode(',');
                $taxRate = (float) ($variant->tax_rate ?? $variant->product?->tax_rate ?? 0);
                $course = max(1, (int) ($line['course'] ?? 1));
                $seat = ! empty($line['seat_number']) ? (int) $line['seat_number'] : null;

                // Tapping the same item twice should read "2 ×", not stack two identical
                // rows. Only unsent lines with the same choices merge — a sent line is
                // already being cooked, and "no onions" is not the same dish as the plain one.
                $existing = $check->items()
                    ->where('product_variant_id', $variant->id)
                    ->where('course', $course)
                    ->whereNull('fired_at')
                    ->whereNull('voided_at')
                    ->when($seat === null, fn ($q) => $q->whereNull('seat_number'))
                    ->when($seat !== null, fn ($q) => $q->where('seat_number', $seat))
                    ->when($modifierKey === null, fn ($q) => $q->whereNull('modifier_key'))
                    ->when($modifierKey !== null, fn ($q) => $q->where('modifier_key', $modifierKey))
                    ->first();

                if ($existing) {
                    $merged = Quantity::round((float) $existing->quantity + $quantity);
                    $mergedSubtotal = (int) round($merged * $unitPriceMinor);

                    $existing->update([
                        'quantity' => $merged,
                        'tax_minor' => (int) round($mergedSubtotal * ($taxRate / 100)),
                        'line_total_minor' => $mergedSubtotal + (int) round($mergedSubtotal * ($taxRate / 100)),
                    ]);

                    continue;
                }

                $lineSubtotalMinor = (int) round($quantity * $unitPriceMinor);

                $created = $check->items()->create([
                    'tenant_id' => $check->tenant_id,
                    'product_variant_id' => $variant->id,
                    // Resolved per line, not per check: an item made at the Pool Bar
                    // depletes the Pool Bar's shelf even though the check belongs to a
                    // service area whose fallback store is somewhere else.
                    'inventory_location_id' => $this->locations->resolveForLine(
                        null,
                        $variant,
                        null,
                    ) ?? $check->inventory_location_id,
                    'item_name' => $this->itemName($variant),
                    'sku' => $variant->sku,
                    'quantity' => $quantity,
                    'course' => $course,
                    'seat_number' => $seat,
                    'modifier_key' => $modifierKey,
                    'unit_price_minor' => $unitPriceMinor,
                    'unit_cost_minor' => (int) ($variant->cost_price_minor ?: 0),
                    'tax_minor' => (int) round($lineSubtotalMinor * ($taxRate / 100)),
                    'line_total_minor' => $lineSubtotalMinor + (int) round($lineSubtotalMinor * ($taxRate / 100)),
                    // Settling reads this: a bottle of wine leaves its shelf when the bill
                    // is paid; a recipe item consumes ingredients instead.
                    'inventory_tracked' => CheckLines::isStockTracked($variant),
                ]);

                foreach ($chosen as $option) {
                    $created->modifiers()->create([
                        'tenant_id' => $check->tenant_id,
                        'modifier_option_id' => $option->id,
                        'group_name' => $option->group?->name ?? '',
                        'option_name' => $option->name,
                        'price_delta_minor' => (int) $option->price_delta_minor,
                        'component_product_variant_id' => $option->component_product_variant_id,
                        'component_quantity' => $option->component_quantity,
                        'component_unit_id' => $option->component_unit_id,
                    ]);
                }
            }

            // A new round after the bill was printed makes that bill wrong.
            $check->reopenIfBilled();

            return $this->totals->execute($check);
        });
    }

    /**
     * The options chosen for one item, checked against the groups that item offers:
     * required groups must be answered, limits respected, and nothing from another
     * item's menu slipped in.
     *
     * @param  array<int, int|string>  $optionIds
     * @return Collection<int, ModifierOption>
     */
    private function resolveModifiers(ProductVariant $variant, array $optionIds): Collection
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $optionIds))));
        $groups = $variant->product?->modifierGroups ?? collect();
        $name = $variant->product?->name ?? 'That item';
        $chosen = collect();

        foreach ($groups as $group) {
            $picked = $group->options->whereIn('id', $ids)->values();
            $min = $group->minimumChoices();

            if ($picked->count() < $min) {
                throw ValidationException::withMessages([
                    'items' => "{$name}: choose ".($min === 1 ? 'an option' : "at least {$min} options")." for {$group->name}.",
                ]);
            }

            if ($group->max_select !== null && $picked->count() > $group->max_select) {
                throw ValidationException::withMessages([
                    'items' => "{$name}: choose no more than {$group->max_select} for {$group->name}.",
                ]);
            }

            $picked->each(fn (ModifierOption $option) => $option->setRelation('group', $group));
            $chosen = $chosen->merge($picked);
        }

        if ($chosen->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'items' => "Some of those choices are not offered with {$name}.",
            ]);
        }

        return $chosen;
    }

    /**
     * What the kitchen and the guest read. A placeholder variant name adds nothing —
     * "Suya Default" on a ticket is noise a cook has to look past.
     */
    private function itemName(ProductVariant $variant): string
    {
        $product = trim((string) ($variant->product?->name ?? 'Item'));
        $variantName = trim((string) ($variant->variant_name ?? ''));

        // A placeholder variant, or one that just repeats the product name, adds nothing.
        if ($variantName === ''
            || strcasecmp($variantName, 'default') === 0
            || strcasecmp($variantName, $product) === 0) {
            return $product;
        }

        return $product.' — '.$variantName;
    }
}
