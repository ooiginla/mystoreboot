<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Catalog\Models\ProductVariant;

final class ProductionOrder extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'planned_quantity' => 'decimal:4',
            'actual_yield_quantity' => 'decimal:4',
            'produced_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductionOrderItem::class);
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function outputVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'output_product_variant_id');
    }

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'source_location_id');
    }

    public function outputLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'output_location_id');
    }
}
