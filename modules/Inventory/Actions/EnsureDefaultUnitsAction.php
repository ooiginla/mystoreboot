<?php

declare(strict_types=1);

namespace Modules\Inventory\Actions;

use Illuminate\Support\Collection;
use Modules\Inventory\Enums\UnitDimension;
use Modules\Inventory\Models\UnitOfMeasure;
use Modules\Tenancy\Models\Tenant;

/**
 * Seeds a tenant's starter set of units of measure. Idempotent — safe to call on
 * every registration and to re-run for backfills. "Piece" (pc) is the default base
 * unit for ordinary retail products, so a basic tenant that never touches the F&B
 * module still has a sensible, invisible default.
 */
final class EnsureDefaultUnitsAction
{
    /**
     * code => [name, dimension, to_base_factor|null, is_base_for_dimension]
     *
     * @var array<string, array{0: string, 1: UnitDimension, 2: float|null, 3: bool}>
     */
    private const DEFAULTS = [
        'pc' => ['Piece', UnitDimension::Count, 1.0, true],
        'g' => ['Gram', UnitDimension::Weight, 1.0, true],
        'kg' => ['Kilogram', UnitDimension::Weight, 1000.0, false],
        'ml' => ['Millilitre', UnitDimension::Volume, 1.0, true],
        'L' => ['Litre', UnitDimension::Volume, 1000.0, false],
        // Product-specific packs: no universal factor — the variant supplies its own.
        'pack' => ['Pack', UnitDimension::Count, null, false],
        'carton' => ['Carton', UnitDimension::Count, null, false],
        'box' => ['Box', UnitDimension::Count, null, false],
    ];

    /**
     * @return Collection<int, UnitOfMeasure>
     */
    public function forTenant(Tenant|string $tenant): Collection
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        $category = \Modules\Inventory\Models\UnitCategory::query()->firstOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'General'],
            ['is_default' => true],
        );

        return collect(self::DEFAULTS)->map(function (array $spec, string $code) use ($tenantId, $category): UnitOfMeasure {
            [$name, $dimension, $factor, $isBase] = $spec;

            // A business that had already made its own "pc" kept the default count unit
            // on its old code "ea" when it was renamed to Piece. That row is still its
            // count base, so a second one is never created alongside it.
            if ($code === 'pc') {
                $legacy = UnitOfMeasure::query()->where('tenant_id', $tenantId)->where('code', 'ea')->first();

                if ($legacy) {
                    return $legacy;
                }
            }

            return UnitOfMeasure::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'code' => $code],
                [
                    'unit_category_id' => $category->id,
                    'name' => $name,
                    'dimension' => $dimension->value,
                    'to_base_factor' => $factor,
                    'is_base_for_dimension' => $isBase,
                    'status' => 'active',
                ],
            );
        })->values();
    }

    /** The default count unit: "pc", or the legacy "ea" row where that was kept. */
    public function baseUnitId(string $tenantId): ?int
    {
        return UnitOfMeasure::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('code', ['pc', 'ea'])
            ->orderByRaw("code = 'pc' desc")
            ->value('id');
    }
}
