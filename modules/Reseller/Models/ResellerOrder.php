<?php

declare(strict_types=1);

namespace Modules\Reseller\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ResellerOrder extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'delivery_address' => 'array',
            'subtotal_minor' => 'integer',
            'delivery_minor' => 'integer',
            'discount_minor' => 'integer',
            'total_minor' => 'integer',
            'placed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ResellerOrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ResellerPayment::class);
    }
}
