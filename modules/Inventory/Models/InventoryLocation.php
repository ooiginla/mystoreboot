<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Business\Models\Branch;

final class InventoryLocation extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            // location_type is a tenant-configurable lookup key (see location_types),
            // so it stays a plain string rather than a fixed enum.
            'is_sellable_point' => 'boolean',
            'is_prep_station' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function replenishmentSource(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replenishment_source_location_id');
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(InventoryStockLevel::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
