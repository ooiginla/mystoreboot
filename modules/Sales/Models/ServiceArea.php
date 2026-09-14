<?php

declare(strict_types=1);

namespace Modules\Sales\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Business\Models\Branch;
use Modules\Inventory\Models\InventoryLocation;

/**
 * A named part of the floor — "Main Restaurant", "Pool Bar", "Room Service". Groups
 * tables and inherits its stock location from the sales point it hangs off.
 */
final class ServiceArea extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * The store this area sells from. One row: the same location that holds the stock is
     * the one marked "Can sell from here".
     */
    public function sellableLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'sellable_location_id');
    }

    public function tables(): HasMany
    {
        return $this->hasMany(RestaurantTable::class)->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Where stock for this area depletes from. Null rather than a guess — the caller
     * decides what to do when an area has not been pointed at a store yet.
     */
    public function stockLocationId(): ?int
    {
        return $this->sellable_location_id ? (int) $this->sellable_location_id : null;
    }
}
