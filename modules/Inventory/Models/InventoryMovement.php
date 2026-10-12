<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use App\Models\User;
use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Catalog\Models\ProductVariant;
use Modules\Inventory\Enums\InventoryMovementType;
use Modules\Inventory\Enums\StockCondition;

final class InventoryMovement extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'movement_type' => InventoryMovementType::class,
            'stock_condition' => StockCondition::class,
            'quantity' => 'decimal:4',
            'entered_quantity' => 'decimal:4',
            'entered_unit_breakdown' => 'array',
            'stock_after' => 'decimal:4',
            'expiry_date' => 'date',
            'occurred_at' => 'datetime',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'destination_inventory_location_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function enteredUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'entered_unit_id');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reversal_of_movement_id');
    }

    public function reversedMovement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_movement_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by_user_id');
    }

    /**
     * The lots this movement drew from (or, for a transfer-in, created). Lets a single
     * movement be traced back to the batch numbers and expiry dates behind it.
     */
    public function batchAllocations(): HasMany
    {
        return $this->hasMany(InventoryMovementBatch::class, 'inventory_movement_id');
    }
}
