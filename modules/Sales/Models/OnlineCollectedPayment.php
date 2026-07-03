<?php

declare(strict_types=1);

namespace Modules\Sales\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Business\Models\Branch;

final class OnlineCollectedPayment extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_settled' => 'boolean',
            'collected_at' => 'datetime',
            'verified_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function orderPayment(): BelongsTo
    {
        return $this->belongsTo(SalesOrderPayment::class, 'sales_order_payment_id');
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(OnlinePaymentSettlement::class, 'online_payment_settlement_id');
    }
}
