<?php

declare(strict_types=1);

namespace Modules\Catalog\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Enums\CategoryType;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductCategory;

final class DeleteCategoryAction
{
    public function __construct(
        private readonly EnsureDefaultProductCategoryAction $ensureDefaultProductCategory,
    ) {}

    public function execute(ProductCategory $category): int
    {
        return DB::transaction(function () use ($category): int {
            $fallback = $this->fallbackCategory($category);

            if ($fallback->is($category)) {
                throw ValidationException::withMessages([
                    'category' => 'The Uncategorized category is required and cannot be deleted.',
                ]);
            }

            $reassignedProducts = Product::query()
                ->withTrashed()
                ->where('tenant_id', $category->tenant_id)
                ->where('category_id', $category->id)
                ->update(['category_id' => $fallback->id]);

            ProductCategory::query()
                ->where('tenant_id', $category->tenant_id)
                ->where('parent_id', $category->id)
                ->update(['parent_id' => $category->parent_id]);

            $category->delete();

            return $reassignedProducts;
        });
    }

    private function fallbackCategory(ProductCategory $category): ProductCategory
    {
        if ($category->category_type === CategoryType::Product) {
            return $this->ensureDefaultProductCategory->execute((string) $category->tenant_id);
        }

        return ProductCategory::query()->firstOrCreate(
            [
                'tenant_id' => $category->tenant_id,
                'category_type' => CategoryType::Service->value,
                'name' => 'Uncategorized',
            ],
            [
                'parent_id' => null,
                'slug' => 'uncategorized-services',
                'description' => 'Services that have not been assigned to another category.',
                'status' => 'active',
            ],
        );
    }
}
