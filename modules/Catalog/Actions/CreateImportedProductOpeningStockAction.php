<?php

declare(strict_types=1);

namespace Modules\Catalog\Actions;

use Modules\Catalog\Models\Product;
use Modules\Inventory\Actions\EnsureInventoryLocationsAction;
use Modules\Inventory\Actions\PostInventoryMovementAction;
use Modules\Inventory\Enums\InventoryMovementType;
use Modules\Inventory\Enums\StockCondition;
use Modules\Subscriptions\Support\TenantModuleAccess;
use Modules\Tenancy\Models\Tenant;

final class CreateImportedProductOpeningStockAction
{
    public function __construct(
        private readonly EnsureInventoryLocationsAction $ensureLocations,
        private readonly PostInventoryMovementAction $postMovement,
        private readonly TenantModuleAccess $moduleAccess,
    ) {}

    public function execute(Product $product): void
    {
        $tenant = Tenant::query()->find($product->tenant_id);

        if (! $tenant || ! $this->moduleAccess->allows($tenant, 'inventory')) {
            return;
        }

        $location = $this->ensureLocations->forTenant($tenant)->first();
        $variant = $product->variants()->oldest('id')->first();

        if (! $location || ! $variant) {
            return;
        }

        $this->postMovement->execute([
            'tenant_id' => $product->tenant_id,
            'inventory_location_id' => $location->id,
            'product_variant_id' => $variant->id,
            'movement_type' => InventoryMovementType::OpeningStock->value,
            'stock_condition' => StockCondition::Sellable->value,
            'quantity' => 1,
            'unit_cost_minor' => 0,
            'allow_zero_opening_cost' => true,
            'reference_type' => 'catalog_import',
            'reference_id' => $product->id,
            'notes' => 'Provisional imported opening stock; cost to be confirmed during product review.',
        ]);
    }
}
