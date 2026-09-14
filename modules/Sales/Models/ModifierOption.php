<?php

declare(strict_types=1);

namespace Modules\Sales\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Models\ProductVariant;
use Modules\Inventory\Models\UnitOfMeasure;

/**
 * One choice inside a group. It can change the price, the ingredients consumed, or
 * both — "extra chicken" costs more *and* takes more chicken off the grill's shelf.
 */
final class ModifierOption extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price_delta_minor' => 'integer',
            'component_quantity' => 'decimal:4',
            'sort_order' => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ModifierGroup::class, 'modifier_group_id');
    }

    public function componentVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'component_product_variant_id');
    }

    public function componentUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'component_unit_id');
    }
}
