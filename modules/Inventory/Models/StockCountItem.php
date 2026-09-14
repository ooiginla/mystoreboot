<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Models\ProductVariant;
use Modules\Inventory\Support\Quantity;

final class StockCountItem extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'system_quantity' => 'decimal:4',
            'counted_quantity' => 'decimal:4',
        ];
    }

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
    }

    public function componentVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * A null counted quantity means nobody has been to that shelf yet; zero means they
     * went and found nothing. The two must not be confused when posting.
     */
    public function isCounted(): bool
    {
        return $this->counted_quantity !== null;
    }

    public function variance(): float
    {
        if (! $this->isCounted()) {
            return 0.0;
        }

        return Quantity::round((float) $this->counted_quantity - (float) $this->system_quantity);
    }

    public function varianceValueMinor(): int
    {
        return (int) round($this->variance() * (int) $this->unit_cost_minor);
    }
}
