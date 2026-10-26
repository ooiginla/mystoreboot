<?php

declare(strict_types=1);

namespace Modules\Customers\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Customers\Enums\CustomerCreditPolicy;
use Modules\Customers\Enums\CustomerStatus;

final class Customer extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => CustomerStatus::class,
            'birthday' => 'date',
            'anniversary' => 'date',
            'last_purchase_at' => 'date',
            'credit_policy' => CustomerCreditPolicy::class,
            'credit_hold' => 'boolean',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id');
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(CustomerPriceList::class, 'customer_price_list_id');
    }

    public function specificPrices(): HasMany
    {
        return $this->hasMany(CustomerSpecificPrice::class);
    }

    public function balanceAdjustments(): HasMany
    {
        return $this->hasMany(CustomerBalanceAdjustment::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(CustomerPurchase::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(CustomerFollowUp::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function getNameAttribute(): string
    {
        return trim($this->first_name.' '.($this->last_name ?? ''));
    }
}
