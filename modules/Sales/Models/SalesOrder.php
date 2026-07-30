<?php

declare(strict_types=1);

namespace Modules\Sales\Models;

use App\Models\User;
use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Modules\Business\Models\Branch;
use Modules\Customers\Models\Customer;
use Modules\Inventory\Models\InventoryLocation;
use Modules\Sales\Enums\SalesOrderStatus;
use Modules\Sales\Enums\SalesPaymentStatus;

final class SalesOrder extends Model
{
    use BelongsToTenant;

    /** Unambiguous alphabet for tracking references (no 0/O/1/I). */
    private const TRACKING_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (SalesOrder $order): void {
            if (blank($order->tracking_reference)) {
                $order->tracking_reference = self::freshTrackingReference();
            }
        });
    }

    /**
     * A globally unique, buyer-facing tracking reference (e.g. TRK-4KQ7MPX2).
     * Checked against the whole table (no tenant scope) so references never
     * collide across tenants; the unique DB index is the final guarantee.
     */
    public static function freshTrackingReference(): string
    {
        $length = strlen(self::TRACKING_ALPHABET);

        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= self::TRACKING_ALPHABET[random_int(0, $length - 1)];
            }
            $reference = 'TRK-'.$code;
        } while (DB::table('sales_orders')->where('tracking_reference', $reference)->exists());

        return $reference;
    }

    protected function casts(): array
    {
        return [
            'order_status' => SalesOrderStatus::class,
            'payment_status' => SalesPaymentStatus::class,
            'order_date' => 'date',
            'is_credit_sale' => 'boolean',
            'stock_reserved' => 'boolean',
            'reserved_until' => 'datetime',
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

    public function inventoryLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class);
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

    public function refunds(): HasMany
    {
        return $this->hasMany(SalesOrderRefund::class);
    }

    public function getBalanceMinorAttribute(): int
    {
        return max(0, $this->total_minor - $this->paid_minor - $this->refunded_minor);
    }
}
