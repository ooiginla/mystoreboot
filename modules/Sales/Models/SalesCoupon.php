<?php

declare(strict_types=1);

namespace Modules\Sales\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Modules\Sales\Enums\DiscountType;

final class SalesCoupon extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'discount_type' => DiscountType::class,
            'discount_percent' => 'decimal:2',
            'starts_at' => 'date',
            'expires_at' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
