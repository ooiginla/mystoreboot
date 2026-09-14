<?php

declare(strict_types=1);

namespace Modules\Catalog\Models;

use App\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Catalog\Actions\EnsureDefaultProductCategoryAction;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Enums\TaxBehavior;

final class Product extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $guarded = [];

    protected static function booted(): void
    {
        self::saving(function (Product $product): void {
            $productType = $product->product_type instanceof ProductType
                ? $product->product_type
                : ProductType::tryFrom((string) $product->product_type);

            if ($productType === ProductType::Product && ! $product->category_id && filled($product->tenant_id)) {
                $product->category_id = app(EnsureDefaultProductCategoryAction::class)
                    ->execute((string) $product->tenant_id)
                    ->id;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'product_type' => ProductType::class,
            'status' => ProductStatus::class,
            'tax_behavior' => TaxBehavior::class,
            'has_variants' => 'boolean',
            'track_inventory' => 'boolean',
            'is_finished_product' => 'boolean',
            'stock_policy' => \Modules\Catalog\Enums\StockPolicy::class,
            'custom_fields' => 'array',
            'personalization_settings' => 'array',
            'tax_rate' => 'decimal:2',
            'seo' => 'array',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function prepStation(): BelongsTo
    {
        return $this->belongsTo(\Modules\Inventory\Models\InventoryLocation::class, 'prep_location_id');
    }

    /** Choices offered with this item on the restaurant pad — "Spice level", "Extras". */
    public function modifierGroups(): BelongsToMany
    {
        return $this->belongsToMany(\Modules\Sales\Models\ModifierGroup::class, 'modifier_group_product')
            ->withPivot(['tenant_id', 'sort_order'])
            ->withTimestamps()
            ->orderBy('modifier_groups.sort_order')
            ->orderBy('modifier_groups.name');
    }

    public function unitCategory(): BelongsTo
    {
        return $this->belongsTo(\Modules\Inventory\Models\UnitCategory::class, 'unit_category_id');
    }

    public function usesRecipeDepletion(): bool
    {
        return $this->stock_policy === \Modules\Catalog\Enums\StockPolicy::Recipe;
    }

    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ProductTag::class, 'product_product_tag')->withTimestamps();
    }

    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(ProductBadge::class, 'product_badge_product')
            ->orderByRaw('product_badges.sort_order is null')
            ->orderBy('product_badges.sort_order')
            ->orderBy('product_badges.name')
            ->withTimestamps();
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(ProductCollection::class, 'product_collection_items')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    public function taxes(): BelongsToMany
    {
        return $this->belongsToMany(ProductTax::class, 'product_product_tax')->withTimestamps();
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(ProductAttributeValue::class, 'product_attribute_value_product')->withTimestamps();
    }
}
