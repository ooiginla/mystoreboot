<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Reseller\Models\ResellerProduct;

final class ProductTag extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_product_tag')->withTimestamps();
    }

    public function resellerProducts(): BelongsToMany
    {
        return $this->belongsToMany(ResellerProduct::class, 'reseller_product_product_tag')->withTimestamps();
    }
}
