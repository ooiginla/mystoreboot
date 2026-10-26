<?php

declare(strict_types=1);

namespace Modules\Customers\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CustomerGroup extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['credit_enabled' => 'boolean'];
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(CustomerPriceList::class, 'customer_price_list_id');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
