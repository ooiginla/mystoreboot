<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class ProductAttributeValue extends Model
{
    protected $guarded = [];

    public function definition(): BelongsTo
    {
        return $this->belongsTo(ProductAttributeDefinition::class, 'product_attribute_definition_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_attribute_value_product')->withTimestamps();
    }
}
