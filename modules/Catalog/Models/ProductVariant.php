<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\TaxBehavior;
use Modules\Inventory\Models\UnitOfMeasure;

final class ProductVariant extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'tax_behavior' => TaxBehavior::class,
            'tax_rate' => 'decimal:2',
            'purchase_to_base_factor' => 'decimal:6',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'base_unit_id');
    }

    public function purchaseUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'purchase_unit_id');
    }

    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(ProductOptionValue::class, 'product_variant_option_values')
            ->withTimestamps();
    }
}
