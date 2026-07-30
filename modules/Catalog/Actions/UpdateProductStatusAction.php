<?php

declare(strict_types=1);

namespace Modules\Catalog\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Enums\ProductStatus;
use Modules\Catalog\Models\Product;

final class UpdateProductStatusAction
{
    public function execute(Product $product, ProductStatus $status): Product
    {
        return DB::transaction(function () use ($product, $status): Product {
            if (
                $product->has_variants
                && $status === ProductStatus::Active
                && ! $product->variants()->where('status', ProductStatus::Active->value)->exists()
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Make at least one variant Live before publishing this product.',
                ]);
            }

            $product->update(['status' => $status]);

            if (! $product->has_variants) {
                $product->variants()
                    ->oldest('id')
                    ->first()
                    ?->update(['status' => $status]);
            }

            return $product->refresh()->load('variants');
        });
    }
}
