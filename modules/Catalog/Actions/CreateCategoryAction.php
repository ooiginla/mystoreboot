<?php

declare(strict_types=1);

namespace Modules\Catalog\Actions;

use Modules\Business\Models\OnlineStore;
use Modules\Catalog\Enums\CategoryType;
use Modules\Catalog\Models\ProductCategory;

final class CreateCategoryAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): ProductCategory
    {
        $category = ProductCategory::query()->create([
            'tenant_id' => $data['tenant_id'],
            'parent_id' => $data['parent_id'] ?? null,
            'category_type' => $data['category_type'],
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'status' => 'active',
        ]);

        if ($category->category_type === CategoryType::Product) {
            OnlineStore::query()
                ->where('tenant_id', $category->tenant_id)
                ->each(fn (OnlineStore $store) => $store->categories()->syncWithoutDetaching([$category->id]));
        }

        return $category;
    }
}
