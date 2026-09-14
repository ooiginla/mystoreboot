<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Catalog\Models\ProductVariant;
use Modules\Inventory\Enums\StockCondition;
use Modules\Inventory\Support\Quantity;

final class InventoryBatch extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'stock_condition' => StockCondition::class,
            'quantity_remaining' => 'decimal:4',
            'expiry_date' => 'date',
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

    /**
     * Every movement that touched this lot — the receipt that created it and each
     * outbound draw against it.
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(InventoryMovementBatch::class, 'inventory_batch_id');
    }

    /**
     * The lot this one was split from when stock was transferred in from another store.
     */
    public function sourceBatch(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_inventory_batch_id');
    }

    /**
     * Lots created downstream of this one by transfers out. Following these recursively
     * is what lets a recall chase stock across every store it reached.
     */
    public function childBatches(): HasMany
    {
        return $this->hasMany(self::class, 'source_inventory_batch_id');
    }

    /**
     * Movements are stored with a signed delta, so outbound draws are the negative ones.
     *
     * @return \Illuminate\Support\Collection<int, InventoryMovementBatch>
     */
    public function outboundAllocations(): \Illuminate\Support\Collection
    {
        return $this->allocations
            ->filter(fn (InventoryMovementBatch $a): bool => (float) ($a->movement?->quantity ?? 0) < 0)
            ->values();
    }

    public function consumedQuantity(): float
    {
        return Quantity::round((float) $this->outboundAllocations()->sum(fn (InventoryMovementBatch $a): float => (float) $a->quantity));
    }

    /**
     * What the lot originally held: what is left plus everything drawn from it.
     */
    public function receivedQuantity(): float
    {
        return Quantity::round((float) $this->quantity_remaining + $this->consumedQuantity());
    }

    public function isExhausted(): bool
    {
        return (float) $this->quantity_remaining <= 0;
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }
}
