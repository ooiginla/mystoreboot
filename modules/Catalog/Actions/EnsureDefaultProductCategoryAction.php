<?php

declare(strict_types=1);

namespace Modules\Catalog\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Business\Models\OnlineStore;
use Modules\Catalog\Enums\CategoryType;
use Modules\Catalog\Enums\ProductType;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductCategory;

final class EnsureDefaultProductCategoryAction
{
    public function execute(string $tenantId): ProductCategory
    {
        return DB::transaction(function () use ($tenantId): ProductCategory {
            $category = ProductCategory::query()
                ->where('tenant_id', $tenantId)
                ->where('category_type', CategoryType::Product->value)
                ->whereRaw('lower(name) = ?', ['uncategorized'])
                ->first();

            if (! $category) {
                $slug = ProductCategory::query()
                    ->where('tenant_id', $tenantId)
                    ->where('slug', 'uncategorized')
                    ->exists()
                        ? 'uncategorized-products'
                        : 'uncategorized';

                $category = ProductCategory::query()->create([
                    'tenant_id' => $tenantId,
                    'parent_id' => null,
                    'category_type' => CategoryType::Product->value,
                    'name' => 'Uncategorized',
                    'slug' => $slug,
                    'description' => 'Products that have not been assigned to another category.',
                    'status' => 'active',
                ]);
            }

            Product::query()
                ->where('tenant_id', $tenantId)
                ->where('product_type', ProductType::Product->value)
                ->whereNull('category_id')
                ->update(['category_id' => $category->id]);

            OnlineStore::query()
                ->where('tenant_id', $tenantId)
                ->each(fn (OnlineStore $store) => $store->categories()->syncWithoutDetaching([$category->id]));

            return $category;
        });
    }
}
