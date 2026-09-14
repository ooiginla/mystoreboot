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
use Modules\Sales\Enums\CheckStatus;
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
            'check_status' => CheckStatus::class,
            'opened_at' => 'datetime',
            'cover_count' => 'integer',
            'service_charge_rate' => 'decimal:2',
            'bill_printed_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    public function serviceArea(): BelongsTo
    {
        return $this->belongsTo(ServiceArea::class, 'service_area_id');
    }

    /** The check this one was split off from. */
    public function parentCheck(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_sales_order_id');
    }

    /** Checks split off this one — they sit on the same table. */
    public function splitChecks(): HasMany
    {
        return $this->hasMany(self::class, 'parent_sales_order_id');
    }

    /** Lines that count towards the bill: everything not voided. */
    public function liveItems(): \Illuminate\Support\Collection
    {
        return $this->items->filter(fn (SalesOrderItem $item): bool => $item->voided_at === null)->values();
    }

    /**
     * Any change after the bill was printed makes that bill wrong, so the check goes back
     * to open and must be printed again before it can be paid.
     */
    public function reopenIfBilled(): void
    {
        if ($this->check_status === CheckStatus::BillPrinted) {
            $this->update(['check_status' => CheckStatus::Open->value, 'bill_printed_at' => null]);
        }
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'restaurant_table_id');
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'server_user_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(KitchenOrderTicket::class, 'sales_order_id');
    }

    /**
     * A dine-in check rather than an ordinary retail sale. Everything downstream keys off
     * this: a null service area means the order behaves exactly as it always has.
     */
    public function isCheck(): bool
    {
        return $this->service_area_id !== null;
    }

    /**
     * Items sitting on the pad that have not been sent to the kitchen. These can still be
     * changed or removed freely — nothing has been cooked.
     */
    public function unfiredItems(): \Illuminate\Support\Collection
    {
        return $this->items->filter(
            fn (SalesOrderItem $item): bool => $item->fired_at === null && $item->voided_at === null,
        )->values();
    }

    public function firedItems(): \Illuminate\Support\Collection
    {
        return $this->items->filter(
            fn (SalesOrderItem $item): bool => $item->fired_at !== null && $item->voided_at === null,
        )->values();
    }

    /** Minutes the table has been occupied, for the floor map. */
    public function openMinutes(): int
    {
        return (int) ($this->opened_at?->diffInMinutes(now()) ?? 0);
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
