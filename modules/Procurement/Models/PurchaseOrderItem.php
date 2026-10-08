<?php

declare(strict_types=1);

namespace Modules\Procurement\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Models\ProductVariant;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Inventory\Models\UnitOfMeasure;

final class PurchaseOrderItem extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'decimal:4',
            'quantity_received' => 'decimal:4',
            'entered_quantity' => 'decimal:4',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    public function enteredUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'entered_unit_id');
    }

    public function getQuantityPendingAttribute(): float
    {
        return max(0, (float) $this->quantity_ordered - (float) $this->quantity_received);
    }
}
