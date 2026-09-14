<?php

declare(strict_types=1);

namespace Modules\Sales\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Sales\Enums\TableStatus;

final class RestaurantTable extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => TableStatus::class,
            'seats' => 'integer',
        ];
    }

    public function serviceArea(): BelongsTo
    {
        return $this->belongsTo(ServiceArea::class);
    }

    public function checks(): HasMany
    {
        return $this->hasMany(SalesOrder::class, 'restaurant_table_id');
    }

    /**
     * The check currently sitting on this table, if any. Splits create sibling checks on
     * the same table, so this prefers the original and falls back to a remaining split —
     * a table is not free while any of its bills is still open.
     */
    public function openCheck(): ?SalesOrder
    {
        return $this->checks()
            ->whereIn('check_status', ['open', 'bill_printed'])
            ->orderByRaw('parent_sales_order_id is null desc')
            ->orderBy('id')
            ->first();
    }
}
