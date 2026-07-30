<?php

declare(strict_types=1);

namespace Modules\Catalog\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Catalog\Models\ProductCollection;

final class SaveProductCollectionAction
{
    /**
     * @param  array{tenant_id: string, name: string, is_visible: bool}  $data
     */
    public function execute(array $data, ?ProductCollection $collection = null): ProductCollection
    {
        return DB::transaction(function () use ($data, $collection): ProductCollection {
            $collection ??= new ProductCollection;
            $collection->fill([
                'tenant_id' => $data['tenant_id'],
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['tenant_id'], $data['name'], $collection),
                'collection_type' => 'manual',
                'is_visible' => $data['is_visible'],
            ]);
            $collection->save();

            return $collection->refresh();
        });
    }

    private function uniqueSlug(string $tenantId, string $name, ProductCollection $collection): string
    {
        $base = Str::slug($name) ?: 'collection';
        $slug = $base;
        $suffix = 2;

        while (ProductCollection::query()
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->when($collection->exists, fn ($query) => $query->whereKeyNot($collection->id))
            ->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
