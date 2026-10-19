<?php

declare(strict_types=1);

namespace Modules\Catalog\Observers;

use Illuminate\Database\Eloquent\Model;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductAttributeDefinition;
use Modules\Catalog\Models\ProductAttributeValue;
use Modules\Catalog\Models\ProductCategory;
use Modules\Catalog\Models\ProductTag;
use Modules\Catalog\Models\ProductTax;
use Modules\Catalog\Models\ProductVariant;
use Modules\Catalog\Support\CatalogRevisionManager;
use Modules\Catalog\Support\ProductSearchDocumentBuilder;
use Modules\Procurement\Models\Vendor;

final class CatalogSearchObserver
{
    public function saved(Model $model): void
    {
        $this->refreshAffectedProducts($model);
    }

    public function deleted(Model $model): void
    {
        $this->refreshAffectedProducts($model);
    }

    public function restored(Model $model): void
    {
        $this->refreshAffectedProducts($model);
    }

    private function refreshAffectedProducts(Model $model): void
    {
        if ($model instanceof Product) {
            app(ProductSearchDocumentBuilder::class)->rebuild($model);

            return;
        }

        if ($model instanceof ProductVariant) {
            $product = Product::withTrashed()->find($model->product_id);

            if ($product) {
                app(ProductSearchDocumentBuilder::class)->rebuild($product);
            }

            return;
        }

        $query = match (true) {
            $model instanceof ProductCategory => Product::query()->where('category_id', $model->id),
            $model instanceof ProductTag => $model->products(),
            $model instanceof ProductTax => $model->products(),
            $model instanceof ProductAttributeValue => $model->products(),
            $model instanceof ProductAttributeDefinition => Product::query()->whereHas(
                'attributeValues',
                fn ($query) => $query->where('product_attribute_definition_id', $model->id),
            ),
            $model instanceof Vendor => Product::query()->whereHas(
                'suppliers',
                fn ($query) => $query->where('vendors.id', $model->id),
            ),
            default => null,
        };

        if (! $query) {
            return;
        }

        $tenantIds = [];
        $query->select('products.*')->chunkById(200, function ($products) use (&$tenantIds): void {
            foreach ($products as $product) {
                app(ProductSearchDocumentBuilder::class)->rebuild($product, false);
                $tenantIds[(string) $product->tenant_id] = true;
            }
        }, 'products.id', 'id');

        foreach (array_keys($tenantIds) as $tenantId) {
            app(CatalogRevisionManager::class)->bump($tenantId);
        }
    }
}
