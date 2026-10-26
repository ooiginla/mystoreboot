<?php

declare(strict_types=1);

namespace Modules\Customers\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CustomerPriceList extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'allow_additional_discounts' => 'boolean',
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(CustomerPriceListItem::class)->orderBy('product_variant_id')->orderBy('minimum_quantity');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(CustomerGroup::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
