<?php

declare(strict_types=1);

namespace Modules\Procurement\Models;

use App\Models\User;
use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Procurement\Enums\PaymentStatus;
use Modules\Procurement\Enums\PurchaseOrderStatus;

final class PurchaseOrder extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'order_date' => 'date',
            'expected_delivery_date' => 'date',
            'approved_at' => 'date',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(VendorPayment::class);
    }

    public function getBalanceMinorAttribute(): int
    {
        return max(0, $this->total_minor - $this->paid_minor);
    }
}
