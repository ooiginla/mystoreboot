<?php

declare(strict_types=1);

namespace Modules\Sales\Models;

use App\Models\User;
use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Business\Models\Branch;
use Modules\Customers\Models\Customer;
use Modules\Sales\Enums\SalesOrderStatus;
use Modules\Sales\Enums\SalesPaymentStatus;

final class SalesOrder extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'order_status' => SalesOrderStatus::class,
            'payment_status' => SalesPaymentStatus::class,
            'order_date' => 'date',
            'is_credit_sale' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tillSession(): BelongsTo
    {
        return $this->belongsTo(SalesTillSession::class, 'sales_till_session_id');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(SalesCoupon::class, 'sales_coupon_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalesOrderPayment::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SalesReturn::class);
    }

    public function getBalanceMinorAttribute(): int
    {
        return max(0, $this->total_minor - $this->paid_minor - $this->refunded_minor);
    }
}
