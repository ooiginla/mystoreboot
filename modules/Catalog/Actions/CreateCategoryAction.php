<?php

declare(strict_types=1);

namespace Modules\Catalog\Actions;

use Modules\Catalog\Models\ProductCategory;

final class CreateCategoryAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): ProductCategory
    {
        return ProductCategory::query()->create([
            'tenant_id' => $data['tenant_id'],
            'parent_id' => $data['parent_id'] ?? null,
            'category_type' => $data['category_type'],
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'status' => 'active',
        ]);
    }
}
