<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Models\ProductVariant;

final class InventoryStockLevel extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'last_movement_at' => 'datetime',
            'quantity_on_hand' => 'decimal:4',
            'quantity_reserved' => 'decimal:4',
            'reorder_level' => 'decimal:4',
            'reorder_quantity' => 'decimal:4',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function getQuantityAvailableAttribute(): float
    {
        return (float) $this->quantity_on_hand - (float) $this->quantity_reserved;
    }

    public function getStockValueMinorAttribute(): int
    {
        return (int) round(max(0, (float) $this->quantity_on_hand) * (int) $this->average_cost_minor);
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->reorder_level > 0 && $this->quantity_available <= $this->reorder_level;
    }
}
