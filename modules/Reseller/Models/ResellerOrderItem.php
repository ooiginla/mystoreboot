<?php

declare(strict_types=1);

namespace Modules\Reseller\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ResellerOrderItem extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'variant' => 'array',
            'source_price_minor' => 'integer',
            'percentage_markup_basis_points' => 'integer',
            'fixed_markup_minor' => 'integer',
            'unit_selling_price_minor' => 'integer',
            'quantity' => 'integer',
            'line_total_minor' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ResellerOrder::class, 'reseller_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ResellerProduct::class, 'reseller_product_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(ResellerSupplier::class, 'supplier_id');
    }
}
