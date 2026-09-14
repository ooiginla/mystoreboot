<?php

declare(strict_types=1);

namespace Modules\Sales\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Inventory\Models\UnitOfMeasure;
use Modules\Inventory\Support\Quantity;
use Modules\Sales\Models\ModifierGroup;

/**
 * Creates or updates a modifier group with its options and the menu items it is
 * offered on, in one save. Options removed from the form are deleted; lines already on
 * bills keep their own copy, so old bills are unaffected.
 */
final class SaveModifierGroupAction
{
    /**
     * @param  array<string, mixed>  $data  name, is_required, min_select, max_select,
     *                                      options[] {id?, name, price, component_product_variant_id?, component_quantity?, component_unit_id?},
     *                                      product_ids[]
     */
    public function execute(string $tenantId, array $data, ?ModifierGroup $group = null): ModifierGroup
    {
        $options = collect((array) ($data['options'] ?? []))
            ->filter(fn ($row): bool => is_array($row) && trim((string) ($row['name'] ?? '')) !== '')
            ->values();

        if ($options->isEmpty()) {
            throw ValidationException::withMessages(['options' => 'Add at least one option, for example "Mild" or "Extra chicken".']);
        }

        $isRequired = (bool) ($data['is_required'] ?? false);
        $min = max(0, (int) ($data['min_select'] ?? 0));
        $max = ($data['max_select'] ?? '') === '' || $data['max_select'] === null ? null : max(1, (int) $data['max_select']);

        if ($max !== null && max($min, $isRequired ? 1 : 0) > $max) {
            throw ValidationException::withMessages(['max_select' => 'The most a guest can pick cannot be lower than the fewest they must pick.']);
        }

        $variantIds = ProductVariant::query()->where('tenant_id', $tenantId)
            ->whereIn('id', $options->pluck('component_product_variant_id')->filter()->map(fn ($id) => (int) $id)->all())
            ->pluck('id')->flip();
        $unitIds = UnitOfMeasure::query()->where('tenant_id', $tenantId)
            ->whereIn('id', $options->pluck('component_unit_id')->filter()->map(fn ($id) => (int) $id)->all())
            ->pluck('id')->flip();
        $productIds = Product::query()->where('tenant_id', $tenantId)
            ->whereIn('id', array_map('intval', (array) ($data['product_ids'] ?? [])))
            ->pluck('id')->all();

        return DB::transaction(function () use ($tenantId, $data, $group, $options, $isRequired, $min, $max, $variantIds, $unitIds, $productIds): ModifierGroup {
            $group ??= new ModifierGroup(['tenant_id' => $tenantId]);
            $group->fill([
                'name' => trim((string) $data['name']),
                'is_required' => $isRequired,
                'min_select' => $min,
                'max_select' => $max,
                'status' => 'active',
            ])->save();

            $keep = [];

            foreach ($options as $index => $row) {
                $variantId = ! empty($row['component_product_variant_id']) && $variantIds->has((int) $row['component_product_variant_id'])
                    ? (int) $row['component_product_variant_id']
                    : null;

                $attributes = [
                    'tenant_id' => $tenantId,
                    'name' => trim((string) $row['name']),
                    'price_delta_minor' => $this->moneyToMinor($row['price'] ?? 0),
                    'component_product_variant_id' => $variantId,
                    'component_quantity' => $variantId ? Quantity::round((float) ($row['component_quantity'] ?? 0)) : 0,
                    'component_unit_id' => $variantId && ! empty($row['component_unit_id']) && $unitIds->has((int) $row['component_unit_id'])
                        ? (int) $row['component_unit_id']
                        : null,
                    'sort_order' => $index,
                ];

                $existing = ! empty($row['id']) ? $group->options()->find((int) $row['id']) : null;

                if ($existing) {
                    $existing->update($attributes);
                    $keep[] = $existing->id;
                } else {
                    $keep[] = $group->options()->create($attributes)->id;
                }
            }

            $group->options()->whereNotIn('id', $keep)->delete();

            $group->products()->sync(
                collect($productIds)->mapWithKeys(fn (int $id): array => [$id => ['tenant_id' => $tenantId]])->all(),
            );

            return $group->load(['options', 'products']);
        });
    }

    /** Signed: "-200" takes money off, "1,500" adds it. */
    private function moneyToMinor(mixed $value): int
    {
        return (int) round((float) str_replace(',', '', (string) ($value ?? 0)) * 100);
    }
}
