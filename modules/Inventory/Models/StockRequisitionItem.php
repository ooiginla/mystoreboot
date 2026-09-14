<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Models\ProductVariant;

final class StockRequisitionItem extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'requested_quantity' => 'decimal:4',
            'fulfilled_quantity' => 'decimal:4',
        ];
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(StockRequisition::class, 'stock_requisition_id');
    }

    public function componentVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_id');
    }
}
