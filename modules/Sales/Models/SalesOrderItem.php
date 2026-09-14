<?php

declare(strict_types=1);

namespace Modules\Sales\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Catalog\Models\ProductVariant;

final class SalesOrderItem extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'custom_selections' => 'array',
            'personalization' => 'array',
            'quantity' => 'decimal:4',
            'quantity_returned' => 'decimal:4',
            'course' => 'integer',
            'seat_number' => 'integer',
            'fired_at' => 'datetime',
            'voided_at' => 'datetime',
            'ingredients_depleted_at' => 'datetime',
            'consumed_cost_minor' => 'integer',
        ];
    }

    public function modifiers(): HasMany
    {
        return $this->hasMany(SalesOrderItemModifier::class, 'sales_order_item_id')->orderBy('id');
    }

    /**
     * How the chosen modifiers change what one of this item consumes, in base units,
     * keyed by ingredient variant. "No onions" is negative, "extra chicken" positive.
     *
     * @return array<int, float>
     */
    public function ingredientAdjustments(): array
    {
        $this->loadMissing('modifiers.componentUnit');

        $adjustments = [];

        foreach ($this->modifiers as $modifier) {
            $quantity = (float) $modifier->component_quantity;

            if (! $modifier->component_product_variant_id || $quantity == 0.0) {
                continue;
            }

            $unit = $modifier->componentUnit;
            $base = $unit && $unit->isConvertible() ? $quantity * (float) $unit->to_base_factor : $quantity;
            $variantId = (int) $modifier->component_product_variant_id;

            $adjustments[$variantId] = ($adjustments[$variantId] ?? 0.0) + $base;
        }

        return $adjustments;
    }

    /**
     * Sent to the kitchen. Once true the food is being cooked, so removing the line is a
     * void that must be written off as waste rather than a free cancel.
     */
    public function isFired(): bool
    {
        return $this->fired_at !== null;
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function inventoryLocation(): BelongsTo
    {
        return $this->belongsTo(\Modules\Inventory\Models\InventoryLocation::class, 'inventory_location_id');
    }

    public function getQuantityReturnableAttribute(): float
    {
        return max(0, (float) $this->quantity - (float) $this->quantity_returned);
    }
}
