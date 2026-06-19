<?php

declare(strict_types=1);

namespace Modules\Customers\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(CustomerPurchase::class);
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
