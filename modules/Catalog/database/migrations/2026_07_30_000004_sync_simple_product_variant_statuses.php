<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Modules\Catalog\Models\Product;

return new class extends Migration
{
    public function up(): void
    {
        Product::query()
            ->where('has_variants', false)
            ->cursor()
            ->each(function (Product $product): void {
                $product->variants()
                    ->oldest('id')
                    ->first()
                    ?->update(['status' => $product->status]);
            });
    }

    public function down(): void
    {
        // Data migration: the previous variant statuses cannot be reconstructed safely.
    }
};
