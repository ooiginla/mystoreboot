<?php

declare(strict_types=1);

namespace Modules\Sales\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Models\UnitOfMeasure;

/**
 * A modifier as it was chosen on one line — names, price and ingredient effect copied
 * at the time, so later edits to the option never rewrite an existing bill.
 */
final class SalesOrderItemModifier extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price_delta_minor' => 'integer',
            'component_quantity' => 'decimal:4',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class, 'sales_order_item_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(ModifierOption::class, 'modifier_option_id');
    }

    public function componentUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'component_unit_id');
    }

    /**
     * Something taken out of the dish. The kitchen screen shouts these — a missed
     * "no peanuts" is a safety incident, not a service slip.
     */
    public function isRemoval(): bool
    {
        return (float) $this->component_quantity < 0
            || str_starts_with(strtolower(trim($this->option_name)), 'no ');
    }
}
