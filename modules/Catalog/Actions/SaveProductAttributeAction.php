<?php

declare(strict_types=1);

namespace Modules\Catalog\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\ProductAttributeDefinition;

final class SaveProductAttributeAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?ProductAttributeDefinition $attribute = null): ProductAttributeDefinition
    {
        return DB::transaction(function () use ($data, $attribute): ProductAttributeDefinition {
            $attribute ??= new ProductAttributeDefinition;
            $attribute->fill([
                'tenant_id' => $data['tenant_id'],
                'name' => $data['name'],
                'slug' => $data['slug'],
            ]);
            $attribute->save();

            $keptValueIds = [];
            $values = collect(explode(',', (string) $data['values']))
                ->map(fn (string $value): string => trim($value))
                ->filter()
                ->unique(fn (string $value): string => strtolower($value))
                ->values();

            foreach ($values as $index => $value) {
                $attributeValue = $attribute->values()
                    ->whereRaw('lower(value) = ?', [strtolower($value)])
                    ->first() ?? $attribute->values()->make();

                $attributeValue->fill([
                    'value' => $value,
                    'sort_order' => $index,
                ]);
                $attributeValue->save();
                $keptValueIds[] = $attributeValue->id;
            }

            $attribute->values()->whereKeyNot($keptValueIds)->delete();

            return $attribute->refresh()->load('values');
        });
    }
}
