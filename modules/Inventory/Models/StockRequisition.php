<?php

declare(strict_types=1);

namespace Modules\Inventory\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Enums\RequisitionStatus;

final class StockRequisition extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => RequisitionStatus::class,
            'fulfilled_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockRequisitionItem::class);
    }

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'source_location_id');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'destination_location_id');
    }
}
