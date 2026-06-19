<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Enums\TaxBehavior;

final class Product extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'product_type' => ProductType::class,
            'status' => ProductStatus::class,
            'tax_behavior' => TaxBehavior::class,
            'has_variants' => 'boolean',
            'tax_rate' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ProductTag::class, 'product_product_tag')->withTimestamps();
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(ProductAttributeValue::class, 'product_attribute_value_product')->withTimestamps();
    }
}
