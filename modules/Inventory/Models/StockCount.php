<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Enums\StockCountStatus;

final class StockCount extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => StockCountStatus::class,
            'is_blind' => 'boolean',
            'posted_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockCountItem::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    /**
     * Net value of everything counted so far — positive means the shelf holds more than
     * the system thought, negative is shrinkage.
     */
    public function varianceValueMinor(): int
    {
        return (int) $this->items->sum(fn (StockCountItem $item): int => $item->varianceValueMinor());
    }

    public function countedLineCount(): int
    {
        return $this->items->filter(fn (StockCountItem $item): bool => $item->isCounted())->count();
    }

    public function varianceLineCount(): int
    {
        return $this->items->filter(fn (StockCountItem $item): bool => $item->isCounted() && $item->variance() !== 0.0)->count();
    }
}
