<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Branch;
use Modules\Catalog\Models\ProductVariant;
use Modules\HrPayroll\Models\HrStaff;
use Modules\Inventory\Enums\ProductionVarianceReason;

final class ProductionWasteRecord extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'waste_date' => 'date',
            'quantity' => 'decimal:4',
            'reason' => ProductionVarianceReason::class,
            'deducted_from_stock' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(HrStaff::class, 'hr_staff_id');
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class, 'inventory_movement_id');
    }
}
