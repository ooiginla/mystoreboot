<?php

declare(strict_types=1);

namespace Modules\Catalog\Providers;

use App\Support\Modules\ModuleServiceProvider;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductAttributeDefinition;
use Modules\Catalog\Models\ProductAttributeValue;
use Modules\Catalog\Models\ProductCategory;
use Modules\Catalog\Models\ProductTag;
use Modules\Catalog\Models\ProductTax;
use Modules\Catalog\Models\ProductVariant;
use Modules\Catalog\Observers\CatalogSearchObserver;
use Modules\Procurement\Models\Vendor;

final class CatalogServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        foreach ([
            Product::class,
            ProductVariant::class,
            ProductCategory::class,
            ProductTag::class,
            ProductTax::class,
            ProductAttributeDefinition::class,
            ProductAttributeValue::class,
            Vendor::class,
        ] as $model) {
            $model::observe(CatalogSearchObserver::class);
        }
    }

    protected function moduleName(): string
    {
        return 'Catalog';
    }
}
